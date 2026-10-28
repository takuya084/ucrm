<?php
namespace Tests\Feature;

use App\Models\Child;
use App\Models\Facility;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityAccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(bool $active = true): array
    {
        $a=Facility::create(['name'=>'Fictional A','capacity_per_day'=>10,'billing_type'=>'free','is_active'=>true]);
        $b=Facility::create(['name'=>'Fictional B','capacity_per_day'=>10,'billing_type'=>'free','is_active'=>true]);
        $user=User::factory()->create();
        $staff=Staff::create(['user_id'=>$user->id,'facility_id'=>$a->id,'name'=>'Fictional staff','role'=>'admin','is_active'=>$active]);
        $own=School::create(['facility_id'=>$a->id,'name'=>'Own school']);
        $other=School::create(['facility_id'=>$b->id,'name'=>'Other school']);
        return [$user,$staff,$a,$b,$own,$other];
    }

    public function test_other_facility_school_is_rejected_on_creation(): void
    {
        [$u,$staff,$a,$b,$own,$other]=$this->fixtures();
        $this->actingAs($u)->postJson('/children',['name'=>'Fictional child','contract_status'=>'active','school_id'=>$other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('school_id');
        $this->assertDatabaseCount('children',0);
    }

    public function test_other_facility_school_is_rejected_on_update(): void
    {
        [$u,$staff,$a,$b,$own,$other]=$this->fixtures();
        $child=Child::create(['facility_id'=>$a->id,'name'=>'Fictional child','contract_status'=>'active','school_id'=>$own->id]);
        $this->actingAs($u)->patchJson('/children/'.$child->id,['name'=>'Changed','contract_status'=>'active','school_id'=>$other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('school_id');
        $this->assertSame($own->id,$child->fresh()->school_id);
        $this->assertSame('Fictional child',$child->fresh()->name);
    }

    public function test_active_staff_can_use_own_school_without_forging_facility(): void
    {
        [$u,$staff,$a,$b,$own]=$this->fixtures();
        $this->actingAs($u)->post('/children',['name'=>'Fictional child','contract_status'=>'active','school_id'=>$own->id,'facility_id'=>$b->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('children',['facility_id'=>$a->id,'school_id'=>$own->id]);
    }

    public function test_inactive_staff_cannot_read_child_data_in_a_free_facility(): void
    {
        [$u,$staff,$a]=$this->fixtures(false);
        $child=Child::create(['facility_id'=>$a->id,'name'=>'Private fixture child','contract_status'=>'active']);
        $this->actingAs($u)->getJson('/children')->assertForbidden();
        $this->getJson('/children/'.$child->id)->assertForbidden();
    }

    public function test_no_staff_membership_is_denied_before_business_pages(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/children')->assertForbidden();
    }

    public function test_other_facility_child_cannot_be_read_or_updated(): void
    {
        [$u,$staff,$a,$b]=$this->fixtures();
        $child=Child::create(['facility_id'=>$b->id,'name'=>'Other private child','contract_status'=>'active']);
        $this->actingAs($u)->getJson('/children/'.$child->id)->assertForbidden();
        $this->patchJson('/children/'.$child->id,['name'=>'Changed','contract_status'=>'active'])->assertForbidden();
        $this->assertSame('Other private child',$child->fresh()->name);
    }

    public function test_current_staff_disablement_is_rechecked_on_the_next_request(): void
    {
        [$u,$staff]=$this->fixtures();
        $this->actingAs($u)->get('/children')->assertOk();
        $staff->update(['is_active'=>false]);
        $this->getJson('/children')->assertForbidden();
        $this->postJson('/support-records',['date'=>'2026-07-01'])->assertForbidden();
        $this->assertDatabaseCount('support_records',0);
    }

    public function test_own_school_can_be_updated_and_cleared(): void
    {
        [$u,$staff,$a,$b,$own]=$this->fixtures();
        $child=Child::create(['facility_id'=>$a->id,'name'=>'Fictional child','contract_status'=>'active']);
        $this->actingAs($u)->patch('/children/'.$child->id,['name'=>'Fictional child','contract_status'=>'active','school_id'=>$own->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($own->id,$child->fresh()->school_id);
        $this->patch('/children/'.$child->id,['name'=>'Fictional child','contract_status'=>'active','school_id'=>null])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($child->fresh()->school_id);
    }

    public function test_inactive_staff_can_still_use_own_account_security(): void
    {
        [$u]=$this->fixtures(false);
        $this->actingAs($u)->withSession(['auth.password_confirmed_at'=>time()])
            ->get(route('account.security'))->assertOk();
    }
}

