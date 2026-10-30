<?php

namespace Tests\Feature;

use App\Jobs\PushContactNote;
use App\Models\{AuditLog,Child,ContactNote,Facility,Staff,User};
use App\Services\YoyakuApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus,Http};
use Tests\TestCase;

class ContactNoteWithdrawalTest extends TestCase
{
    use RefreshDatabase;
    private ContactNote $note;
    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $facility=Facility::create(['name'=>'Synthetic withdrawal','capacity_per_day'=>10,'yoyaku_business_id'=>77,'yoyaku_api_token'=>'synthetic-token']);
        $user=User::factory()->create();
        Staff::create(['user_id'=>$user->id,'facility_id'=>$facility->id,'name'=>'Synthetic staff','role'=>'staff','is_active'=>true]);
        $this->actingAs($user);
        $child=Child::create(['facility_id'=>$facility->id,'name'=>'Synthetic child','contract_status'=>'active','yoyaku_user_id'=>501]);
        $this->note=ContactNote::create(['facility_id'=>$facility->id,'child_id'=>$child->id,'date'=>'2026-11-04',
            'status'=>'published','published_at'=>now(),'guardian_message'=>'Synthetic public history','guardian_comment'=>'Synthetic home history']);
        config(['services.houkago_plus.base_url'=>'https://synthetic.example.test']);
    }
    public function test_unpublish_preserves_history_audits_and_queues_after_commit(): void
    {
        $before=AuditLog::count();
        $this->post('/contact-notes/'.$this->note->id.'/unpublish')->assertRedirect();
        $note=$this->note->fresh();
        $this->assertSame('draft',$note->status);
        $this->assertNotNull($note->withdrawn_at);
        $this->assertNull($note->published_at);
        $this->assertNull($note->yoyaku_synced_at);
        $this->assertSame('Synthetic public history',$note->guardian_message);
        $this->assertSame('Synthetic home history',$note->guardian_comment);
        $this->assertGreaterThan($before,AuditLog::count());
        $this->assertFalse($note->trashed());
        Bus::assertDispatched(PushContactNote::class,fn($job)=>$job->afterCommit);
    }
    public function test_old_queued_public_job_sends_only_the_current_withdrawal(): void
    {
        $job=new PushContactNote($this->note->id);
        $this->post('/contact-notes/'.$this->note->id.'/unpublish')->assertRedirect();
        Http::fake(['*'=>Http::response(['ok'=>true,'applied'=>true])]);
        $job->handle(app(YoyakuApiService::class));
        Http::assertSent(function($request) {
            $this->assertTrue($request['withdrawn']);
            $this->assertNull($request['published_at']);
            $this->assertSame(1,$request['revision']);
            $this->assertEqualsCanonicalizing(['revision','external_ref','user_id','date','withdrawn','published_at'],array_keys($request->data()));
            return true;
        });
        $this->assertNotNull($this->note->fresh()->yoyaku_synced_at);
    }
    public function test_republish_clears_withdrawal_and_queued_jobs_read_the_new_state(): void
    {
        $this->post('/contact-notes/'.$this->note->id.'/unpublish')->assertRedirect();
        $job=new PushContactNote($this->note->id);
        $this->post('/contact-notes/'.$this->note->id.'/publish')->assertRedirect();
        $this->assertNull($this->note->fresh()->withdrawn_at);
        $this->assertSame('published',$this->note->fresh()->status);
        Http::fake(['*'=>Http::response(['ok'=>true,'applied'=>true])]);
        $job->handle(app(YoyakuApiService::class));
        Http::assertSent(fn($request)=>$request['withdrawn']===false && $request['guardian_message']==='Synthetic public history');
        $this->assertSame('Synthetic home history',$this->note->fresh()->guardian_comment);
    }
    public function test_foreign_facility_cannot_withdraw_and_failed_delivery_remains_pending(): void
    {
        $foreign=Facility::create(['name'=>'Synthetic foreign','capacity_per_day'=>10]);
        $child=Child::create(['facility_id'=>$foreign->id,'name'=>'Synthetic foreign child','contract_status'=>'active']);
        $note=ContactNote::create(['facility_id'=>$foreign->id,'child_id'=>$child->id,'date'=>'2026-11-04','status'=>'published']);
        $this->post('/contact-notes/'.$note->id.'/unpublish')->assertForbidden();
        Bus::assertNotDispatched(PushContactNote::class);
        $this->post('/contact-notes/'.$this->note->id.'/unpublish')->assertRedirect();
        Http::fake(['*'=>Http::response([],503)]);
        $job=(new PushContactNote($this->note->id))->withFakeQueueInteractions();
        $job->handle(app(YoyakuApiService::class));
        $job->assertReleased(60);
        $this->assertNull($this->note->fresh()->yoyaku_synced_at);
    }
}
