<?php
namespace Tests\Feature;

use App\Jobs\PushYoyakuBooking;
use App\Models\{Child, Facility, UsageRecord};
use App\Services\YoyakuApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Http};
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use RefreshDatabase;
    private Child $child;
    private Facility $facility;
    protected function setUp(): void
    {
        parent::setUp(); Bus::fake();
        $this->facility=Facility::create(['name'=>'Synthetic A','capacity_per_day'=>10,
            'yoyaku_business_id'=>77,'yoyaku_api_token'=>'synthetic-token','yoyaku_webhook_secret'=>'synthetic-secret']);
        $this->child=Child::create(['facility_id'=>$this->facility->id,'name'=>'Synthetic child',
            'contract_status'=>'active','yoyaku_user_id'=>501]);
        config(['services.houkago_plus.base_url'=>'https://p-yoyaku.example.test']);
    }
    private function record(): UsageRecord
    {
        return UsageRecord::create(['facility_id'=>$this->facility->id,'child_id'=>$this->child->id,
            'date'=>'2026-11-04','status'=>'attended','pickup_done'=>true,'check_in_time'=>'15:00:00']);
    }
    private function send(int $revision,string $event='booking.created')
    {
        $body=['event'=>$event,'business_id'=>77,'sent_at'=>now()->toIso8601String(),
            'data'=>['user_id'=>501,'date'=>'2026-11-04','pickup_time'=>'15:00','booking_revision'=>$revision]];
        $json=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        return $this->call('POST','/api/webhooks/yoyaku',server:['CONTENT_TYPE'=>'application/json',
            'HTTP_X_YOYAKU_SIGNATURE'=>hash_hmac('sha256',$json,'synthetic-secret')],content:$json);
    }
    public function test_cancelled_record_sends_explicit_cancellation_not_empty_create(): void
    {
        Http::fake(['*'=>Http::response(['ok'=>true,'applied'=>true])]);
        $r=$this->record(); $r->update(['status'=>'cancel']);
        (new PushYoyakuBooking($r->id))->handle(app(YoyakuApiService::class));
        Http::assertSent(fn($q)=>$q->method()==='PUT' && str_ends_with($q->url(),'/api/integration/bookings')
            && $q['cancelled']===true && $q['revision']===1 && $q['pickup_time']===null);
        Http::assertSentCount(1);
    }
    public function test_soft_delete_and_retry_re_read_latest_state_instead_of_old_delete_flag(): void
    {
        Http::fake(['*'=>Http::response(['ok'=>true,'applied'=>true])]);
        $r=$this->record(); $r->delete();
        $job=new PushYoyakuBooking($r->id,true);
        $job->handle(app(YoyakuApiService::class));
        $r->restore(); $job->handle(app(YoyakuApiService::class));
        $requests=Http::recorded();
        $this->assertTrue($requests[0][0]['cancelled']);
        $this->assertFalse($requests[1][0]['cancelled']);
        $this->assertSame(2,$requests[1][0]['revision']);
        $this->assertSame('15:00',$requests[1][0]['pickup_time']);
    }
    public function test_failed_delivery_is_not_reported_as_a_success_or_retried_on_legacy_endpoint(): void
    {
        Http::fake(['*'=>Http::response([],503)]);
        $r=$this->record();
        try { (new PushYoyakuBooking($r->id))->handle(app(YoyakuApiService::class)); $this->fail('Must fail visibly'); }
        catch (\RuntimeException $e) { $this->assertSame('Booking synchronization failed',$e->getMessage()); }
        Http::assertSentCount(1);
    }
    public function test_deleted_then_delayed_created_notification_does_not_restore_transport(): void
    {
        $this->send(1)->assertOk();
        $this->send(2,'booking.deleted')->assertOk();
        $this->send(1)->assertOk();
        $this->assertFalse(UsageRecord::sole()->pickup_done);
        $this->assertNull(UsageRecord::sole()->check_in_time);
        Bus::assertNotDispatched(PushYoyakuBooking::class);
    }
    public function test_delete_before_create_is_remembered_and_newer_rebooking_is_allowed(): void
    {
        $this->send(2,'booking.deleted')->assertOk();
        $this->send(1)->assertOk();
        $this->assertDatabaseCount('usage_records',0);
        $this->send(3)->assertOk();
        $this->send(2,'booking.deleted')->assertOk();
        $this->assertTrue(UsageRecord::sole()->pickup_done);
    }
    public function test_duplicate_revision_is_idempotent_but_conflicting_payload_is_rejected(): void
    {
        $this->send(1)->assertOk(); $this->send(1)->assertOk();
        $this->assertDatabaseCount('usage_records',1);
        $this->send(1,'booking.deleted')->assertStatus(409);
        $this->assertTrue(UsageRecord::sole()->pickup_done);
    }
    public function test_saving_memo_after_received_cancellation_does_not_recreate_booking(): void
    {
        Http::fake(['*'=>Http::response(['ok'=>true,'applied'=>true])]);
        $this->record();
        $this->send(1,'booking.deleted')->assertOk();
        $record=UsageRecord::sole(); $record->update(['memo'=>'Synthetic memo']);
        (new PushYoyakuBooking($record->id))->handle(app(YoyakuApiService::class));
        Http::assertSent(fn($r)=>$r['cancelled']===true);
    }
    public function test_unlinked_record_sends_nothing(): void
    {
        Http::fake(); $r=$this->record(); $this->child->update(['yoyaku_user_id'=>null]);
        (new PushYoyakuBooking($r->id))->handle(app(YoyakuApiService::class));
        Http::assertNothingSent();
    }
}
