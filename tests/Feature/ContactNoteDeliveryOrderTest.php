<?php

namespace Tests\Feature;

use App\Jobs\PushContactNote;
use App\Models\{Child,ContactNote,Facility};
use App\Services\YoyakuApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContactNoteDeliveryOrderTest extends TestCase
{
    use RefreshDatabase;
    private ContactNote $note;
    protected function setUp(): void
    {
        parent::setUp();
        $facility=Facility::create(['name'=>'Synthetic ordered notes','capacity_per_day'=>10,'yoyaku_business_id'=>77,
            'yoyaku_webhook_secret'=>'synthetic-secret','yoyaku_api_token'=>'synthetic-token']);
        $child=Child::create(['facility_id'=>$facility->id,'name'=>'Synthetic child','contract_status'=>'active','yoyaku_user_id'=>501]);
        $this->note=ContactNote::create(['facility_id'=>$facility->id,'child_id'=>$child->id,'date'=>'2026-11-04',
            'status'=>'published','published_at'=>now(),'guardian_message'=>'Synthetic public original']);
        config(['services.houkago_plus.base_url'=>'https://p.example.test']);
    }
    private function send(array $changes=[])
    {
        $body=['event'=>'contact_note.guardian_entry','business_id'=>77,'sent_at'=>now()->toIso8601String(),
            'data'=>array_replace(['user_id'=>501,'date'=>'2026-11-04','external_ref'=>'ucrm:'.$this->note->id,
                'revision'=>2,'guardian_comment'=>'Synthetic newest'],$changes)];
        $json=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        return $this->call('POST','/api/webhooks/yoyaku',[],[],[],
            ['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json',
             'HTTP_X_YOYAKU_SIGNATURE'=>hash_hmac('sha256',$json,'synthetic-secret')],$json);
    }
    public function test_outbound_jobs_allocate_increasing_revisions_with_their_current_body(): void
    {
        Http::fake(['*'=>Http::response(['ok'=>true])]);
        $job=new PushContactNote($this->note->id);
        $job->handle(app(YoyakuApiService::class));
        $this->note->update(['guardian_message'=>'Synthetic public newest']);
        $job->handle(app(YoyakuApiService::class));
        $requests=Http::recorded()->map(fn($pair)=>$pair[0]->data())->all();
        $this->assertSame([1,2],array_column($requests,'revision'));
        $this->assertSame(['Synthetic public original','Synthetic public newest'],array_column($requests,'guardian_message'));
        $this->assertSame(2,$this->note->fresh()->delivery_revision);
    }
    public function test_older_and_duplicate_replies_do_not_replace_newest_or_create_audit_changes(): void
    {
        $this->send()->assertOk();
        $before=\App\Models\AuditLog::count();
        foreach ([1,2] as $revision) $this->send(['revision'=>$revision,'guardian_comment'=>'Synthetic stale'])->assertOk()->assertJson(['applied'=>false]);
        $this->assertSame($before,\App\Models\AuditLog::count());
        $this->assertSame('Synthetic newest',$this->note->fresh()->guardian_comment);
        $this->assertSame(2,$this->note->fresh()->guardian_source_revision);
        $this->send(['revision'=>3,'guardian_comment'=>null])->assertOk();
        $this->assertNull($this->note->fresh()->guardian_comment);
    }
    public function test_versioned_replies_require_the_exact_existing_note_identity(): void
    {
        $this->send(['external_ref'=>'ucrm:999'])->assertStatus(409);
        $this->send(['external_ref'=>null])->assertStatus(400);
        $this->send(['revision'=>null])->assertStatus(400);
        $this->assertNull($this->note->fresh()->guardian_comment);
    }
    public function test_legacy_reply_cannot_overwrite_after_upgrade(): void
    {
        $this->send()->assertOk();
        $body=['event'=>'contact_note.guardian_entry','business_id'=>77,'sent_at'=>now()->toIso8601String(),
            'data'=>['user_id'=>501,'date'=>'2026-11-04','guardian_comment'=>'Synthetic legacy']];
        $json=json_encode($body);
        $this->call('POST','/api/webhooks/yoyaku',[],[],[],['CONTENT_TYPE'=>'application/json',
            'HTTP_X_YOYAKU_SIGNATURE'=>hash_hmac('sha256',$json,'synthetic-secret')],$json)->assertStatus(409);
        $this->assertSame('Synthetic newest',$this->note->fresh()->guardian_comment);
    }
    public function test_invalid_revisions_are_rejected_without_modification(): void
    {
        foreach ([0,-1,[],true,'bad',9007199254740992] as $revision) $this->send(['revision'=>$revision])->assertStatus(400);
        $this->assertNull($this->note->fresh()->guardian_source_revision);
        $this->assertNull($this->note->fresh()->guardian_comment);
    }
}
