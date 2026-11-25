<?php
namespace Tests\Feature;
use App\Models\User;
use App\Privacy\{Backup,CacheStore,Cipher,Files,PrivateFilesystem,PrivateZip,SecretArchive};
use Illuminate\Cache\ArrayStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;
class PrivacyFilesBackupTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    protected function setUp(): void {
        parent::setUp();$this->root=sys_get_temp_dir().'/privacy-test-'.bin2hex(random_bytes(8));mkdir($this->root,0700);
        config(['filesystems.disks.local'=>['driver'=>'private-local','root'=>$this->root.'/files','throw'=>true]]);Storage::forgetDisk('local');
    }
    protected function tearDown(): void {
        Storage::forgetDisk('local');$this->remove($this->root);parent::tearDown();
    }
    private function remove(string $path): void {if(!file_exists($path))return;if(is_dir($path)){foreach(array_diff(scandir($path),['.','..']) as $f)$this->remove($path.'/'.$f);rmdir($path);}else unlink($path);}
    public function test_private_files_and_zip_are_ciphertext_at_rest_but_authorized_reads_return_original_bytes(): void {
        $disk=Storage::disk('local');$this->assertInstanceOf(PrivateFilesystem::class,$disk);
        $bytes="%PDF-1.7\n秘密の児童と受給者証\n";$disk->put('record.pdf',$bytes);
        $raw=file_get_contents($this->root.'/files/record.pdf');$this->assertStringStartsWith('sealed:v1:',$raw);$this->assertStringNotContainsString('秘密',$raw);
        $this->assertSame($bytes,$disk->get('record.pdf'));$this->assertSame(strlen($bytes),$disk->size('record.pdf'));
        $this->assertSame($bytes,stream_get_contents($disk->readStream('record.pdf')));
        $zip=new PrivateZip();$zip->open('records.zip');$zip->addFile('record.pdf','記録.pdf');$zip->close();
        $zipBytes=$disk->get('records.zip');$this->assertStringStartsWith("PK\x03\x04",$zipBytes);
        // Validate the in-memory ZIP via a disposable test-only file, never application storage.
        $tmp=$this->root.'/test-only.zip';file_put_contents($tmp,$zipBytes);$check=new \ZipArchive();$this->assertTrue($check->open($tmp));$this->assertSame($bytes,$check->getFromName('記録.pdf'));$check->close();
        $this->assertStringStartsWith('sealed:v1:',file_get_contents($this->root.'/files/records.zip'));
    }
    public function test_plain_legacy_files_migrate_without_losing_bytes_and_private_paths_are_refused(): void {
        mkdir($this->root.'/files',0700,true);file_put_contents($this->root.'/files/legacy.pdf','旧資料の保持');
        $this->assertSame(1,Files::migrate(true)['plaintext']);$this->assertSame(1,Files::migrate()['encrypted']);
        $this->assertSame('旧資料の保持',Storage::disk('local')->get('legacy.pdf'));$this->assertSame(0,Files::migrate(true)['plaintext']);
        $this->expectExceptionMessage('never by a plaintext filesystem path');Storage::disk('local')->path('legacy.pdf');
    }
    public function test_backup_is_authenticated_contains_no_plain_personal_data_and_restores_to_an_empty_clone(): void {
        $user=User::factory()->create(['name'=>'暗号化バックアップ氏名','email'=>'backup-private@example.test']);
        Storage::disk('local')->put('record.txt','バックアップ個人資料');$backup=$this->root.'/backup.enc';Backup::create($backup);
        $raw=file_get_contents($backup);$this->assertStringStartsWith(SecretArchive::MAGIC,$raw);$this->assertStringNotContainsString('暗号化バックアップ',$raw);$this->assertStringNotContainsString('バックアップ個人資料',$raw);
        $target=new \PDO('sqlite:'.$this->root.'/clone.sqlite');Backup::restore($backup,$target,$this->root.'/restored-files');
        $stored=$target->query('SELECT name FROM users WHERE id='.(int)$user->id)->fetchColumn();$this->assertSame($user->name,Cipher::decrypt('users.name',$stored));
        $this->assertSame('バックアップ個人資料',Cipher::unseal('files',file_get_contents($this->root.'/restored-files/record.txt')));
        $this->expectExceptionMessage('empty target database');Backup::restore($backup,$target,$this->root.'/not-used');
    }
    public function test_truncated_backup_is_rejected_before_creating_any_target_table(): void {
        User::factory()->create();Storage::disk('local')->put('record.txt','private');$backup=$this->root.'/backup.enc';Backup::create($backup);
        $bytes=file_get_contents($backup);file_put_contents($backup,substr($bytes,0,-8));$target=new \PDO('sqlite:'.$this->root.'/clone.sqlite');
        try{Backup::restore($backup,$target,$this->root.'/restored');$this->fail('truncated backup accepted');}catch(\RuntimeException $e){$this->assertStringContainsString('Truncated',$e->getMessage());}
        $this->assertSame(0,(int)$target->query("SELECT count(*) FROM sqlite_master WHERE type='table'")->fetchColumn());
    }
    public function test_cache_encrypts_values_and_keys_and_preserves_atomic_counters(): void {
        $backend=new ArrayStore();$store=new CacheStore($backend);$this->assertTrue($store->put('email=private@example.test',['name'=>'秘密の氏名'],30));
        $stored=$backend->get(Cipher::index('cache.key','email=private@example.test'));$this->assertStringStartsWith('sealed:v1:',$stored);$this->assertStringNotContainsString('秘密',$stored);
        $this->assertSame(['name'=>'秘密の氏名'],$store->get('email=private@example.test'));
        $this->assertTrue($store->add('counter',1,30));$this->assertFalse($store->add('counter',99,30));$this->assertSame(2,$store->increment('counter'));$this->assertSame(1,$store->decrement('counter'));
    }
}
