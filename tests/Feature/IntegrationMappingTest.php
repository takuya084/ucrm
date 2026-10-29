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
    private function notify()
    {
        $json=json_encode(['event'=>'booking.created','business_id'=>77,'sent_at'=>now()->toIso8601String(),
            'data'=>['user_id'=>501,'date'=>'2026-11-04','pickup_time'=>'15:00','booking_revision'=>1]],JSON_THROW_ON_ERROR);
        return $this->call('POST','/api/webhooks/yoyaku',server:['CONTENT_TYPE'=>'application/json',
            'HTTP_X_YOYAKU_SIGNATURE'=>hash_hmac('sha256',$json,'synthetic-secret')],content:$json);
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
}
