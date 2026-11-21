<?php
namespace App\Services\Billing;
use App\Models\UsageRecord;
use App\Services\AbsenceBoard;
class AbsenceBillingEligibility {
 public function allows(UsageRecord $record): bool {
  if($record->status!=='absent_notice') return false;
  $board=app(AbsenceBoard::class)->overview((int)$record->facility_id,$record->date->format('Y-m-d'));
  if(!$board['available']) return false;
  foreach($board['rows'] as $row) {
   if((int)($row['local_child_id']??0)===(int)$record->child_id) return ($row['payload']['is_absent']??true) && ($row['payload']['claim_confirmed']??false) && $row['assessment']['eligible_candidate'];
  }
  return false;
 }
}
