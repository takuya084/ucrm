<?php
namespace Tests\Feature;
use App\Privacy\{Cipher,Migrator};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Schema};
use Tests\TestCase;
class PrivacyLegacyRowsTest extends TestCase
{
    use RefreshDatabase;
    public function test_legacy_rows_without_a_primary_key_keep_duplicates_and_exact_values(): void {
        Schema::create('privacy_legacy_reset',function(Blueprint $table){$table->string('email')->index();$table->string('token');});
        $pdo=DB::connection()->getPdo();$stmt=$pdo->prepare('INSERT INTO '.Migrator::quote('privacy_legacy_reset').' (email,token) VALUES (?,?)');
        foreach(['KeepX@example.test','KeepX@example.test','keepx@example.test'] as $email)$stmt->execute([$email,'synthetic-hash']);
        $tables=config('privacy.tables');$tables['privacy_legacy_reset']=['email'=>'text'];config(['privacy.tables'=>$tables]);
        while(DB::transactionLevel())DB::commit();
        try {
            Migrator::prepare();$values=DB::table('privacy_legacy_reset')->pluck('email')->all();sort($values);
            $this->assertSame(['KeepX@example.test','KeepX@example.test','keepx@example.test'],$values);
            $this->assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM privacy_legacy_reset')->fetchColumn());
            foreach($pdo->query('SELECT email FROM privacy_legacy_reset')->fetchAll(\PDO::FETCH_COLUMN) as $raw)$this->assertStringStartsWith(Cipher::PREFIX,$raw);
            $this->assertSame(0,Migrator::scan()['plaintext']);
        }finally{Schema::dropIfExists('privacy_legacy_reset');DB::beginTransaction();}
    }
}
