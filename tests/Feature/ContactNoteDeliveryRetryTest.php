<?php

namespace Tests\Feature;

use App\Jobs\PushContactNote;
use App\Models\{Child, ContactNote, Facility, Staff, User};
use App\Services\{ContactNoteDelivery, ContactNoteStatus, YoyakuApiService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Http};
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactNoteDeliveryRetryTest extends TestCase
{
    use RefreshDatabase;
    private Facility $facility;
    private Child $child;
    private ContactNote $note;

    protected function setUp(): void
    {
        parent::setUp(); Bus::fake(); Http::preventStrayRequests();
        config(['services.houkago_plus.base_url'=>'https://p.example.test']);
        $this->facility=Facility::create(['name'=>'Synthetic retry', 'capacity_per_day'=>10,
            'yoyaku_business_id'=>77, 'yoyaku_api_token'=>'synthetic-private-token']);
        $this->child=Child::create(['facility_id'=>$this->facility->id, 'name'=>'Synthetic child',
            'contract_status'=>'active', 'yoyaku_user_id'=>501]);
        $user=User::factory()->create();
        Staff::create(['facility_id'=>$this->facility->id, 'user_id'=>$user->id, 'name'=>'Synthetic staff', 'role'=>'staff', 'is_active'=>true]);
        $this->actingAs($user);
        $this->note=ContactNote::create(['facility_id'=>$this->facility->id,'child_id'=>$this->child->id,
            'date'=>'2026-11-07','status'=>'published','published_at'=>now(),'guardian_message'=>'Public synthetic content','guardian_comment'=>'Home synthetic content']);
    }

    private function retry(): \Illuminate\Testing\TestResponse
    {
        return $this->post('/contact-notes/'.$this->note->id.'/retry-delivery');
    }

    public function test_retry_preserves_publication_body_and_confirmation_and_coalesces_double_clicks(): void
    {
        $this->note->forceFill(['publication_hash'=>$this->note->currentPublicationHash(),
            'guardian_confirmed_hash'=>$this->note->currentPublicationHash(),'guardian_confirmed_at'=>now()])->save();
        $before=$this->note->fresh()->only(['status','published_at','withdrawn_at','guardian_message','guardian_comment','publication_hash','guardian_confirmed_hash','guardian_confirmed_at','updated_at']);
        $this->get('/contact-notes?date=2026-11-07&task=delivery')->assertInertia(fn(Assert $page)=>$page->has('rows',1));
        $this->retry()->assertRedirect();
        $requested=$this->note->fresh();
        $this->assertNotNull($requested->delivery_request_id);
        $this->assertEquals($before,$requested->only(array_keys($before)));
        $this->retry()->assertRedirect();
        $this->assertSame($requested->delivery_request_id,$this->note->fresh()->delivery_request_id);
        Bus::assertDispatchedTimes(PushContactNote::class,1);
        Bus::assertDispatched(PushContactNote::class,fn($job)=>$job->afterCommit && $job->requestId===$requested->delivery_request_id);
        Http::fake(['*'=>Http::response(['ok'=>true])]);
        (new PushContactNote($requested->id))->handle(app(YoyakuApiService::class));
        $this->assertSame('連携完了',ContactNoteStatus::for($this->note->fresh())['delivery']);
        $this->assertSame($before['guardian_confirmed_hash'],$this->note->fresh()->guardian_confirmed_hash);
        $this->get('/contact-notes?date=2026-11-07&task=delivery')->assertInertia(fn(Assert $page)=>$page->has('rows',0));
        $this->get('/dashboard?date=2026-11-07')->assertInertia(fn(Assert $page)=>$page->where('dailyWork.counts.delivery',0));
    }

    public function test_http_failure_has_safe_reason_then_retry_succeeds_and_clears_it(): void
    {
        Http::fake(['*'=>Http::response(['error'=>'synthetic-private-token and private remote body'],401)]);
        $this->retry();
        $job=(new PushContactNote($this->note->id))->withFakeQueueInteractions();
        $job->handle(app(YoyakuApiService::class)); $job->assertReleased(60);
        $this->assertSame('authentication',$this->note->fresh()->delivery_error_code);
        $this->get('/contact-notes?date=2026-11-07')->assertOk()->assertDontSee('synthetic-private-token')->assertDontSee('private remote body')
            ->assertInertia(fn(Assert $page)=>$page->where('rows.0.delivery.canRetry',true)
                ->where('rows.0.delivery.reason',ContactNoteDelivery::REASONS['authentication']));
        $this->retry(); Http::fake(['*'=>Http::response(['ok'=>true])]);
        (new PushContactNote($this->note->id))->handle(app(YoyakuApiService::class));
        $this->assertNull($this->note->fresh()->delivery_error_code);
        $this->assertFalse(ContactNoteDelivery::details($this->note->fresh())['pending']);
    }

    public function test_connection_and_response_failures_are_distinguished_without_raw_details(): void
    {
        foreach ([503=>'remote_error',422=>'rejected',403=>'authentication'] as $status=>$code) {
            Http::fake(['*'=>Http::response(['error'=>'private detail'],$status)]);
            $this->retry(); (new PushContactNote($this->note->id))->withFakeQueueInteractions()->handle(app(YoyakuApiService::class));
            $this->assertSame($code,$this->note->fresh()->delivery_error_code);
        }
        Http::fake(['*'=>fn()=>throw new \Illuminate\Http\Client\ConnectionException('private connection detail')]);
        $this->retry(); (new PushContactNote($this->note->id))->withFakeQueueInteractions()->handle(app(YoyakuApiService::class));
        $this->assertSame('unreachable',$this->note->fresh()->delivery_error_code);
        Http::fake(['*'=>Http::response('not-json',200)]);
        $this->retry(); (new PushContactNote($this->note->id))->withFakeQueueInteractions()->handle(app(YoyakuApiService::class));
        $this->assertSame('invalid_response',$this->note->fresh()->delivery_error_code);
    }

    public function test_configuration_errors_do_not_enqueue_and_are_explained(): void
    {
        $this->facility->update(['yoyaku_business_id'=>null]);
        $this->retry()->assertRedirect();
        $this->assertSame(ContactNoteDelivery::REASONS['facility_unlinked'],ContactNoteDelivery::details($this->note->fresh())['reason']);
        $this->facility->update(['yoyaku_business_id'=>77]);$this->child->update(['yoyaku_user_id'=>null]);
        $this->retry()->assertRedirect();
        $this->assertSame(ContactNoteDelivery::REASONS['child_unlinked'],ContactNoteDelivery::details($this->note->fresh())['reason']);
        $this->child->update(['yoyaku_user_id'=>501]);$this->facility->update(['yoyaku_api_token'=>null]);
        $this->retry()->assertRedirect();
        $this->assertSame(ContactNoteDelivery::REASONS['not_configured'],ContactNoteDelivery::details($this->note->fresh())['reason']);
        $this->facility->update(['yoyaku_api_token'=>'synthetic-private-token']);
        Child::create(['facility_id'=>$this->facility->id,'name'=>'Synthetic duplicate','yoyaku_user_id'=>501]);
        $this->retry()->assertRedirect();
        $this->assertSame(ContactNoteDelivery::REASONS['ambiguous_target'],ContactNoteDelivery::details($this->note->fresh())['reason']);
        Bus::assertNotDispatched(PushContactNote::class);Http::assertNothingSent();
    }

    public function test_draft_completed_and_foreign_notes_cannot_be_retried(): void
    {
        $this->note->update(['status'=>'draft']);$this->retry()->assertRedirect();
        $this->note->update(['status'=>'published']);
        $this->note->forceFill(['publication_hash'=>$this->note->currentPublicationHash(),'yoyaku_synced_at'=>now()->addSecond()])->save();
        $this->retry()->assertRedirect();
        $foreign=Facility::create(['name'=>'Synthetic foreign','capacity_per_day'=>10]);
        $this->note->update(['facility_id'=>$foreign->id]);$this->retry()->assertForbidden();
        Bus::assertNotDispatched(PushContactNote::class);
    }

    public function test_withdrawal_retry_sends_only_withdrawal_and_never_republishes(): void
    {
        $this->note->forceFill(['status'=>'draft','published_at'=>null,'withdrawn_at'=>now()])->save();
        $this->retry()->assertRedirect();Http::fake(['*'=>Http::response(['ok'=>true])]);
        (new PushContactNote($this->note->id))->handle(app(YoyakuApiService::class));
        Http::assertSent(fn($request)=>$request['withdrawn']===true && !array_key_exists('guardian_message',$request->data()));
        $this->assertSame('draft',$this->note->fresh()->status);
        $this->assertSame('Public synthetic content',$this->note->fresh()->guardian_message);
        $this->assertSame('取り下げ連携完了',ContactNoteStatus::for($this->note->fresh())['delivery']);
    }

    public function test_superseded_job_and_late_failure_cannot_overwrite_latest_success(): void
    {
        $this->retry();$old=new PushContactNote($this->note->id);
        $this->travel(61)->seconds();$this->retry();
        Http::fake(['*'=>Http::response(['ok'=>true])]);
        (new PushContactNote($this->note->id))->handle(app(YoyakuApiService::class));
        $old->handle(app(YoyakuApiService::class));$old->failed(new \RuntimeException('private exception'));
        Http::assertSentCount(1);
        $this->assertNull($this->note->fresh()->delivery_error_code);
        $this->assertFalse(ContactNoteDelivery::details($this->note->fresh())['pending']);
    }

    public function test_stopped_worker_is_reported_as_unknown_not_as_a_confirmed_communication_failure(): void
    {
        $this->retry();$note=$this->note->fresh();
        $this->assertFalse(ContactNoteDelivery::details($note)['canRetry']);
        $this->assertNull($note->delivery_attempted_at);
        $this->travel(61)->seconds();
        $details=ContactNoteDelivery::details($note);
        $this->assertTrue($details['canRetry']);$this->assertStringContainsString('稼働状況',$details['reason']);
        $this->assertStringNotContainsString('接続できませんでした',$details['reason']);
    }
}
