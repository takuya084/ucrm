<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

class SqliteLiveCopyCheckTest extends TestCase
{
    private string $root;
    private ?\PDO $writer=null;
    protected function setUp(): void {
        require_once dirname(__DIR__,2).'/tools/manual-demo/sqlite_copy_check.php';
        $this->root=sys_get_temp_dir().'/sqlite-copy-check-'.bin2hex(random_bytes(8));mkdir($this->root,0700);mkdir($this->root.'/source',0700);
    }
    protected function tearDown(): void {
        chmod($this->root.'/source',0700);
        $this->writer=null;
        $this->remove($this->root);
    }
    private function remove(string $path): void {
        if (is_dir($path)) {chmod($path,0700);foreach (array_diff(scandir($path),['.','..']) as $name) $this->remove($path.'/'.$name);rmdir($path);}
        else unlink($path);
    }
    private function fixture(bool $wal): array {
        $this->writer=new \PDO('sqlite:'.$this->root.'/source/database.sqlite',null,null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        if ($wal) $this->writer->exec('PRAGMA journal_mode=WAL');
        $this->writer->exec('CREATE TABLE records(id INTEGER PRIMARY KEY,value TEXT)');
        $this->writer->exec("INSERT INTO records VALUES(1,'retained existing data')");
        $this->writer->exec('VACUUM INTO '.$this->writer->quote($this->root.'/source/reference.sqlite'));
        $names=['database.sqlite','reference.sqlite'];
        if ($wal) $names[]='database.sqlite-wal';
        $expected=[];foreach ($names as $name) $expected[$name]=hash_file('sha256',$this->root.'/source/'.$name);
        return ['source'=>$this->root.'/source','workspace'=>$this->root.'/check','reference'=>'reference.sqlite','expected'=>$expected];
    }
    public function test_live_wal_is_checked_in_private_copy_without_writing_original_database(): void {
        $config=$this->fixture(true);
        $this->assertGreaterThan(0,filesize($config['source'].'/database.sqlite-wal'));
        chmod($config['source'],0500);
        $result=\shift_verify_sqlite_copy($config);
        $this->assertTrue($result['ok']);
        foreach ($config['expected'] as $name=>$sha) $this->assertSame($sha,hash_file('sha256',$config['source'].'/'.$name));
    }
    public function test_non_wal_database_is_checked_without_host_sqlite_or_source_changes(): void {
        $config=$this->fixture(false);$result=\shift_verify_sqlite_copy($config);$this->assertTrue($result['ok']);
        foreach ($config['expected'] as $name=>$sha) $this->assertSame($sha,hash_file('sha256',$config['source'].'/'.$name));
    }
    public function test_actual_live_changes_are_refused(): void {
        $config=$this->fixture(true);$this->writer->exec("UPDATE records SET value='unrelated change'");
        foreach (array_keys($config['expected']) as $name) $config['expected'][$name]=hash_file('sha256',$config['source'].'/'.$name);
        $this->expectExceptionMessage('live_database_differs_from_verified_snapshot');\shift_verify_sqlite_copy($config);
    }
    public function test_wrong_source_hash_is_refused_before_copying(): void {
        $config=$this->fixture(false);$config['expected']['database.sqlite']=str_repeat('0',64);
        $this->expectExceptionMessage('source_file_mismatch');\shift_verify_sqlite_copy($config);
    }
}
