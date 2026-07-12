<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChildRequest;
use App\Http\Requests\UpdateChildRequest;
use App\Models\Child;
use App\Models\School;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChildrenController extends Controller
{
    /** 利用児童一覧 */
    public function index(Request $request)
    {
        $facilityId = $this->facilityId();

        // latestOfMany は集計サブクエリと JOIN されるため、カラムはテーブル名で修飾する（未修飾だと ambiguous になる）
        $query = Child::with(['school', 'activeRecipientCertificate' => fn ($q) => $q->select(
                'recipient_certificates.id',
                'recipient_certificates.child_id',
                'recipient_certificates.valid_to',
                'recipient_certificates.monthly_limit',
            )])
            ->where('facility_id', $facilityId)
            ->search($request->search)
            ->when($request->status, fn ($q, $s) => $q->where('contract_status', $s))
            ->orderBy('name_kana')
            ->select('id', 'name', 'name_kana', 'gender', 'grade', 'school_id', 'contract_status', 'pickup_required');

        $children = $query->paginate(20)->withQueryString();

        return Inertia::render('Children/Index', [
            'children' => $children,
            'filters'  => $request->only(['search', 'status']),
        ]);
    }

    /** 登録フォーム */
    public function create()
    {
        return Inertia::render('Children/Create', [
            'schools' => School::where('facility_id', $this->facilityId())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** 登録処理 */
    public function store(StoreChildRequest $request)
    {
        $data = $request->safe()->except(['schedule_days', 'ai_draft_consent']);
        $data['ai_draft_consented_at'] = $request->boolean('ai_draft_consent') ? now() : null;
        $child = Child::create(array_merge($data, ['facility_id' => $this->facilityId()]));

        // 利用曜日の一括登録
        $today = now()->toDateString();
        foreach ($request->input('schedule_days', []) as $day) {
            $child->schedules()->create([
                'day_of_week' => $day,
                'start_date'  => $request->contract_start_date ?? $today,
                'status'      => 'regular',
            ]);
        }

        return to_route('children.index')
            ->with(['message' => '児童を登録しました。', 'status' => 'success']);
    }

    /** CSV一括登録フォーム（基本情報のみ。要配慮個人情報・保護者は対象外） */
    public function createBulk()
    {
        return Inertia::render('Children/BulkCreate', [
            'schools' => School::where('facility_id', $this->facilityId())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** CSVテンプレートダウンロード */
    public function bulkTemplate(): Response
    {
        $schools = School::where('facility_id', $this->facilityId())->pluck('name')->all();

        $lines = [];
        $lines[] = 'name,name_kana,gender,birthdate,grade,school_name,pickup_address,contract_start_date,contract_status';
        $lines[] = '山田太郎,ヤマダタロウ,male,2015-04-01,小3,' . ($schools[0] ?? '') . ',〇〇市△△町1-2-3,2026-04-01,active';
        $lines[] = '佐藤花子,サトウハナコ,female,2016-08-10,小2,,,,active';

        $csv = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="children_template.csv"',
        ]);
    }

    /**
     * CSV一括登録処理。
     *
     * 要配慮個人情報（障がい種別・アレルギー・配慮事項）と保護者の紐付けは対象外
     * （CSVでの誤入力・誤送信リスクを避けるため、登録後に個別画面で入力する運用）。
     * 部分成功はさせない: 1行でもエラーがあれば全件中止し、行番号付きでエラーを返す。
     */
    public function storeBulk(Request $request)
    {
        $facilityId = $this->facilityId();

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $rows = $this->parseCsv($request->file('csv_file')->getRealPath());

        if (empty($rows)) {
            return back()->with(['message' => 'CSVにデータ行がありません。', 'status' => 'danger']);
        }

        $schoolMap = School::where('facility_id', $facilityId)->pluck('id', 'name')->all();

        $errors = [];
        $records = [];

        foreach ($rows as $i => $row) {
            $lineNo = $i + 2; // ヘッダー行を考慮

            $name              = trim($row['name'] ?? '');
            $nameKana          = trim($row['name_kana'] ?? '') ?: null;
            $gender            = trim($row['gender'] ?? '') ?: null;
            $birthdate         = trim($row['birthdate'] ?? '') ?: null;
            $grade             = trim($row['grade'] ?? '') ?: null;
            $schoolName        = trim($row['school_name'] ?? '');
            $pickupAddress     = trim($row['pickup_address'] ?? '') ?: null;
            $contractStartDate = trim($row['contract_start_date'] ?? '') ?: null;
            $contractStatus    = trim($row['contract_status'] ?? '') ?: 'active';

            $validator = Validator::make(
                [
                    'name'                => $name,
                    'name_kana'           => $nameKana,
                    'gender'              => $gender,
                    'birthdate'           => $birthdate,
                    'contract_start_date' => $contractStartDate,
                    'contract_status'     => $contractStatus,
                ],
                [
                    'name'                => ['required', 'string', 'max:50'],
                    'name_kana'           => ['nullable', 'string', 'max:50', 'regex:/^[ァ-ヶー　 ]+$/u'],
                    'gender'              => ['nullable', 'in:male,female,other'],
                    'birthdate'           => ['nullable', 'date'],
                    'contract_start_date' => ['nullable', 'date'],
                    'contract_status'     => ['required', 'in:active,suspended,ended'],
                ]
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $msg) {
                    $errors[] = "{$lineNo}行目: {$msg}";
                }
                continue;
            }

            $schoolId = null;
            if ($schoolName !== '') {
                // 学校は事業所内でスコープして解決する（テナント分離。他事業所の学校IDを誤って引かないため）
                if (!isset($schoolMap[$schoolName])) {
                    $errors[] = "{$lineNo}行目: 学校「{$schoolName}」が事業所に登録されていません。";
                    continue;
                }
                $schoolId = $schoolMap[$schoolName];
            }

            $records[] = [
                'name'                => $name,
                'name_kana'           => $nameKana,
                'gender'              => $gender,
                'birthdate'           => $birthdate,
                'grade'               => $grade,
                'school_id'           => $schoolId,
                'pickup_address'      => $pickupAddress,
                'contract_start_date' => $contractStartDate,
                'contract_status'     => $contractStatus,
            ];
        }

        if (!empty($errors)) {
            return back()->with([
                'message'       => 'エラーが見つかったため、登録は行われませんでした。',
                'status'        => 'danger',
                'import_errors' => $errors,
            ]);
        }

        $createdCount = 0;
        DB::transaction(function () use ($records, $facilityId, &$createdCount) {
            foreach ($records as $rec) {
                Child::create(array_merge($rec, ['facility_id' => $facilityId]));
                $createdCount++;
            }
        });

        return to_route('children.bulk')
            ->with(['message' => "{$createdCount}件の児童を登録しました。", 'status' => 'success']);
    }

    private function parseCsv(string $path): array
    {
        $raw = file_get_contents($path);

        // Excel(Windows)からのSJIS保存にも対応
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

    /** 詳細表示 */
    public function show(Child $child)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        \App\Models\AuditLog::record('viewed', $child);
        $child->load([
            'school',
            'guardians',
            'schedules' => fn ($q) => $q->active()->orderBy('day_of_week'),
            'activeRecipientCertificate',
            'usageRecords' => fn ($q) => $q->orderByDesc('date')->limit(10)->with('supportRecord:id,usage_record_id'),
            'monitoringRecords' => fn ($q) => $q->orderByDesc('monitoring_date')->limit(10),
            'supportPlans' => fn ($q) => $q->orderByDesc('plan_date')->limit(10),
            'assessments' => fn ($q) => $q->orderByDesc('assessed_at')->limit(10)->with('staff:id,name'),
        ]);

        return Inertia::render('Children/Show', [
            'child' => $child,
        ]);
    }

    /** 編集フォーム */
    public function edit(Child $child)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        return Inertia::render('Children/Edit', [
            'child'   => $child->load('school'),
            'schools' => School::where('facility_id', $this->facilityId())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** 更新処理 */
    public function update(UpdateChildRequest $request, Child $child)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        $data = $request->safe()->except('ai_draft_consent');
        // 同意済みの日時は維持し、同意の取り下げは null に戻す
        $data['ai_draft_consented_at'] = $request->boolean('ai_draft_consent')
            ? ($child->ai_draft_consented_at ?? now())
            : null;
        $child->update($data);

        return to_route('children.show', $child)
            ->with(['message' => '情報を更新しました。', 'status' => 'success']);
    }

    /** 削除（ソフトデリート） */
    public function destroy(Child $child)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        $child->delete();

        return to_route('children.index')
            ->with(['message' => '児童を削除しました。', 'status' => 'success']);
    }
}
