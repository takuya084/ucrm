<?php
namespace Tests\Feature;
use App\Models\User;
use App\Privacy\Cipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class PrivacyStorageTest extends TestCase
{
    use RefreshDatabase;
    public function test_orm_and_direct_builder_encrypt_at_rest_and_hide_lookup_columns(): void {
        $u=User::factory()->create(['name'=>'秘密の氏名ABC','email'=>'private-person@example.test']);
        $pdo=DB::connection()->getPdo();$raw=$pdo->query('SELECT * FROM users WHERE id='.(int)$u->id)->fetch(\PDO::FETCH_ASSOC);
        $this->assertStringStartsWith(Cipher::PREFIX,$raw['name']);$this->assertStringNotContainsString('秘密',$raw['name']);
        $this->assertStringStartsWith(Cipher::PREFIX,$raw['email']);$this->assertNotSame($raw['email'],$raw['pii_i_email']);
        $this->assertSame('秘密の氏名ABC',DB::table('users')->where('id',$u->id)->value('name'));
        $this->assertSame($u->id,User::where('email','PRIVATE-PERSON@example.test')->firstOrFail()->id);
        DB::table('users')->where('id',$u->id)->update(['name'=>'更新した氏名','email'=>'changed@example.test']);
        $this->assertSame($u->id,User::where('email','changed@example.test')->firstOrFail()->id);
        $this->assertNull(User::where('email','private-person@example.test')->first());
        $this->assertSame('更新した氏名',DB::table('users')->where('id',$u->id)->select('name as display_name')->first()->display_name);
        $this->assertArrayNotHasKey('pii_i_email',$u->fresh()->toArray());
    }
    public function test_private_partial_search_and_sort_return_the_same_logical_records(): void {
        User::factory()->create(['name'=>'BBB 秘密','email'=>'b@example.test']);
        $a=User::factory()->create(['name'=>'AAA 秘密','email'=>'a@example.test']);
        $names=User::where('name','like','%秘密%')->orderBy('name')->pluck('name')->all();
        $this->assertSame(['AAA 秘密','BBB 秘密'],$names);
        $this->assertSame($a->id,User::whereIn('email',['a@example.test'])->firstOrFail()->id);
    }
    public function test_ciphertext_tampering_or_wrong_context_is_refused_without_exposing_content(): void {
        $u=User::factory()->create(['name'=>'秘密の名前']);$pdo=DB::connection()->getPdo();
        $pdo->exec('UPDATE users SET email=name WHERE id='.(int)$u->id);
        $this->expectExceptionMessage('Personal data authentication failed.');$u->fresh();
    }
    public function test_plaintext_is_detected_and_cannot_be_read_as_an_encrypted_value(): void {
        $u=User::factory()->create();$pdo=DB::connection()->getPdo();$s=$pdo->prepare('UPDATE users SET name=? WHERE id=?');$s->execute(['平文の検査',$u->id]);
        $this->artisan('privacy:verify')->assertFailed();
        $this->expectExceptionMessage('Unencrypted personal data detected.');$u->fresh();
    }
    public function test_schema_setup_requires_an_empty_database_and_does_not_change_existing_records(): void {
        $u=User::factory()->create(['name'=>'新規登録の個人情報']);$pdo=DB::connection()->getPdo();$before=$pdo->query('SELECT name FROM users WHERE id='.(int)$u->id)->fetchColumn();
        try {\App\Privacy\SchemaSetup::prepare();$this->fail('Nonempty database was accepted.');}
        catch(\RuntimeException $e){$this->assertStringContainsString('Fresh database required.',$e->getMessage());}
        $this->assertSame($before,$pdo->query('SELECT name FROM users WHERE id='.(int)$u->id)->fetchColumn());$this->assertSame('新規登録の個人情報',$u->fresh()->name);
        $this->artisan('privacy:verify')->assertSuccessful();
    }
    public function test_duplicate_email_is_refused_even_though_ciphertexts_are_randomized(): void {
        User::factory()->create(['email'=>'same-person@example.test']);
        $this->expectException(\Illuminate\Database\QueryException::class);User::factory()->create(['email'=>'SAME-PERSON@example.test']);
    }
    public function test_required_personal_columns_remain_required_after_migration(): void {
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['name'=>null]);
    }
    public function test_missing_application_key_refuses_personal_writes(): void {
        config(['app.key'=>null]);$this->expectExceptionMessage('APP_KEY is missing or invalid.');User::factory()->create();
    }
}
