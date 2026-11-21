<?php
namespace App\Services;

/** Date checks are assistance, never a substitute for the consultation or a claim review. */
class AbsenceRules
{
    public function assess(array $record, string $date, ?array $calendar, int $otherConfirmed = 0, bool $reusedConsultation = false): array
    {
        $missing = []; $blocked = []; $review = [];
        $day = new \DateTimeImmutable($date, new \DateTimeZone('Asia/Tokyo'));
        $contact = empty($record['contacted_at']) ? null : new \DateTimeImmutable($record['contacted_at'], new \DateTimeZone('Asia/Tokyo'));
        $earliest = null; $timing = '未確認';
        if (!$calendar || !($calendar['confirmed'] ?? false)) $review[] = '営業日の設定・確認が必要です';
        else {
            $isOpen = fn ($d) => in_array((int) $d->format('N'), $calendar['weekdays'], true) && !in_array($d->format('Y-m-d'), $calendar['closed_dates'], true);
            if (!$isOpen($day)) $blocked[] = '利用予定日が休業日です';
            $cursor = $day; $count = 0;
            for ($i = 0; $i < 370 && $count < 2; $i++) { $cursor = $cursor->modify('-1 day'); if ($isOpen($cursor)) $count++; }
            if ($count < 2) $review[] = '前2営業日を確認できません';
            else {
                $earliest = $cursor->format('Y-m-d');
                if ($contact) {
                    $received = $contact->format('Y-m-d');
                    $timing = $received < $earliest ? '対象期間より前' : ($received > $date ? '欠席日の翌日以降' : '前2営業日〜当日');
                    if ($received < $earliest || $received > $date) $blocked[] = '欠席連絡が前2営業日〜当日の範囲外です';
                }
            }
        }
        foreach (['contacted_at'=>'連絡日時','reason'=>'欠席理由','contact_method'=>'連絡手段','caller'=>'連絡者','receiver_staff_id'=>'受付職員','consultant_staff_id'=>'相談援助を行った職員','consultant_role'=>'相談職員の職名','child_condition'=>'児童の状況','support_content'=>'相談援助の内容','next_date'=>'次回利用予定日'] as $key=>$label) {
            if (empty($record[$key])) $missing[] = $label;
        }
        foreach (['was_planned'=>'事前の利用予定','sudden'=>'急病等の予期しない欠席','service_available'=>'受入体制','consultation_done'=>'相談援助の実施'] as $key=>$label) {
            if (!array_key_exists($key, $record) || $record[$key] === null) $missing[] = $label.'の確認';
            elseif (!$record[$key]) $blocked[] = $label.'の条件を満たしません';
        }
        if ($reusedConsultation) $blocked[] = '同じ相談日時の記録が別の利用日にあります。1回の相談を複数日分に算定できません';
        if ($otherConfirmed >= 4) $review[] = '通常の月4回に到達しています。重症心身障害児の特例等は別途確認してください';
        if ($record['special_case'] ?? false) $review[] = '特例の適用は管理者が要件を個別確認してください';
        $status = $blocked ? '対象外' : ($missing ? '記録不足' : ($review ? '要確認' : '算定候補'));
        if (array_key_exists('is_absent', $record) && !$record['is_absent']) $status = '欠席取消';
        return ['status'=>$status,'timing'=>$timing,'earliest_contact_date'=>$earliest,'missing'=>$missing,'reasons'=>array_merge($blocked,$review),
            'other_confirmed'=>$otherConfirmed,'ordinary_limit'=>4,'eligible_candidate'=>$status === '算定候補'];
    }
}
