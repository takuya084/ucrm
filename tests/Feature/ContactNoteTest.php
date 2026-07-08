<?php

namespace Tests\Feature;

use App\Jobs\PushContactNote;
use App\Models\Child;
use App\Models\ContactNote;
use App\Models\Facility;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ContactNoteTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET      = 'test-webhook-secret';
    private const BUSINESS_ID = 88;

    private Facility $facility;
    private Child $child;
    private User $user;
    private Staff $staff;

    /** 年間PDF出力などリーダー以上限定の操作用 */
    private function actingAsLeader(): static
    {
        $leaderUser = User::factory()->create();
        Staff::create([
            'user_id'     => $leaderUser->id,
            'facility_id' => $this->facility->id,
            'name'        => 'テストリーダー',
            'role'        => 'leader',
            'is_active'   => true,
        ]);

        return $this->actingAs($leaderUser);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::create([
            'name'                  => 'テスト事業所',
            'facility_code'         => '1310000001',
            'service_type'          => 'houday',
            'area_unit_price'       => 10.00,
            'capacity_per_day'      => 10,
            'yoyaku_business_id'    => self::BUSINESS_ID,
            'yoyaku_webhook_secret' => self::SECRET,
        ]);

        $this->child = Child::create([
            'facility_id'     => $this->facility->id,
            'name'            => 'テスト児童',
            'name_kana'       => 'テストジドウ',
            'contract_status' => 'active',
            'yoyaku_user_id'  => 501,
        ]);

        $this->user = User::factory()->create();
        $this->staff = Staff::create([
            'user_id'     => $this->user->id,
            'facility_id' => $this->facility->id,
            'name'        => 'テスト職員',
            'role'        => 'staff',
            'is_active'   => true,
        ]);
    }

    private function storePayload(array $contactNote = []): array
    {
        return [
            'child_id'      => $this->child->id,
            'date'          => '2026-07-01',
            'staff_id'      => $this->staff->id,
            'condition'     => 'good',
            'behavior_note' => '落ち着いて過ごせた',
            'contact_note'  => $contactNote,
        ];
    }

    public function test_支援記録の保存と同時に連絡帳が下書き作成される(): void
    {
        $this->actingAs($this->user)
            ->post(route('support-records.store'), $this->storePayload([
                'guardian_message' => '今日は工作を頑張りました。',
                'meal_note'        => '完食',
                'five_domain_tags' => ['health_life', 'social_relations'],
                'goal_progress'    => 'achieved',
            ]))
            ->assertStatus(200);

        $note = ContactNote::first();
        $this->assertNotNull($note);
        $this->assertSame(ContactNote::STATUS_DRAFT, $note->status);
        $this->assertSame('今日は工作を頑張りました。', $note->guardian_message);
        $this->assertSame(['health_life', 'social_relations'], $note->five_domain_tags);
        $this->assertSame('achieved', $note->goal_progress);
        $this->assertSame($this->facility->id, $note->facility_id);
        $this->assertNotNull($note->support_record_id);

        // 保護者共有フラグは「連絡帳あり」の意味で自動セットされる
        $this->assertTrue($note->supportRecord->is_shared_with_guardian);
    }

    public function test_連絡帳欄が空なら連絡帳は作成されない(): void
    {
        $this->actingAs($this->user)
            ->post(route('support-records.store'), $this->storePayload([]))
            ->assertStatus(200);

        $this->assertSame(0, ContactNote::count());
    }

    public function test_publish_nowで公開されp_yoyakuへの配信ジョブが投入される(): void
    {
        Bus::fake();

        $this->actingAs($this->user)
            ->post(route('support-records.store'), $this->storePayload([
                'guardian_message' => '今日も元気に過ごしました。',
                'publish_now'      => true,
            ]))
            ->assertStatus(200);

        $note = ContactNote::first();
        $this->assertSame(ContactNote::STATUS_PUBLISHED, $note->status);
        $this->assertNotNull($note->published_at);
        Bus::assertDispatched(PushContactNote::class, fn ($job) => $job->contactNoteId === $note->id);
    }

    public function test_記入がない連絡帳は公開できない(): void
    {
        $note = ContactNote::create([
            'facility_id' => $this->facility->id,
            'child_id'    => $this->child->id,
            'date'        => '2026-07-01',
        ]);

        $this->actingAs($this->user)
            ->post(route('contact-notes.publish', $note))
            ->assertRedirect();

        $this->assertSame(ContactNote::STATUS_DRAFT, $note->fresh()->status);
    }

    public function test_他施設の連絡帳は公開できない(): void
    {
        $otherFacility = Facility::create(['name' => '別事業所', 'capacity_per_day' => 10]);
        $otherChild    = Child::create([
            'facility_id'     => $otherFacility->id,
            'name'            => '別児童',
            'contract_status' => 'active',
        ]);
        $note = ContactNote::create([
            'facility_id'      => $otherFacility->id,
            'child_id'         => $otherChild->id,
            'date'             => '2026-07-01',
            'guardian_message' => '別施設の連絡帳',
        ]);

        $this->actingAs($this->user)
            ->post(route('contact-notes.publish', $note))
            ->assertStatus(403);
    }

    public function test_他施設の児童では支援記録を保存できない(): void
    {
        $otherFacility = Facility::create(['name' => '別事業所', 'capacity_per_day' => 10]);
        $otherChild    = Child::create([
            'facility_id'     => $otherFacility->id,
            'name'            => '別児童',
            'contract_status' => 'active',
        ]);

        $payload = $this->storePayload([]);
        $payload['child_id'] = $otherChild->id;

        $this->actingAs($this->user)
            ->postJson(route('support-records.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['child_id']);
    }

    // ── webhook（p-yoyaku → uCRM）───────────────────────────────────────

    private function postWebhook(array $body)
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);

        return $this->call(
            'POST',
            route('api.webhooks.yoyaku'),
            server: $this->transformHeadersToServerVars([
                'X-Yoyaku-Signature' => hash_hmac('sha256', $json, self::SECRET),
                'Content-Type'       => 'application/json',
            ]),
            content: $json,
        );
    }

    public function test_webhookで家庭側記入を受信すると連絡帳が作成される(): void
    {
        $this->postWebhook([
            'event'       => 'contact_note.guardian_entry',
            'business_id' => self::BUSINESS_ID,
            'sent_at'     => now()->toIso8601String(),
            'data'        => [
                'user_id'          => 501,
                'date'             => '2026-07-01',
                'home_temperature' => '36.5',
                'home_sleep'       => '22時〜7時',
                'guardian_comment' => '今朝は少し眠そうでした。',
            ],
        ])->assertStatus(200);

        $note = ContactNote::first();
        $this->assertNotNull($note);
        $this->assertSame(ContactNote::STATUS_DRAFT, $note->status);
        $this->assertSame('36.5', $note->home_temperature);
        $this->assertSame('今朝は少し眠そうでした。', $note->guardian_comment);
        $this->assertNotNull($note->guardian_submitted_at);
        $this->assertSame($this->facility->id, $note->facility_id);
    }

    public function test_家庭側記入が先行してもその後の支援記録保存で同じ連絡帳に合流する(): void
    {
        $this->postWebhook([
            'event'       => 'contact_note.guardian_entry',
            'business_id' => self::BUSINESS_ID,
            'sent_at'     => now()->toIso8601String(),
            'data'        => [
                'user_id'          => 501,
                'date'             => '2026-07-01',
                'guardian_comment' => '朝の連絡です。',
            ],
        ])->assertStatus(200);

        $this->actingAs($this->user)
            ->post(route('support-records.store'), $this->storePayload([
                'guardian_message' => '今日の様子です。',
            ]))
            ->assertStatus(200);

        $this->assertSame(1, ContactNote::count());
        $note = ContactNote::first();
        $this->assertSame('朝の連絡です。', $note->guardian_comment);
        $this->assertSame('今日の様子です。', $note->guardian_message);
        $this->assertNotNull($note->support_record_id);
    }

    public function test_webhookの既読は公開済み連絡帳にのみ反映される(): void
    {
        $note = ContactNote::create([
            'facility_id'      => $this->facility->id,
            'child_id'         => $this->child->id,
            'date'             => '2026-07-01',
            'guardian_message' => 'テスト',
        ]);

        $readEvent = [
            'event'       => 'contact_note.read',
            'business_id' => self::BUSINESS_ID,
            'sent_at'     => now()->toIso8601String(),
            'data'        => ['user_id' => 501, 'date' => '2026-07-01'],
        ];

        // 未公開なら無視される
        $this->postWebhook($readEvent)->assertStatus(200);
        $this->assertNull($note->fresh()->read_at);

        // 公開後は既読が記録される
        $note->update(['status' => ContactNote::STATUS_PUBLISHED, 'published_at' => now()]);
        $this->postWebhook($readEvent)->assertStatus(200);
        $this->assertNotNull($note->fresh()->read_at);
    }

    // ── 年間PDF出力 ─────────────────────────────────────────────────────

    public function test_年間PDFを出力できる(): void
    {
        ContactNote::create([
            'facility_id'      => $this->facility->id,
            'child_id'         => $this->child->id,
            'date'             => '2026-03-01',
            'guardian_message' => '今日は元気に過ごしました。',
            'status'           => ContactNote::STATUS_PUBLISHED,
            'published_at'     => now(),
        ]);

        $response = $this->actingAsLeader()
            ->get(route('contact-notes.export-yearly', ['year' => 2026, 'child_id' => $this->child->id]));

        $response->assertOk();
        $response->assertDownload();
        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'exported_contact_notes_2026',
            'auditable_id' => $this->child->id,
        ]);
    }

    public function test_連絡帳がない年はPDF出力されない(): void
    {
        $response = $this->actingAsLeader()
            ->from(route('contact-notes.index'))
            ->get(route('contact-notes.export-yearly', ['year' => 2020, 'child_id' => $this->child->id]));

        $response->assertRedirect(route('contact-notes.index'));
    }

    public function test_他施設の児童の年間PDFは出力できない(): void
    {
        $otherFacility = Facility::create(['name' => '別事業所', 'capacity_per_day' => 10]);
        $otherChild    = Child::create([
            'facility_id'     => $otherFacility->id,
            'name'            => '別児童',
            'contract_status' => 'active',
        ]);

        $this->actingAsLeader()
            ->getJson(route('contact-notes.export-yearly', ['year' => 2026, 'child_id' => $otherChild->id]))
            ->assertStatus(422);
    }

    public function test_一般スタッフは年間PDFを出力できない(): void
    {
        $this->actingAs($this->user)
            ->get(route('contact-notes.export-yearly', ['year' => 2026, 'child_id' => $this->child->id]))
            ->assertStatus(403);

        $this->actingAs($this->user)
            ->get(route('contact-notes.export-yearly-zip', ['year' => 2026]))
            ->assertStatus(403);
    }

    public function test_AI下書きは保護者同意がない児童では拒否される(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('ai-draft.contact-note', $this->child), [
                'behavior_note' => '落ち着いて過ごせた',
            ])
            ->assertStatus(403)
            ->assertJsonStructure(['error']); // 権限403ではなく同意チェックの403であること
    }

    public function test_同意済みなら一般スタッフでもAI下書きを生成できる(): void
    {
        $this->child->forceFill(['ai_draft_consented_at' => now()])->save();

        $this->mock(\App\Services\OpenAiService::class, function ($mock) {
            $mock->shouldReceive('generateContactNoteDraft')
                ->once()
                ->andReturn(['guardian_message' => '今日は元気に活動できました。']);
        });

        $this->actingAs($this->user)
            ->postJson(route('ai-draft.contact-note', $this->child), [
                'behavior_note' => '落ち着いて過ごせた',
            ])
            ->assertOk()
            ->assertJson(['guardian_message' => '今日は元気に活動できました。']);
    }

    public function test_webhookの家庭側記入は部分送信でも既存項目を消さない(): void
    {
        $this->postWebhook([
            'event'       => 'contact_note.guardian_entry',
            'business_id' => self::BUSINESS_ID,
            'sent_at'     => now()->toIso8601String(),
            'data'        => [
                'user_id'          => 501,
                'date'             => '2026-07-01',
                'home_temperature' => '36.5',
                'guardian_comment' => '朝の連絡です。',
            ],
        ])->assertStatus(200);

        // 体温を含まない再送信（コメントのみ更新）
        $this->postWebhook([
            'event'       => 'contact_note.guardian_entry',
            'business_id' => self::BUSINESS_ID,
            'sent_at'     => now()->toIso8601String(),
            'data'        => [
                'user_id'          => 501,
                'date'             => '2026-07-01',
                'guardian_comment' => '追記です。',
            ],
        ])->assertStatus(200);

        $note = ContactNote::first();
        $this->assertSame('36.5', $note->home_temperature);
        $this->assertSame('追記です。', $note->guardian_comment);
    }
}
