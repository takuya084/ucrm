<?php

use App\Models\{BillingPeriod, Child, Facility, ServiceCodeMaster, SupportPlan};
use App\Services\Billing\{BillingCalculationService, BillingCompletenessGuard, GuardianInvoicePdfService};
use Illuminate\Support\Facades\DB;

/** Explicit maintenance for the labelled synthetic review data, never general client data. */
function repairReviewBillingSamples(int $facilityId, string $yearMonth): array
{
    $facility=Facility::findOrFail($facilityId);
    $children=Child::where('facility_id',$facilityId)->orderBy('id')->get();
    if((int)$facility->yoyaku_business_id!==77 || $children->pluck('id')->all()!==range(1001,1018)
        || $children->contains(fn($child)=>!str_starts_with($child->name,'【架空】'))){
        throw new RuntimeException('架空テストデータの事業所・児童を確認できないため、請求データは変更しません。');
    }
    $period=BillingPeriod::where('facility_id',$facilityId)->where('year_month',$yearMonth)->sole();
    $demo=ServiceCodeMaster::where('service_code','999901')->where('service_type','houday')->sole();
    if($period->isLocked() || !str_contains((string)$period->notes,'画面確認専用') || !($demo->conditions['demo_only']??false)){
        throw new RuntimeException('未確定の架空請求月を確認できないため、請求データは変更しません。');
    }
    return DB::transaction(function()use($facilityId,$yearMonth,$children,$period,$demo){
        $first=$yearMonth.'-01';
        // Keep the original plans and workflow examples. Add a clearly labelled
        // earlier plan so that attendance at the beginning of the month is covered.
        foreach($children as $child){
            if(!DB::table('usage_records')->where('facility_id',$facilityId)->where('child_id',$child->id)->where('date','like',$yearMonth.'%')->where('billing_target',true)->exists())continue;
            $exists=$child->supportPlans()->where('plan_date','<=',$first)->where(fn($q)=>$q->whereNull('valid_from')->orWhere('valid_from','<=',$first))->where(fn($q)=>$q->whereNull('valid_to')->orWhere('valid_to','>=',$first))->exists();
            if(!$exists)SupportPlan::create(['child_id'=>$child->id,'plan_date'=>$first,'valid_from'=>$first,'valid_to'=>date('Y-m-t',strtotime($first)),
                'planned_start_time'=>'14:00','planned_end_time'=>'17:00','planned_duration_minutes'=>180,
                'long_term_goal'=>'【架空・請求動作確認用】月初の支援時間を確認するサンプル','status'=>'draft']);
        }
        ServiceCodeMaster::firstOrCreate(['service_code'=>'999902','service_type'=>'jidou'],[
            'revision_date'=>$demo->revision_date,'service_name'=>'【架空・画面確認専用】児発の基本サービス（正式請求不可）',
            'unit_count'=>100,'unit_type'=>'per_day','category'=>'base','conditions'=>['demo_only'=>true],
            'valid_from'=>$demo->valid_from,'valid_to'=>$demo->valid_to]);

        $oldDetails=$period->billingDetails()->get()->keyBy('id');
        $oldIds=$oldDetails->keys()->all();$saved=[];
        foreach(['guardian_invoices','error_claims','claim_returns'] as $table){
            $saved[$table]=DB::table($table)->whereIn('billing_detail_id',$oldIds)->get()->map(fn($row)=>(array)$row)->all();
        }
        // Error claims have a restrictive FK. Retain the rows in memory while
        // recalculation replaces their synthetic source details, then relink them.
        DB::table('error_claims')->whereIn('billing_detail_id',$oldIds)->delete();
        $fresh=app(BillingCalculationService::class)->calculateMonthlyBilling($facilityId,$yearMonth);
        $newDetails=$fresh->billingDetails()->get()->keyBy(fn($d)=>$d->child_id.':'.$d->service_type);
        foreach($saved as $table=>$rows){
            foreach($rows as $row){
                $old=$oldDetails[$row['billing_detail_id']];
                $detail=$newDetails->get($old->child_id.':'.$old->service_type);
                if(!$detail)throw new RuntimeException('架空明細の再計算結果を確認できません。');
                $row['billing_detail_id']=$detail->id;
                if($table==='guardian_invoices'){
                    $paid=$row['paid_amount'];
                    if($paid===null && $row['payment_status']==='paid')$paid=(int)$row['total_amount'];
                    $row['copayment_amount']=(int)$detail->copayment_cap_applied;
                    $row['total_amount']=$row['copayment_amount']+(int)$row['other_charges'];
                    $row['paid_amount']=$paid;
                    if((int)$paid>0)$row['payment_status']=(int)$paid>=$row['total_amount']?'paid':'partial';
                    elseif(!in_array($row['payment_status'],['unpaid','overdue'],true))$row['payment_status']='unpaid';
                    $row['pdf_path']=null;
                }
                // claim_returns are retained with their original ID by nullOnDelete.
                if($table==='claim_returns')DB::table($table)->where('id',$row['id'])->update($row);
                else DB::table($table)->insert($row);
            }
        }
        $invoices=app(GuardianInvoicePdfService::class)->createInvoicesFromBillingPeriod($fresh);
        app(BillingCompletenessGuard::class)->assertPeriod($fresh);
        foreach($invoices as $invoice)app(BillingCompletenessGuard::class)->assertInvoice($invoice);
        return ['month'=>$yearMonth,'details'=>$fresh->billingDetails()->count(),'invoices'=>count($invoices),'synthetic_rates_only'=>true];
    });
}
