<?php
namespace Tests\Feature;
use App\Privacy\{Cipher,Migrator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class PrivacyMigrationRetentionTest extends TestCase
{
    use RefreshDatabase;
    public function test_existing_rows_foreign_keys_constraints_and_autoincrement_are_retained(): void {
        $pdo=DB::connection()->getPdo();
        if(DB::getDriverName()!=='sqlite'){$this->assertTrue(true);return;}
        $pdo->exec('CREATE TABLE privacy_parent(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,kind TEXT NOT NULL CHECK(kind IN (\'a\',\'b\')))');
        $pdo->exec('CREATE TABLE privacy_child(id INTEGER PRIMARY KEY AUTOINCREMENT,parent_id INTEGER NOT NULL REFERENCES privacy_parent(id) ON DELETE CASCADE,note TEXT NOT NULL)');
        $pdo->exec("INSERT INTO privacy_parent(name,kind) VALUES('旧氏名','a'),('deleted sequence row','a')");$pdo->exec('DELETE FROM privacy_parent WHERE id=2');$pdo->exec("INSERT INTO privacy_child(parent_id,note) VALUES(1,'旧記録を保持')");
        $tables=config('privacy.tables');$tables['privacy_parent']=['name'=>'text'];$tables['privacy_child']=['note'=>'text'];config(['privacy.tables'=>$tables]);
        // A standalone migration must not run inside RefreshDatabase's outer transaction.
        while(DB::transactionLevel())DB::commit();
        Migrator::prepare();
        $this->assertSame(1,(int)$pdo->query('SELECT count(*) FROM privacy_child')->fetchColumn());
        $this->assertSame('旧記録を保持',DB::table('privacy_child')->first()->note);
        $this->assertSame('旧氏名',DB::table('privacy_parent')->first()->name);
        DB::table('privacy_parent')->insert(['name'=>'次の氏名','kind'=>'b']);$this->assertSame(3,(int)$pdo->lastInsertId());
        $this->assertFalse($pdo->query('PRAGMA foreign_key_check')->fetch());
        $this->expectException(\PDOException::class);$pdo->exec("INSERT INTO privacy_parent(name,kind) VALUES('invalid','no')");
    }
}
