<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\FacilityServiceSetting;
use App\Models\ServiceCodeMaster;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FacilityServiceSettingController extends Controller
{
    /** 加算・減算設定一覧 */
    public function index()
    {
        $facilityId = $this->facilityId();

        // 現在有効なサービスコード（加算・減算のみ）
        $today = now()->format('Y-m');
        $codes = ServiceCodeMaster::validAt($today)
            ->whereIn('category', ['addition', 'subtraction'])
            ->orderBy('service_type')
            ->orderBy('service_code')
            ->get(['id', 'service_type', 'service_code', 'service_name', 'unit_count', 'unit_type', 'category']);

        // 事業所の既存設定
        $settings = FacilityServiceSetting::where('facility_id', $facilityId)
            ->get()
            ->keyBy('service_code_master_id');

        // コードごとに enabled 状態をマージ
        $items = $codes->map(function ($code) use ($settings) {
            $setting = $settings->get($code->id);
            return [
                'id'                    => $code->id,
                'service_type'          => $code->service_type,
                'service_code'          => $code->service_code,
                'service_name'          => $code->service_name,
                'unit_count'            => $code->unit_count,
                'unit_type'             => $code->unit_type,
                'category'              => $code->category,
                'is_enabled'            => $setting?->is_enabled ?? false,
                'setting_id'            => $setting?->id,
            ];
        });

        return Inertia::render('Billing/Settings/ServiceCodes', [
            'items' => $items->values(),
        ]);
    }

    /** 一括更新 */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'settings'                          => ['required', 'array'],
            'settings.*.service_code_master_id' => ['required', 'exists:service_code_masters,id'],
            'settings.*.is_enabled'             => ['required', 'boolean'],
        ]);

        $facilityId = $this->facilityId();
        $today = now()->toDateString();

        foreach ($request->settings as $item) {
            FacilityServiceSetting::updateOrCreate(
                [
                    'facility_id'           => $facilityId,
                    'service_code_master_id' => $item['service_code_master_id'],
                ],
                [
                    'is_enabled'     => $item['is_enabled'],
                    'effective_from' => $today,
                    'effective_to'   => null,
                ]
            );
        }

        return back()->with(['message' => '加算・減算設定を更新しました。', 'status' => 'success']);
    }

    /**
     * サービスコードマスターCSV一括取込
     *
     * CSVフォーマット（ヘッダ行必須、UTF-8/SJIS自動判定）:
     *   報酬改定日, サービス種別, サービスコード, サービス内容名, 単位数, 単位種別, 区分, 有効開始日, 有効終了日
     *   例: 2024-04-01,houday,631111,児童発達支援(基本),604,per_day,base,2024-04-01,
     */
    public function importMaster(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $raw = file_get_contents($request->file('file')->getRealPath());
        $enc = mb_detect_encoding($raw, ['UTF-8', 'SJIS-win', 'SJIS', 'CP932', 'EUC-JP'], true) ?: 'UTF-8';
        if ($enc !== 'UTF-8') $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        if (count($lines) <= 1) {
            return back()->with(['message' => 'データ行がありません', 'status' => 'warning']);
        }
        array_shift($lines);

        $imported = 0; $updated = 0; $skipped = 0;
        $errors = [];

        $typeMap = ['63' => 'houday', '61' => 'jidou', 'houday' => 'houday', 'jidou' => 'jidou'];
        $catMap  = ['1' => 'base', '2' => 'addition', '3' => 'subtraction', 'base' => 'base', 'addition' => 'addition', 'subtraction' => 'subtraction'];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') continue;
            $lineNo = $i + 2;
            $c = str_getcsv($line);
            if (count($c) < 8) { $errors[] = "行{$lineNo}: 列数不足"; $skipped++; continue; }

            $c = array_map('trim', $c);
            [$revDate, $typeRaw, $code, $name, $units, $unitType, $catRaw, $validFrom] = array_slice($c, 0, 8);
            $validTo = $c[8] ?? null;

            $type = $typeMap[$typeRaw] ?? null;
            $cat  = $catMap[$catRaw] ?? null;
            if (!$type) { $errors[] = "行{$lineNo}: サービス種別不正 ({$typeRaw})"; $skipped++; continue; }
            if (!$cat)  { $errors[] = "行{$lineNo}: 区分不正 ({$catRaw})"; $skipped++; continue; }
            if (!$code) { $errors[] = "行{$lineNo}: サービスコード空"; $skipped++; continue; }

            $revDate   = $this->normDate($revDate);
            $validFrom = $this->normDate($validFrom);
            $validTo   = $validTo ? $this->normDate($validTo) : null;
            if (!$revDate || !$validFrom) { $errors[] = "行{$lineNo}: 日付形式不正"; $skipped++; continue; }

            $attrs = [
                'service_code' => $code,
                'valid_from'   => $validFrom,
            ];
            $values = [
                'revision_date' => $revDate,
                'service_type'  => $type,
                'service_name'  => $name,
                'unit_count'    => (int) preg_replace('/[^0-9-]/', '', $units),
                'unit_type'     => $unitType ?: 'per_day',
                'category'      => $cat,
                'valid_to'      => $validTo ?: null,
            ];

            $existing = ServiceCodeMaster::where($attrs)->first();
            if ($existing) {
                $existing->update($values);
                $updated++;
            } else {
                ServiceCodeMaster::create($attrs + $values);
                $imported++;
            }
        }

        $msg = "マスター取込: 新規{$imported}件 / 更新{$updated}件 / スキップ{$skipped}件";
        if (!empty($errors)) $msg .= ' / エラー ' . count($errors) . '件';

        return back()->with([
            'message'       => $msg,
            'status'        => 'success',
            'import_errors' => $errors,
        ]);
    }

    private function normDate(?string $s): ?string
    {
        if (!$s) return null;
        if (preg_match('/^(\d{4})[-\/]?(\d{2})[-\/]?(\d{2})$/', $s, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        return null;
    }
}
