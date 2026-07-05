<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Facility;
use App\Services\YoyakuApiService;
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
            'child_ids.*' => ['integer'],
        ]);

        $children = Child::where('facility_id', $facility->id)
            ->whereIn('id', $request->child_ids)
            ->whereNull('yoyaku_user_id')
            ->with('school:id,name')
            ->get();

        $results = [];
        foreach ($children as $child) {
            $res = $api->createUser([
                'name'         => $child->name,
                'school_name'  => $child->school?->name,
                'external_ref' => "ucrm:{$child->id}",
            ], $facility->id);

            if (!$res || empty($res['id'])) {
                $results[] = [
                    'child_id'   => $child->id,
                    'child_name' => $child->name,
                    'ok'         => false,
                ];
                continue;
            }

            $child->update(['yoyaku_user_id' => $res['id']]);

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
            $message .= "（{$ngCount}件は p-yoyaku 側との通信に失敗し未作成です。時間を置いて再度お試しください）";
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

        $yoyakuUsers = $api->listUsers((int) $facility->yoyaku_business_id, $facility->id);
        if ($yoyakuUsers === null) {
            return back()->with(['message' => 'p-yoyaku側との通信に失敗しました。時間を置いて再度お試しください。', 'status' => 'danger']);
        }
        $validYoyakuIds = collect($yoyakuUsers)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $emailToId = collect($yoyakuUsers)->pluck('id', 'email')->all();

        $errors = [];
        $updates = [];

        foreach ($rows as $i => $row) {
            $lineNo = $i + 2;
            $childId       = trim($row['child_id'] ?? '');
            $yoyakuUserId  = trim($row['yoyaku_user_id'] ?? '');
            $yoyakuEmail   = trim($row['yoyaku_email'] ?? '');

            if ($childId === '' || !ctype_digit($childId)) {
                $errors[] = "{$lineNo}行目: child_id が不正です。";
                continue;
            }

            $resolvedYoyakuId = null;
            if ($yoyakuUserId !== '' && ctype_digit($yoyakuUserId)) {
                $resolvedYoyakuId = (int) $yoyakuUserId;
            } elseif ($yoyakuEmail !== '' && isset($emailToId[$yoyakuEmail])) {
                $resolvedYoyakuId = (int) $emailToId[$yoyakuEmail];
            }

            if (!$resolvedYoyakuId) {
                $errors[] = "{$lineNo}行目: yoyaku_user_id または yoyaku_email がp-yoyaku側の利用者と一致しません。";
                continue;
            }

            if (!in_array($resolvedYoyakuId, $validYoyakuIds, true)) {
                $errors[] = "{$lineNo}行目: p-yoyaku側にID「{$resolvedYoyakuId}」の利用者が見つかりません。";
                continue;
            }

            // テナント分離: この施設に属する児童のみ対象
            $child = Child::where('facility_id', $facility->id)->find((int) $childId);
            if (!$child) {
                $errors[] = "{$lineNo}行目: 児童ID「{$childId}」がこの事業所に見つかりません。";
                continue;
            }

            $updates[] = ['child' => $child, 'yoyaku_user_id' => $resolvedYoyakuId];
        }

        if (!empty($errors)) {
            return back()->with([
                'message'       => 'エラーが見つかったため、紐付けは行われませんでした。',
                'status'        => 'danger',
                'import_errors' => $errors,
            ]);
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $u) {
                $u['child']->update(['yoyaku_user_id' => $u['yoyaku_user_id']]);
            }
        });

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

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_filter($lines, fn ($line) => trim($line) !== '');
        $lines = array_values($lines);

        if (count($lines) < 2) {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn ($h) => trim($h), $header);

        $rows = [];
        foreach ($lines as $line) {
            $cols = str_getcsv($line);
            $row = [];
            foreach ($header as $idx => $key) {
                $row[$key] = $cols[$idx] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
