<?php
namespace Tests\Feature;
use App\Models\{Child,Facility,RecipientCertificate,SupportPlan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PrivacyRelationsTest extends TestCase
{
    use RefreshDatabase;
    public function test_latest_relations_use_logical_dates_for_lazy_and_eager_loading(): void {
        $facility=Facility::create(['name'=>'非公開事業所','facility_code'=>'1310000001','service_type'=>'houday','area_unit_price'=>10,'capacity_per_day'=>10]);
        $child=Child::create(['facility_id'=>$facility->id,'name'=>'非公開児童','contract_status'=>'active']);
        $new=RecipientCertificate::create(['child_id'=>$child->id,'valid_from'=>'2026-10-01','status'=>'active']);
        RecipientCertificate::create(['child_id'=>$child->id,'valid_from'=>'2025-01-01','status'=>'active']);
        $plan=SupportPlan::create(['child_id'=>$child->id,'plan_date'=>'2026-10-01']);
        SupportPlan::create(['child_id'=>$child->id,'plan_date'=>'2025-01-01']);
        $this->assertSame($new->id,$child->fresh()->activeRecipientCertificate->id);
        $this->assertSame($plan->id,$child->fresh()->latestSupportPlan->id);
        $loaded=Child::with(['activeRecipientCertificate','latestSupportPlan'])->findOrFail($child->id);
        $this->assertSame($new->id,$loaded->activeRecipientCertificate->id);$this->assertSame($plan->id,$loaded->latestSupportPlan->id);
    }
}
