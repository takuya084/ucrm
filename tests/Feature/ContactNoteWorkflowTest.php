<?php
namespace Tests\Feature;
use App\Jobs\PushContactNote;
use App\Models\{Child,ContactNote,Facility,Staff,User};
use App\Services\YoyakuApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue};
use Tests\TestCase;
class ContactNoteWorkflowTest extends TestCase
{
    use RefreshDatabase;
    private Facility $facility; private Child $child; private User $user; private Staff $staff;
    private string $entry='a3040a60-5564-40d7-b2c5-75990381ec39';
    protected function setUp(): void
    {
        parent::setUp(); Queue::fake();
        $this->facility=Facility::create(['name'=>'Synthetic workflow','capacity_per_day'=>10,'yoyaku_business_id'=>77,'yoyaku_webhook_secret'=>'synthetic-secret','yoyaku_api_token'=>'synthetic-token']);
        $this->child=Child::create(['facility_id'=>$this->facility->id,'name'=>'Synthetic child','contract_status'=>'active','yoyaku_user_id'=>501]);
        $this->user=User::factory()->create();
        $this->staff=Staff::create(['user_id'=>$this->user->id,'facility_id'=>$this->facility->id,'name'=>'Synthetic staff','role'=>'staff','is_active'=>true]);
        config(['services.houkago_plus.base_url'=>'https://p.example.test']);
    }
    private function event(string $event='contact_note.pre_use_entry',array $data=[])
    {
        $payload=['event'=>$event,'business_id'=>77,'sent_at'=>now()->toIso8601String(),
            'data'=>$data+['user_id'=>501,'date'=>'2026-11-07','revision'=>1,'guardian_entry_ref'=>$this->entry,'guardian_comment'=>'Synthetic morning condition']];
        $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        return $this->call('POST','/api/webhooks/yoyaku',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_YOYAKU_SIGNATURE'=>hash_hmac('sha256',$body,'synthetic-secret')],$body);
    }
    private function publish(): ContactNote
    {
        $note=ContactNote::sole(); $note->update(['status'=>'published','published_at'=>now(),'guardian_message'=>'Synthetic facility message']);
        Http::fake(['*'=>Http::response(['ok'=>true])]);
        (new PushContactNote($note->id))->handle(app(YoyakuApiService::class));
        return $note->fresh();
    }
    public function test_morning_entry_creates_only_a_draft_and_staff_check_is_invalidated_by_new_family_content(): void
    {
        $this->event()->assertOk(); $note=ContactNote::sole();
        $this->assertSame('draft',$note->status); $this->assertNull($note->published_at); $this->assertNull($note->read_at);
        $this->actingAs($this->user)->post('/contact-notes/'.$note->id.'/check-guardian',['guardian_revision'=>1])->assertRedirect();
        $this->assertNotNull($note->fresh()->guardian_checked_at); $this->assertSame($this->staff->id,(int)$note->fresh()->guardian_checked_by);
        $this->event()->assertOk()->assertJson(['applied'=>false]);
        $this->assertNotNull($note->fresh()->guardian_checked_at);
        $this->event(data:['revision'=>2,'guardian_comment'=>'Synthetic newer condition'])->assertOk();
        $this->assertNull($note->fresh()->guardian_checked_at);
        $this->post('/contact-notes/'.$note->id.'/check-guardian',['guardian_revision'=>1])->assertSessionHasErrors('guardian_revision');
        $this->event(data:['revision'=>1,'guardian_comment'=>'Synthetic stale'])->assertOk()->assertJson(['applied'=>false]);
        $this->assertSame('Synthetic newer condition',$note->fresh()->guardian_comment);
    }
    public function test_reference_change_and_malformed_morning_payload_are_rejected(): void
    {
        $this->event()->assertOk();
        $this->event(data:['revision'=>2,'guardian_entry_ref'=>'61924b23-e86e-4155-9cf6-bda544757f3b'])->assertStatus(409);
        foreach ([['guardian_entry_ref'=>'bad'],['revision'=>0],['external_ref'=>'ucrm:1'],['guardian_comment'=>str_repeat('x',2001)]] as $data) $this->event(data:$data)->assertStatus(400);
        $this->assertSame(1,ContactNote::sole()->guardian_source_revision);
    }
    public function test_only_matching_public_body_can_be_stamped_and_retries_preserve_confirmation(): void
    {
        $this->event()->assertOk(); $note=$this->publish();
        $stamp=['external_ref'=>'ucrm:'.$note->id,'publication_hash'=>$note->publication_hash,'confirmed_at'=>now()->subSeconds(5)->toIso8601String()];
        $this->event('contact_note.confirmed',$stamp)->assertOk();
        $confirmed=$note->fresh()->guardian_confirmed_at->toIso8601String();
        (new PushContactNote($note->id))->handle(app(YoyakuApiService::class));
        $this->assertSame($confirmed,$note->fresh()->guardian_confirmed_at->toIso8601String());
        $note->update(['guardian_message'=>'Synthetic edited body']);
        (new PushContactNote($note->id))->handle(app(YoyakuApiService::class));
        $this->assertNull($note->fresh()->guardian_confirmed_at);
        $this->event('contact_note.confirmed',$stamp)->assertOk()->assertJson(['applied'=>false]);
        $this->assertNull($note->fresh()->guardian_confirmed_at);
    }
    public function test_legacy_read_never_counts_as_confirmation_and_foreign_staff_cannot_check(): void
    {
        $this->event()->assertOk(); $note=$this->publish();
        $this->event('contact_note.read',['external_ref'=>'ucrm:'.$note->id])->assertOk();
        $this->assertNotNull($note->fresh()->read_at); $this->assertNull($note->fresh()->guardian_confirmed_at);
        $other=Facility::create(['name'=>'Synthetic other','capacity_per_day'=>10]);
        $user=User::factory()->create(); Staff::create(['user_id'=>$user->id,'facility_id'=>$other->id,'name'=>'Synthetic other staff','role'=>'staff','is_active'=>true]);
        $this->actingAs($user)->post('/contact-notes/'.$note->id.'/check-guardian',['guardian_revision'=>1])->assertForbidden();
        $this->assertNull($note->fresh()->guardian_checked_at);
    }
    public function test_withdrawn_facility_body_stays_draft_when_family_edits_and_stamp_is_refused(): void
    {
        $this->event()->assertOk(); $note=$this->publish();
        $note->forceFill(['status'=>'draft','withdrawn_at'=>now()])->save();
        $this->event(data:['revision'=>2])->assertOk();
        $this->assertSame('draft',$note->fresh()->status);
        $this->event('contact_note.confirmed',['external_ref'=>'ucrm:'.$note->id,'publication_hash'=>$note->publication_hash,'confirmed_at'=>now()->toIso8601String()])->assertOk()->assertJson(['applied'=>false]);
        $this->assertNull($note->fresh()->guardian_confirmed_at);
    }
    public function test_public_support_edits_invalidate_a_stamp_but_internal_notes_do_not(): void
    {
        $this->event()->assertOk(); $note=$this->publish();
        $support=\App\Models\SupportRecord::create(['child_id'=>$this->child->id,'date'=>'2026-11-07',
            'staff_id'=>$this->staff->id,'condition'=>'good','behavior_note'=>'Synthetic internal observation']);
        $note->update(['support_record_id'=>$support->id]);
        (new PushContactNote($note->id))->handle(app(YoyakuApiService::class));
        $hash=$note->fresh()->publication_hash;
        $stamp=['external_ref'=>'ucrm:'.$note->id,'publication_hash'=>$hash,'confirmed_at'=>now()->toIso8601String()];
        $this->event('contact_note.confirmed',$stamp)->assertOk();
        $support->update(['behavior_note'=>'Synthetic edited internal observation']);
        $this->assertSame($hash,$note->fresh()->currentPublicationHash());
        $support->update(['condition'=>'poor']);
        $this->assertNotSame($hash,$note->fresh()->currentPublicationHash());
        $this->event('contact_note.confirmed',$stamp)->assertOk()->assertJson(['applied'=>false]);
        (new PushContactNote($note->id))->handle(app(YoyakuApiService::class));
        $this->assertNull($note->fresh()->guardian_confirmed_at);
        Http::assertSent(fn($request)=>!array_key_exists('behavior_note',$request->data()));
    }

}
