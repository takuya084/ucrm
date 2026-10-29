<?php

namespace Tests\Feature;

use App\Models\{Child, Facility, Staff, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationLinkCreationTest extends TestCase
{
    use RefreshDatabase;
    private Facility $facility;
    private Child $a;
    private Child $b;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.houkago_plus.base_url' => 'https://synthetic.example.test']);
        $this->facility = $this->facility(77);
        $user = User::factory()->create();
        Staff::create(['user_id' => $user->id, 'facility_id' => $this->facility->id,
            'name' => 'Synthetic admin', 'role' => 'admin', 'is_active' => true]);
        $this->actingAs($user);
        $this->a = $this->child();
        $this->b = $this->child();
    }

    private function facility(int $business): Facility
    {
        return Facility::create(['name' => 'Synthetic facility', 'capacity_per_day' => 10,
            'billing_type' => 'free', 'is_active' => true, 'yoyaku_business_id' => $business, 'yoyaku_api_token' => 'synthetic-token']);
    }
    private function child(?int $id = null, ?Facility $facility = null): Child
    {
        return Child::create(['facility_id' => ($facility ?? $this->facility)->id,
            'name' => 'Synthetic child', 'contract_status' => 'active', 'yoyaku_user_id' => $id]);
    }
    private function directory(array $users = []): void
    {
        Http::fake(['*' => Http::response($users ?: [
            ['id' => 501, 'email' => 'one@example.test'], ['id' => 502, 'email' => 'two@example.test'],
        ])]);
    }
    private function csv(string $body)
    {
        return $this->post('/children/yoyaku-link/csv', ['csv_file' => UploadedFile::fake()->createWithContent('links.csv', $body)]);
    }
    private function createAccounts(array $ids)
    {
        return $this->post('/children/yoyaku-link/create', ['child_ids' => $ids]);
    }

    public function test_child_registration_and_update_reject_same_facility_duplicate_without_partial_edits(): void
    {
        $this->a->update(['yoyaku_user_id' => 501]);
        $this->postJson('/children', ['name' => 'Must not create', 'contract_status' => 'active', 'yoyaku_user_id' => 501])
            ->assertUnprocessable()->assertJsonValidationErrors('yoyaku_user_id');
        $this->patchJson('/children/'.$this->b->id, ['name' => 'Must not edit', 'contract_status' => 'active', 'yoyaku_user_id' => 501])
            ->assertUnprocessable()->assertJsonValidationErrors('yoyaku_user_id');
        $this->assertSame('Synthetic child', $this->b->fresh()->name);
        $this->assertNull($this->b->fresh()->yoyaku_user_id);
        $this->assertDatabaseCount('children', 2);
    }

    public function test_existing_self_link_can_be_saved_and_other_facility_numbers_are_allowed(): void
    {
        $this->a->update(['yoyaku_user_id' => 501]);
        $this->child(501, $this->facility(88));
        $this->patch('/children/'.$this->a->id, ['name' => 'Synthetic edited', 'contract_status' => 'active', 'yoyaku_user_id' => 501])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/children', ['name' => 'Synthetic new', 'contract_status' => 'active', 'yoyaku_user_id' => 502])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Synthetic edited', $this->a->fresh()->name);
    }

    public function test_deleted_mapping_does_not_block_a_new_active_child(): void
    {
        $this->a->update(['yoyaku_user_id' => 501]);
        $this->a->delete();
        $this->post('/children', ['name' => 'Synthetic replacement', 'contract_status' => 'active', 'yoyaku_user_id' => 501])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, Child::where('yoyaku_user_id', 501)->count());
    }

    public function test_csv_links_by_id_and_email_and_is_idempotent(): void
    {
        $this->directory();
        $body = "child_id,yoyaku_user_id,yoyaku_email\n{$this->a->id},501,\n{$this->b->id},,TWO@EXAMPLE.TEST\n";
        $this->csv($body)->assertSessionHas('status', 'success');
        $this->csv($body)->assertSessionHas('status', 'success');
        $this->assertSame(501, (int) $this->a->fresh()->yoyaku_user_id);
        $this->assertSame(502, (int) $this->b->fresh()->yoyaku_user_id);
    }

    public function test_duplicate_child_or_user_in_csv_aborts_all_rows(): void
    {
        $this->directory();
        foreach (["{$this->a->id},501\n{$this->a->id},502", "{$this->a->id},501\n{$this->b->id},501"] as $rows) {
            $this->csv("child_id,yoyaku_user_id\n{$rows}\n")->assertSessionHas('import_errors');
            $this->assertNull($this->a->fresh()->yoyaku_user_id);
            $this->assertNull($this->b->fresh()->yoyaku_user_id);
        }
    }

    public function test_csv_cannot_take_a_link_from_a_child_outside_the_batch(): void
    {
        $owner = $this->child(502);
        $this->directory();
        $this->csv("child_id,yoyaku_user_id\n{$this->a->id},501\n{$this->b->id},502\n")->assertSessionHas('import_errors');
        $this->assertNull($this->a->fresh()->yoyaku_user_id);
        $this->assertNull($this->b->fresh()->yoyaku_user_id);
        $this->assertSame(502, (int) $owner->fresh()->yoyaku_user_id);
    }

    public function test_csv_id_email_conflict_and_malformed_id_do_not_fall_back(): void
    {
        $this->directory();
        foreach (['501,two@example.test', 'invalid,one@example.test', '0,one@example.test'] as $row) {
            $this->csv("child_id,yoyaku_user_id,yoyaku_email\n{$this->a->id},{$row}\n")->assertSessionHas('import_errors');
            $this->assertNull($this->a->fresh()->yoyaku_user_id);
        }
    }

    public function test_ambiguous_or_malformed_remote_directory_is_rejected(): void
    {
        foreach ([ [['id'=>501,'email'=>'same@example.test'],['id'=>502,'email'=>'same@example.test']],
            [['id'=>501],['id'=>501]], [['name'=>'Missing ID']], ['error'=>'Unexpected'] ] as $users) {
            Http::fake(['*' => Http::response($users)]);
            $this->csv("child_id,yoyaku_user_id\n{$this->a->id},501\n")->assertSessionHasErrors('csv_file');
            $this->assertNull($this->a->fresh()->yoyaku_user_id);
        }
    }

    public function test_csv_foreign_and_deleted_children_are_rejected(): void
    {
        $foreign = $this->child(null, $this->facility(88));
        $this->b->delete();
        $this->directory();
        foreach ([$foreign->id, $this->b->id] as $id) {
            $this->csv("child_id,yoyaku_user_id\n{$this->a->id},501\n{$id},502\n")->assertSessionHas('import_errors');
            $this->assertNull($this->a->fresh()->yoyaku_user_id);
        }
    }

    public function test_csv_changed_child_during_http_is_preserved(): void
    {
        Http::fake(function () {
            $this->b->update(['yoyaku_user_id' => 503]);
            return Http::response([['id'=>501],['id'=>502]]);
        });
        $this->csv("child_id,yoyaku_user_id\n{$this->a->id},501\n{$this->b->id},502\n")->assertSessionHas('import_errors');
        $this->assertNull($this->a->fresh()->yoyaku_user_id);
        $this->assertSame(503, (int) $this->b->fresh()->yoyaku_user_id);
    }

    public function test_csv_changed_facility_during_http_is_rejected(): void
    {
        Http::fake(function () {
            $this->facility->update(['yoyaku_api_token' => 'synthetic-new-token']);
            return Http::response([['id'=>501]]);
        });
        $this->csv("child_id,yoyaku_user_id\n{$this->a->id},501\n")->assertSessionHas('import_errors');
        $this->assertNull($this->a->fresh()->yoyaku_user_id);
    }

    public function test_csv_headers_and_column_mismatches_are_rejected_before_http(): void
    {
        foreach (["child_id,child_id,yoyaku_user_id\n1,2,501", "name,yoyaku_user_id\nSynthetic,501",
            "child_id,yoyaku_user_id\n1,501,extra"] as $csv) {
            $this->csv($csv)->assertSessionHasErrors('csv_file');
        }
        Http::assertNothingSent();
    }

    public function test_account_creation_does_not_store_a_colliding_result_or_expose_credentials(): void
    {
        $owner = $this->child(501);
        Http::fake(['*'=>Http::response(['id'=>501,'email'=>'private@example.test','password'=>'SYNTHETIC-CONFLICT-PASSWORD'])]);
        $this->createAccounts([$this->a->id])->assertSessionHas('status', 'warning')
            ->assertSessionHas('yoyaku_link_results', fn ($rows) => $rows[0]['ok'] === false && !isset($rows[0]['password']) && !isset($rows[0]['email']));
        $this->assertNull($this->a->fresh()->yoyaku_user_id);
        $this->assertSame(501, (int) $owner->fresh()->yoyaku_user_id);
    }

    public function test_account_batch_keeps_per_child_results_if_api_returns_same_id_twice(): void
    {
        Http::fake(['*'=>Http::response(['id'=>501,'email'=>'synthetic@example.test'])]);
        $this->createAccounts([$this->a->id, $this->b->id])->assertSessionHas('status', 'warning')
            ->assertSessionHas('yoyaku_link_results', fn ($rows) => count($rows)===2 && $rows[0]['ok'] && !$rows[1]['ok']);
        $this->assertSame(1, Child::where('yoyaku_user_id', 501)->count());
    }

    public function test_valid_account_creation_uses_scoped_token_and_external_ref(): void
    {
        Http::fake(['*'=>Http::response(['id'=>501,'email'=>'synthetic@example.test','password'=>'SYNTHETIC-NEW-PASSWORD'])]);
        $this->createAccounts([$this->a->id])->assertSessionHas('status', 'success');
        $this->assertSame(501, (int) $this->a->fresh()->yoyaku_user_id);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization','Bearer synthetic-token') && $r['external_ref']==='ucrm:'.$this->a->id);
        $this->createAccounts([$this->a->id])->assertSessionHas('status', 'success');
        Http::assertSentCount(1);
    }

    public function test_account_creation_preserves_a_link_changed_during_http(): void
    {
        Http::fake(function () {
            $this->a->update(['yoyaku_user_id'=>502]);
            return Http::response(['id'=>501,'password'=>'SYNTHETIC-CONFLICT-PASSWORD']);
        });
        $this->createAccounts([$this->a->id])->assertSessionHas('status', 'warning');
        $this->assertSame(502, (int) $this->a->fresh()->yoyaku_user_id);
    }

    public function test_account_creation_rejects_bad_id_wrong_business_or_changed_facility(): void
    {
        foreach ([['id'=>0],['id'=>'invalid'],['id'=>501,'business_id'=>88]] as $response) {
            Http::fake(['*'=>Http::response($response)]);
            $this->createAccounts([$this->a->id])->assertSessionHas('status','warning');
            $this->assertNull($this->a->fresh()->yoyaku_user_id);
        }
        Http::fake(function () {
            $this->facility->update(['yoyaku_business_id'=>88]);
            return Http::response(['id'=>501]);
        });
        $this->createAccounts([$this->a->id])->assertSessionHas('status','warning');
        $this->assertNull($this->a->fresh()->yoyaku_user_id);
    }

    public function test_account_selection_rejects_foreign_deleted_and_duplicate_children_before_http(): void
    {
        $foreign = $this->child(null, $this->facility(88));
        $this->b->delete();
        foreach ([[$this->a->id,$foreign->id], [$this->b->id], [$this->a->id,$this->a->id]] as $ids) {
            $this->postJson('/children/yoyaku-link/create',['child_ids'=>$ids])->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_duplicate_business_blocks_linking_and_settings_reject_a_duplicate_number(): void
    {
        $other = $this->facility(88);
        $this->patchJson('/facility',['name'=>'Synthetic','yoyaku_business_id'=>88])->assertUnprocessable()->assertJsonValidationErrors('yoyaku_business_id');
        $other->update(['yoyaku_business_id'=>77]);
        $this->postJson('/children/yoyaku-link/create',['child_ids'=>[$this->a->id]])->assertUnprocessable();
        $this->csv("child_id,yoyaku_user_id\n{$this->a->id},501\n")->assertSessionHasErrors('yoyaku_user_id');
        Http::assertNothingSent();
    }
}
