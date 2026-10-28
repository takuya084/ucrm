<?php

namespace Tests\Feature;

use App\Models\{Child, Facility, Staff, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyBrowserRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function facility(): Facility
    {
        return Facility::create(['name'=>'架空事業所','capacity_per_day'=>10,'billing_type'=>'free','is_active'=>true]);
    }

    private function staff(Facility $facility, string $role, string $name): User
    {
        $user=User::factory()->create(['name'=>$name]);
        Staff::create(['user_id'=>$user->id,'facility_id'=>$facility->id,'name'=>$name,'role'=>$role,'is_active'=>true]);
        return $user;
    }

    public function test_child_calendar_dates_reach_edit_page_without_utc_day_shift(): void
    {
        $facility=$this->facility();
        $admin=$this->staff($facility,'admin','架空管理者');
        $child=Child::create(['facility_id'=>$facility->id,'name'=>'架空児童','contract_status'=>'active',
            'birthdate'=>'2016-02-29','contract_start_date'=>'2026-04-01','contract_end_date'=>'2027-03-31']);
        $this->actingAs($admin)->get('/children/'.$child->id.'/edit',['X-Inertia'=>'true'])
            ->assertOk()->assertJsonPath('props.child.birthdate','2016-02-29')
            ->assertJsonPath('props.child.contract_start_date','2026-04-01')
            ->assertJsonPath('props.child.contract_end_date','2027-03-31');
        $this->assertSame('Asia/Tokyo',config('app.timezone'));
        $stored=\Illuminate\Support\Facades\DB::table('children')->where('id',$child->id)->first();
        $this->assertSame('2016-02-29',substr($stored->birthdate,0,10));
        $this->assertSame('2026-04-01',substr($stored->contract_start_date,0,10));
        $child->update(['birthdate'=>null,'contract_end_date'=>null]);
        $this->assertNull($child->fresh()->toArray()['birthdate']);
        $this->assertNull($child->fresh()->toArray()['contract_end_date']);
    }

    public function test_staff_list_orders_roles_and_names_without_cross_facility_rows(): void
    {
        $facility=$this->facility();
        $this->staff($facility,'staff','職員');
        $this->staff($facility,'leader','児発管');
        $admin=$this->staff($facility,'admin','A管理者');
        $this->staff($facility,'admin','B管理者');
        $this->staff($this->facility(),'admin','別事業所');
        $this->actingAs($admin)->get('/staff',['X-Inertia'=>'true'])->assertOk()
            ->assertJsonCount(4,'props.staffMembers')
            ->assertJsonPath('props.staffMembers.0.name','A管理者')
            ->assertJsonPath('props.staffMembers.1.name','B管理者')
            ->assertJsonPath('props.staffMembers.2.role','leader')
            ->assertJsonPath('props.staffMembers.3.role','staff');
    }
}
