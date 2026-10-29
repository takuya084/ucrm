<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Facility;
use App\Services\YoyakuApiService;
use App\Services\YoyakuLinkGuard;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * 児童 (Child) と p-yoyaku 利用者 (User) の紐付け管理。
 *
 * 想定する3導入パターン:
 *  - p-yoyakuのみ先行導入 → はぐくむ追加導入: p-yoyaku側に既存Userがいる状態から
 *    CSVで child_id / yoyaku_user_id(またはメール) を明示指定して紐付ける(linkCsv)
 *  - はぐくむのみ先行導入 → p-yoyaku追加導入: はぐくむ側の既存Childからp-yoyaku
 *    アカウントを新規作成して自動で紐付ける(createAccounts)
 *  - 両方はじめから導入: はぐくむで児童登録した直後に createAccounts を使う
 *
 * 名前の自動突合はしない(同姓同名等の誤結合を避けるため)。
 */
class ChildrenYoyakuLinkController extends Controller
{
    public function index(YoyakuApiService $api)
    {
        $facility = Facility::findOrFail($this->facilityId());

        return Inertia::render('Children/YoyakuLink', $this->buildProps($facility, $api));
    }

    /**
     * 選択した児童について p-yoyaku アカウントを一括作成し、yoyaku_user_id を紐付ける。
     * 外部システムへのHTTP呼び出しを伴うため、CSV一括登録のような「全件検証してから
     * 一括コミット」はできない(1件ごとに成否が確定する)。1件ずつ独立して処理し、
     * 結果を行ごとに返す。
     */
    public function createAccounts(Request $request, YoyakuApiService $api)
    {
        $facility = Facility::findOrFail($this->facilityId());

        if (!$facility->yoyaku_business_id) {
            return back()->with(['message' => 'p-yoyaku連携が設定されていません。施設設定から連携を設定してください。', 'status' => 'danger']);
        }

        $request->validate([
            'child_ids'   => ['required', 'array', 'min:1'],
            'child_ids.*' => ['integer', 'distinct', Rule::exists('children', 'id')->where('facility_id', $facility->id)->whereNull('deleted_at')],
        ]);

        YoyakuLinkGuard::assertConfiguration($facility, $facility);

        $children = Child::where('facility_id', $facility->id)
            ->whereIn('id', $request->child_ids)
            ->whereNull('yoyaku_user_id')
            ->with('school:id,name')
            ->get();

        $results = [];
        foreach ($children as $child) {
            try {
                YoyakuLinkGuard::assertConfiguration(Facility::findOrFail($facility->id), $facility);
                $res = $api->createUser([
                    'name' => $child->name,
                    'school_name' => $child->school?->name,
                    'external_ref' => "ucrm:{$child->id}",
                ], $facility->id, hash('sha256', (string) $facility->yoyaku_api_token));
                Validator::make($res ?? [], ['id' => ['required', 'integer', 'min:1'],
                    'business_id' => ['sometimes', 'integer', 'min:1'],
                    'email' => ['sometimes', 'nullable', 'string'], 'password' => ['sometimes', 'nullable', 'string']])->validate();
                if (isset($res['business_id']) && (string) $res['business_id'] !== (string) $facility->yoyaku_business_id) {
                    throw ValidationException::withMessages(['yoyaku_user_id' => '作成結果の事業所が一致しません。']);
                }
                DB::transaction(function () use ($facility, $child, $res) {
                    $currentFacility = YoyakuLinkGuard::lockFacility($facility->id);
                    YoyakuLinkGuard::assertConfiguration($currentFacility, $facility);
                    $current = Child::where('facility_id', $facility->id)->whereKey($child->id)->lockForUpdate()->first();
                    if (!$current || $current->yoyaku_user_id !== null) {
                        throw ValidationException::withMessages(['yoyaku_user_id' => '児童の紐付けが変更されています。']);
                    }
                    YoyakuLinkGuard::assertAvailable($facility->id, $current->id, (int) $res['id']);
                    $current->update(['yoyaku_user_id' => (int) $res['id']]);
                });
            } catch (ValidationException | \RuntimeException $e) {
                // An external account may already exist. Do not expose its credentials
                // or overwrite a conflicting local link; keep the per-child failure.
                $results[] = ['child_id' => $child->id, 'child_name' => $child->name, 'ok' => false];
                continue;
            }

            $results[] = [
                'child_id'   => $child->id,
                'child_name' => $child->name,
                'ok'         => true,
                // 冪等ヒット(既存アカウントを再利用)の場合は password が無い
                'email'      => $res['email'] ?? null,
                'password'   => $res['password'] ?? null,
            ];
        }

        $okCount = count(array_filter($results, fn ($r) => $r['ok']));
        $ngCount = count($results) - $okCount;
        $message = "{$okCount}件のアカウントを作成しました。";
        if ($ngCount > 0) {
            $message .= "（{$ngCount}件は通信・応答または紐付けの確認で停止しました。連携先にアカウントが作成済みの可能性もあるため、設定を確認してください）";
        }

        return to_route('children.yoyaku-link')->with([
            'message'            => $message,
            'status'             => $ngCount > 0 ? 'warning' : 'success',
            'yoyaku_link_results' => $results,
        ]);
    }

    /**
     * CSVで child_id と p-yoyaku 側 yoyaku_user_id(またはメール) を明示指定して紐付ける。
     * 部分成功はさせない: 1行でもエラーがあれば全件中止。
     */
    public function linkCsv(Request $request, YoyakuApiService $api)
    {
        $facility = Facility::findOrFail($this->facilityId());

        if (!$facility->yoyaku_business_id) {
            return back()->with(['message' => 'p-yoyaku連携が設定されていません。施設設定から連携を設定してください。', 'status' => 'danger']);
        }

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $rows = $this->parseCsv($request->file('csv_file')->getRealPath());
        if (empty($rows)) {
            return back()->with(['message' => 'CSVにデータ行がありません。', 'status' => 'danger']);
        }

        $originalChildren = Child::where('facility_id', $facility->id)
            ->whereIn('id', array_column($rows, 'child_id'))->get(['id', 'yoyaku_user_id'])->keyBy('id');
        YoyakuLinkGuard::assertConfiguration($facility, $facility);
        $yoyakuUsers = $api->listUsers((int) $facility->yoyaku_business_id, $facility->id);
        if ($yoyakuUsers === null) {
            return back()->with(['message' => 'p-yoyaku側との通信に失敗しました。時間を置いて再度お試しください。', 'status' => 'danger']);
        }
        if (!array_is_list($yoyakuUsers)) {
            throw ValidationException::withMessages(['csv_file' => '利用者一覧の応答が不正です。']);
        }
        $validYoyakuIds = [];
        $emailToId = [];
        foreach ($yoyakuUsers as $user) {
            if (!is_array($user) || Validator::make($user, ['id' => ['required', 'integer', 'min:1'], 'email' => ['nullable', 'string']])->fails()) {
                throw ValidationException::withMessages(['csv_file' => '利用者一覧の応答が不正です。']);
            }
            $id = (int) $user['id'];
            $email = strtolower(trim($user['email'] ?? ''));
            if (isset($validYoyakuIds[$id]) || ($email !== '' && isset($emailToId[$email]))) {
                throw ValidationException::withMessages(['csv_file' => '利用者一覧のIDまたはメールが重複しています。']);
            }
            $validYoyakuIds[$id] = true;
            if ($email !== '') $emailToId[$email] = $id;
        }

        $errors = [];
        $updates = [];
        $seenChildren = [];
        $seenUsers = [];

        foreach ($rows as $i => $row) {
            $lineNo = $i + 2;
            $childId       = trim($row['child_id'] ?? '');
            $yoyakuUserId  = trim($row['yoyaku_user_id'] ?? '');
            $yoyakuEmail   = strtolower(trim($row['yoyaku_email'] ?? ''));

            if ($childId === '' || !ctype_digit($childId) || (int) $childId < 1) {
                $errors[] = "{$lineNo}行目: child_id が不正です。";
                continue;
            }

            $resolvedYoyakuId = null;
            if ($yoyakuUserId !== '' && (!ctype_digit($yoyakuUserId) || (int) $yoyakuUserId < 1)) {
                $errors[] = "{$lineNo}行目: yoyaku_user_id が不正です。";
                continue;
            }
            if ($yoyakuUserId !== '') {
                $resolvedYoyakuId = (int) $yoyakuUserId;
            } elseif ($yoyakuEmail !== '' && isset($emailToId[$yoyakuEmail])) {
                $resolvedYoyakuId = (int) $emailToId[$yoyakuEmail];
            }

            if ($yoyakuEmail !== '' && (!isset($emailToId[$yoyakuEmail]) || $emailToId[$yoyakuEmail] !== $resolvedYoyakuId)) {
                $errors[] = "{$lineNo}行目: IDとメールの指定が一致しません。";
                continue;
            }

            if (!$resolvedYoyakuId) {
                $errors[] = "{$lineNo}行目: yoyaku_user_id または yoyaku_email がp-yoyaku側の利用者と一致しません。";
                continue;
            }

            if (!isset($validYoyakuIds[$resolvedYoyakuId])) {
                $errors[] = "{$lineNo}行目: p-yoyaku側にID「{$resolvedYoyakuId}」の利用者が見つかりません。";
                continue;
            }

            // テナント分離: この施設に属する児童のみ対象
            $child = $originalChildren->get((int) $childId);
            if (!$child) {
                $errors[] = "{$lineNo}行目: 児童ID「{$childId}」がこの事業所に見つかりません。";
                continue;
            }

            if (isset($seenChildren[$child->id]) || isset($seenUsers[$resolvedYoyakuId])) {
                $errors[] = "{$lineNo}行目: CSV内で児童または予約アカウントが重複しています。";
                continue;
            }
            $seenChildren[$child->id] = true;
            $seenUsers[$resolvedYoyakuId] = true;
            $updates[] = ['child_id' => $child->id, 'previous' => $child->yoyaku_user_id, 'yoyaku_user_id' => $resolvedYoyakuId];
        }

        if (!empty($errors)) {
            return back()->with([
                'message'       => 'エラーが見つかったため、紐付けは行われませんでした。',
                'status'        => 'danger',
                'import_errors' => $errors,
            ]);
        }

        try {
            DB::transaction(function () use ($updates, $facility) {
                $currentFacility = YoyakuLinkGuard::lockFacility($facility->id);
                YoyakuLinkGuard::assertConfiguration($currentFacility, $facility);
                // Validate the entire batch before changing any child.
                $children = [];
                foreach ($updates as $u) {
                    $child = Child::where('facility_id', $facility->id)->whereKey($u['child_id'])->lockForUpdate()->first();
                    if (!$child || (string) $child->yoyaku_user_id !== (string) $u['previous']) {
                        throw ValidationException::withMessages(['csv_file' => '児童の紐付けが別の操作で変更されています。再確認してください。']);
                    }
                    YoyakuLinkGuard::assertAvailable($facility->id, $child->id, $u['yoyaku_user_id']);
                    $children[$child->id] = $child;
                }
                foreach ($updates as $u) {
                    $children[$u['child_id']]->update(['yoyaku_user_id' => $u['yoyaku_user_id']]);
                }
            });
        } catch (ValidationException $e) {
            return back()->with(['message' => 'エラーが見つかったため、紐付けは行われませんでした。',
                'status' => 'danger', 'import_errors' => $e->validator->errors()->all()]);
        }

        return to_route('children.yoyaku-link')
            ->with(['message' => count($updates) . '件の紐付けを更新しました。', 'status' => 'success']);
    }

    private function buildProps(Facility $facility, YoyakuApiService $api): array
    {
        $configured = (bool) $facility->yoyaku_business_id;

        $unlinkedChildren = Child::where('facility_id', $facility->id)
            ->whereNull('yoyaku_user_id')
            ->where('contract_status', '!=', 'ended')
            ->with('school:id,name')
            ->orderBy('name_kana')
            ->get(['id', 'name', 'name_kana', 'school_id']);

        $linkedCount = Child::where('facility_id', $facility->id)
            ->whereNotNull('yoyaku_user_id')
            ->count();

        $yoyakuUsersRaw = $configured
            ? $api->listUsers((int) $facility->yoyaku_business_id, $facility->id)
            : null;

        return [
            'configured'         => $configured,
            'unlinkedChildren'   => $unlinkedChildren,
            'linkedCount'        => $linkedCount,
            'yoyakuUsers'        => $yoyakuUsersRaw ?? [],
            'yoyakuApiReachable' => $configured ? ($yoyakuUsersRaw !== null) : null,
        ];
    }

    private function parseCsv(string $path): array
    {
        $raw = file_get_contents($path);

        $enc = mb_detect_encoding($raw, ['UTF-8', 'SJIS-win', 'SJIS', 'CP932', 'EUC-JP'], true) ?: 'UTF-8';
        if ($enc !== 'UTF-8') {
            $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $raw);
        rewind($stream);
        try {
            $header = fgetcsv($stream);
            if ($header === false) return [];
            $header = array_map(fn ($h) => trim($h ?? ''), $header);
            if (count($header) !== count(array_unique($header)) || !in_array('child_id', $header, true)
                || (!in_array('yoyaku_user_id', $header, true) && !in_array('yoyaku_email', $header, true))) {
                throw ValidationException::withMessages(['csv_file' => 'CSVの見出しが不足または重複しています。']);
            }
            $rows = [];
            while (($cols = fgetcsv($stream)) !== false) {
                if ($cols === [null]) continue;
                if (count($cols) !== count($header)) {
                    throw ValidationException::withMessages(['csv_file' => 'CSVの列数が見出しと一致しません。']);
                }
                $rows[] = array_combine($header, $cols);
            }
            return $rows;
        } finally {
            fclose($stream);
        }
    }
}
