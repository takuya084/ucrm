<?php

namespace Tests\Feature;

use App\Models\{Child, Facility, UsageRecord};
use App\Jobs\PushYoyakuBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class YoyakuEnvelopeRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'synthetic-contract-secret';
    private Child $child;
    private array $envelope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-28T12:00:00+00:00'));
        Bus::fake();
        $facility=Facility::create(['name'=>'架空連携A','capacity_per_day'=>10,
            'yoyaku_business_id'=>77,'yoyaku_webhook_secret'=>self::SECRET]);
        $this->child=Child::create(['facility_id'=>$facility->id,'name'=>'架空児童A',
            'contract_status'=>'active','yoyaku_user_id'=>501]);
        $this->envelope=json_decode(file_get_contents(base_path('tests/fixtures/integration/booking.json')),true,512,JSON_THROW_ON_ERROR);
    }

    private function send(array $body, string $secret=self::SECRET)
    {
        $json=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        return $this->call('POST','/api/webhooks/yoyaku',server:[
            'CONTENT_TYPE'=>'application/json','HTTP_X_YOYAKU_SIGNATURE'=>hash_hmac('sha256',$json,$secret),
        ],content:$json);
    }

    public function test_sender_contract_is_accepted_and_duplicate_delivery_does_not_duplicate_rows(): void
    {
        $this->send($this->envelope)->assertOk();
        $id=UsageRecord::sole()->id;
        $this->send($this->envelope)->assertOk();
        $this->assertDatabaseCount('usage_records',1);
        $this->assertSame($id,UsageRecord::sole()->id);
        $this->assertSame($this->child->id,UsageRecord::sole()->child_id);
        Bus::assertNotDispatched(PushYoyakuBooking::class);
    }

    public function test_existing_attendance_status_is_preserved_and_soft_deleted_row_is_restored(): void
    {
        $record=UsageRecord::create(['facility_id'=>$this->child->facility_id,'child_id'=>$this->child->id,
            'date'=>'2026-11-04','status'=>'absent_notice']);
        $record->delete();
        Bus::fake();
        $this->send($this->envelope)->assertOk();
        $this->assertDatabaseCount('usage_records',1);
        $this->assertSame($record->id,UsageRecord::sole()->id);
        $this->assertSame('absent_notice',UsageRecord::sole()->status);
        $this->assertFalse(UsageRecord::sole()->trashed());
        Bus::assertNotDispatched(PushYoyakuBooking::class);
    }

    public function test_missing_empty_relative_and_impossible_timestamps_are_rejected(): void
    {
        foreach ([null,'','now','tomorrow','2026-02-30T12:00:00+00:00',[],123] as $value) {
            $body=$this->envelope; $body['sent_at']=$value;
            $this->send($body)->assertStatus(401);
        }
        $body=$this->envelope; unset($body['sent_at']);
        $this->send($body)->assertStatus(401);
        $this->assertDatabaseCount('usage_records',0);
    }

    public function test_future_and_old_timestamps_are_rejected_but_valid_offsets_are_accepted(): void
    {
        foreach (['2026-10-28T12:11:00+00:00','2026-10-28T11:49:00+00:00'] as $value) {
            $body=$this->envelope; $body['sent_at']=$value;
            $this->send($body)->assertStatus(401);
        }
        $body=$this->envelope; $body['sent_at']='2026-10-28T21:00:00+09:00';
        $this->send($body)->assertOk();
        $this->assertDatabaseCount('usage_records',1);
    }

    public function test_unknown_events_never_become_booking_updates(): void
    {
        foreach (['booking.typo','unexpected.event','contact_note.typo'] as $event) {
            $body=$this->envelope; $body['event']=$event;
            $this->send($body)->assertStatus(400);
        }
        $this->assertDatabaseCount('usage_records',0);
    }

    public function test_impossible_calendar_dates_are_rejected(): void
    {
        foreach (['2026-02-30','2026-04-31','2026-00-01','0000-01-01'] as $date) {
            $body=$this->envelope; $body['data']['date']=$date;
            $this->send($body)->assertStatus(400);
        }
        $this->assertDatabaseCount('usage_records',0);
    }

    public function test_malformed_payload_shapes_and_identifiers_are_rejected_without_server_errors(): void
    {
        foreach ([[],['event'=>[]],['event'=>'booking.created','business_id'=>[]],
            array_replace($this->envelope,['data'=>'not-an-object']),
            array_replace($this->envelope,['data'=>['user_id'=>[],'date'=>'2026-11-04']]),
            array_replace($this->envelope,['business_id'=>'77junk'])] as $body) {
            $this->send($body)->assertStatus(400);
        }
        $this->assertDatabaseCount('usage_records',0);
    }

    public function test_invalid_nested_values_are_rejected_before_writing(): void
    {
        foreach ([['pickup_time'=>[]],['dropoff_time'=>'25:00'],['actual_pickup_at'=>'now'],
            ['guardian_comment'=>['unexpected']]] as $invalid) {
            $body=$this->envelope; $body['data']=array_replace($body['data'],$invalid);
            $this->send($body)->assertStatus(400);
        }
        $this->assertDatabaseCount('usage_records',0);
    }

    public function test_business_signature_and_child_mapping_cannot_cross_facilities(): void
    {
        $other=Facility::create(['name'=>'架空連携B','capacity_per_day'=>10,
            'yoyaku_business_id'=>88,'yoyaku_webhook_secret'=>'other-secret']);
        Child::create(['facility_id'=>$other->id,'name'=>'架空児童B','contract_status'=>'active','yoyaku_user_id'=>502]);
        $body=$this->envelope; $body['business_id']=88;
        $this->send($body)->assertStatus(401);
        $body=$this->envelope; $body['data']['user_id']=502;
        $this->send($body)->assertOk()->assertJson(['ignored'=>'child not linked']);
        $this->assertDatabaseCount('usage_records',0);
    }
}
