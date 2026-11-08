<?php

namespace Tests\Feature;

use App\Models\{Child, ContactNote, Facility, Staff, SupportRecord, UsageRecord, User};
use App\Services\ContactNoteStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DailyWorkTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_work_is_scoped_to_date_and_facility_and_excludes_cancelled_records(): void
    {
        Queue::fake();
        $facility = Facility::create(['name'=>'事業所A','facility_code'=>'1310000001','service_type'=>'houday','capacity_per_day'=>10,'area_unit_price'=>10]);
        $other = Facility::create(['name'=>'事業所B','facility_code'=>'1310000002','service_type'=>'houday','capacity_per_day'=>10,'area_unit_price'=>10]);
        $user = User::factory()->create();
        $staff = Staff::create(['user_id'=>$user->id,'facility_id'=>$facility->id,'name'=>'職員A','role'=>'staff','is_active'=>true]);
        $this->actingAs($user);
        $children = collect();
        foreach ([$facility, $facility, $other] as $i => $f) {
            $child = Child::create(['facility_id'=>$f->id,'name'=>'児童'.$i,'contract_status'=>'active']);
            $children->push($child);
            UsageRecord::create(['child_id'=>$child->id,'facility_id'=>$f->id,'date'=>'2026-12-01','status'=>$i===1?'cancel':'attended']);
            ContactNote::create(['facility_id'=>$f->id,'child_id'=>$child->id,'date'=>'2026-12-01','guardian_submitted_at'=>now(),'guardian_comment'=>'家庭の連絡']);
        }
        UsageRecord::create(['child_id'=>$children[0]->id,'facility_id'=>$facility->id,'date'=>'2026-12-02','status'=>'attended']);
        $this->get('/dashboard?date=2026-12-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->where('date','2026-12-01')->has('dailyWork.rows',2)
            ->where('dailyWork.counts.home',2)->where('dailyWork.counts.record',1)->where('dailyWork.counts.draft',0)
            ->where('dailyWork.rows.0.child_id',$children[0]->id)
            ->where('dailyWork.rows.1.record','対象外')->where('dailyWork.rows.1.record_url',null)
            ->where('billingPeriod',null));
        $this->get('/dashboard?date=2026-12-02')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('dailyWork.rows',1)->where('dailyWork.counts.home',0));
        $this->get('/dashboard?date=bad-date')->assertSessionHasErrors('date');
        $this->get('/contact-notes?date=bad-date')->assertSessionHasErrors('date');
    }

    public function test_record_saved_without_contact_note_is_editable_from_daily_work(): void
    {
        Queue::fake();
        $facility = Facility::create(['name'=>'事業所A','facility_code'=>'1310000001','service_type'=>'houday','capacity_per_day'=>10,'area_unit_price'=>10]);
        $user = User::factory()->create();
        $staff = Staff::create(['user_id'=>$user->id,'facility_id'=>$facility->id,'name'=>'職員A','role'=>'staff','is_active'=>true]);
        $child = Child::create(['facility_id'=>$facility->id,'name'=>'児童A','contract_status'=>'active']);
        $usage = UsageRecord::create(['child_id'=>$child->id,'facility_id'=>$facility->id,'date'=>'2026-12-01','status'=>'attended']);
        $record = SupportRecord::create(['child_id'=>$child->id,'usage_record_id'=>$usage->id,'staff_id'=>$staff->id,'date'=>'2026-12-01','condition'=>'normal']);
        $this->actingAs($user)->get('/dashboard?date=2026-12-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dailyWork.counts.record',0)->where('dailyWork.counts.report',1)->where('dailyWork.rows.0.record_url',route('support-records.edit',$record)));
        $this->get('/contact-notes?date=2026-12-01')->assertOk()->assertInertia(fn (Assert $page) => $page->where('rows.0.support_record_id',$record->id));
    }

    public function test_status_never_treats_old_delivery_or_old_confirmation_as_current(): void
    {
        $note = new ContactNote(['status'=>'published','guardian_message'=>'新しい公開内容']);
        $note->forceFill(['updated_at'=>'2026-12-01 10:00:00','yoyaku_synced_at'=>'2026-12-01 09:00:00',
            'publication_hash'=>str_repeat('a',64),'guardian_confirmed_hash'=>str_repeat('b',64),'guardian_confirmed_at'=>'2026-12-01 09:00:00',
            'guardian_submitted_at'=>'2026-12-01 08:00:00','guardian_source_revision'=>2,'guardian_checked_revision'=>1,'guardian_checked_at'=>'2026-12-01 08:00:00']);
        $state = ContactNoteStatus::for($note);
        $this->assertTrue($state['homePending']);
        $this->assertSame('連携待ち・要確認',$state['delivery']);
        $this->assertSame('保護者の確認印待ち',$state['confirmation']);
        $note->yoyaku_synced_at='2026-12-01 11:00:00';
        $note->guardian_confirmed_hash=$note->publication_hash;
        $this->assertSame('連携完了',ContactNoteStatus::for($note)['delivery']);
        $this->assertSame('保護者確認印済み',ContactNoteStatus::for($note)['confirmation']);
    }
}
