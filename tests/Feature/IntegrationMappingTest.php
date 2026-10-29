<?php
namespace Tests\Feature;
use App\Models\{Facility,Child,UsageRecord};
use App\Services\YoyakuApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Bus};
use Tests\TestCase;

class IntegrationMappingTest extends TestCase
{
    use RefreshDatabase;
    private function facility(array $extra=[]): Facility
    {
        return Facility::create($extra+['name'=>'Synthetic facility','capacity_per_day'=>10,'yoyaku_business_id'=>77,'yoyaku_webhook_secret'=>'synthetic-secret']);
    }
    private function notify(string $event = 'booking.created', array $payload = [], string $secret = 'synthetic-secret')
    {
        $json=json_encode(['event'=>$event,'business_id'=>77,'sent_at'=>now()->toIso8601String(),
            'data'=>$payload+['user_id'=>501,'date'=>'2026-11-04','pickup_time'=>'15:00','booking_revision'=>1]],JSON_THROW_ON_ERROR);
        return $this->call('POST','/api/webhooks/yoyaku',server:['CONTENT_TYPE'=>'application/json',
            'HTTP_X_YOYAKU_SIGNATURE'=>hash_hmac('sha256',$json,$secret)],content:$json);
    }
    public function test_missing_facility_token_never_uses_global_token(): void
    {
        Http::fake(['*'=>Http::response([])]);
        config(['services.houkago_plus.base_url'=>'https://synthetic.example.test','services.houkago_plus.api_token'=>'global-token']);
        $f=$this->facility();
        $this->assertNull(app(YoyakuApiService::class)->getDailySchedule('2026-11-04',77,$f->id));
        Http::assertNothingSent();
    }
    public function test_unknown_facility_never_uses_global_token(): void
    {
        Http::fake(['*'=>Http::response([])]);
        config(['services.houkago_plus.base_url'=>'https://synthetic.example.test','services.houkago_plus.api_token'=>'global-token']);
        $this->assertNull(app(YoyakuApiService::class)->listUsers(77,999999));
        Http::assertNothingSent();
    }
    public function test_duplicate_business_mapping_does_not_choose_first_facility(): void
    {
        Bus::fake(); $a=$this->facility(); $this->facility();
        Child::create(['facility_id'=>$a->id,'name'=>'Synthetic child','contract_status'=>'active','yoyaku_user_id'=>501]);
        $this->notify()->assertStatus(409);
        $this->assertDatabaseCount('usage_records',0);
    }
    public function test_duplicate_child_mapping_does_not_choose_first_child(): void
    {
        Bus::fake(); $a=$this->facility();
        foreach ([1,2] as $i) Child::create(['facility_id'=>$a->id,'name'=>'Synthetic '.$i,'contract_status'=>'active','yoyaku_user_id'=>501]);
        $this->notify()->assertStatus(409);
        $this->assertDatabaseCount('usage_records',0);
    }
    public function test_same_child_number_in_different_facilities_is_scoped(): void
    {
        Bus::fake(); $a=$this->facility(); $b=$this->facility(['yoyaku_business_id'=>88]);
        $child=Child::create(['facility_id'=>$a->id,'name'=>'Synthetic A','contract_status'=>'active','yoyaku_user_id'=>501]);
        Child::create(['facility_id'=>$b->id,'name'=>'Synthetic B','contract_status'=>'active','yoyaku_user_id'=>501]);
        $this->notify()->assertOk();
        $this->assertSame($child->id,UsageRecord::sole()->child_id);
    }

    public function test_all_api_methods_require_a_facility_token_even_when_a_global_token_exists(): void
    {
        Http::fake(['*'=>Http::response(['ok'=>true,'applied'=>true])]);
        config(['services.houkago_plus.base_url'=>'https://synthetic.example.test',
            'services.houkago_plus.api_token'=>'global-token']);
        $missing=$this->facility();
        $blank=$this->facility(['yoyaku_api_token'=>'   ']);
        $api=app(YoyakuApiService::class);
        foreach ([null,0,-1,999999,$missing->id,$blank->id] as $id) {
            $this->assertNull($api->getDailySchedule('2026-11-04',77,$id));
            $this->assertNull($api->getActuals('2026-11-04',77,$id));
            $this->assertNull($api->listUsers(77,$id));
            $this->assertNull($api->createBooking([],$id));
            $this->assertNull($api->updateBooking(123,[],$id));
            $this->assertFalse($api->deleteBooking(123,$id));
            $this->assertNull($api->upsertContactNote([],$id));
            $this->assertNull($api->createUser([],$id));
            if ($id !== null) {
                try {
                    $api->syncBooking([],$id);
                    $this->fail('Synchronization must reject missing facility credentials.');
                } catch (\RuntimeException $e) {
                    $this->assertSame('Booking synchronization failed',$e->getMessage());
                }
            }
        }
        Http::assertNothingSent();
    }

    public function test_facility_token_is_used_for_reads_and_writes_without_crossing_facilities(): void
    {
        Http::fake(['*'=>Http::response(['ok'=>true,'applied'=>true])]);
        config(['services.houkago_plus.base_url'=>'https://synthetic.example.test',
            'services.houkago_plus.api_token'=>'global-token']);
        $a=$this->facility(['yoyaku_api_token'=>'facility-a']);
        $b=$this->facility(['yoyaku_business_id'=>88,'yoyaku_api_token'=>'facility-b']);
        $api=app(YoyakuApiService::class);
        $api->listUsers(77,$a->id);
        $api->listUsers(88,$b->id);
        $api->createUser(['name'=>'Synthetic child'],$a->id);
        $api->syncBooking(['external_ref'=>'ucrm:synthetic'],$b->id);
        $requests=Http::recorded()->values();
        $this->assertCount(4,$requests);
        foreach (['facility-a','facility-b','facility-a','facility-b'] as $index=>$token) {
            $this->assertTrue($requests[$index][0]->hasHeader('Authorization','Bearer '.$token));
        }
    }

    public function test_ambiguous_mapping_preserves_existing_booking_and_contact_notes(): void
    {
        Bus::fake();
        $a=$this->facility();
        $child=Child::create(['facility_id'=>$a->id,'name'=>'Synthetic A',
            'contract_status'=>'active','yoyaku_user_id'=>501]);
        $record=UsageRecord::create(['facility_id'=>$a->id,'child_id'=>$child->id,
            'date'=>'2026-11-04','status'=>'attended','pickup_done'=>true,'check_in_time'=>'14:00']);
        $note=\App\Models\ContactNote::create(['facility_id'=>$a->id,'child_id'=>$child->id,
            'date'=>'2026-11-04','status'=>'published','guardian_comment'=>'Keep this comment']);
        $beforeRecord=$record->fresh()->getRawOriginal();
        $beforeNote=$note->fresh()->getRawOriginal();

        // Duplicate children within one facility must block every event type.
        Child::create(['facility_id'=>$a->id,'name'=>'Synthetic duplicate',
            'contract_status'=>'active','yoyaku_user_id'=>501]);
        // Fixture creation may enqueue outbound work; observe only received events.
        Bus::fake();
        foreach (['booking.created','booking.updated','booking.deleted',
            'contact_note.read','contact_note.guardian_entry'] as $event) {
            $this->notify($event,['guardian_comment'=>'Must not overwrite'])->assertStatus(409);
        }
        $this->assertSame($beforeRecord,$record->fresh()->getRawOriginal());
        $this->assertSame($beforeNote,$note->fresh()->getRawOriginal());
        $this->assertDatabaseCount('usage_records',1);
        $this->assertDatabaseCount('contact_notes',1);
        $this->assertDatabaseCount('yoyaku_booking_states',0);
        Bus::assertNothingDispatched();
    }

    public function test_duplicate_businesses_with_different_secrets_are_not_selected_by_signature(): void
    {
        Bus::fake();
        $a=$this->facility();
        $this->facility(['yoyaku_webhook_secret'=>'another-secret']);
        Child::create(['facility_id'=>$a->id,'name'=>'Synthetic A',
            'contract_status'=>'active','yoyaku_user_id'=>501]);
        foreach (['synthetic-secret','another-secret'] as $secret) {
            $this->notify(secret:$secret)->assertStatus(409);
        }
        $this->assertDatabaseCount('usage_records',0);
        $this->assertDatabaseCount('yoyaku_booking_states',0);
        Bus::assertNothingDispatched();
    }

    public function test_deleted_child_does_not_make_an_active_mapping_ambiguous(): void
    {
        Bus::fake(); $a=$this->facility();
        $deleted=Child::create(['facility_id'=>$a->id,'name'=>'Synthetic old',
            'contract_status'=>'active','yoyaku_user_id'=>501]);
        $deleted->delete();
        $active=Child::create(['facility_id'=>$a->id,'name'=>'Synthetic active',
            'contract_status'=>'active','yoyaku_user_id'=>501]);
        $this->notify()->assertOk();
        $this->assertSame($active->id,UsageRecord::sole()->child_id);
        $this->assertTrue($deleted->fresh()->trashed());
    }
}
