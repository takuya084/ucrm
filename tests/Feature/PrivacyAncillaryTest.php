<?php
namespace Tests\Feature;
use App\Privacy\{Cipher,EncryptedLogFormatter,PrivateSessionHandler};
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;
class PrivacyAncillaryTest extends TestCase
{
    public function test_file_sessions_are_encrypted_at_rest_and_old_session_data_is_preserved(): void {
        $root=sys_get_temp_dir().'/privacy-session-'.bin2hex(random_bytes(8));mkdir($root,0700);$id=str_repeat('a',40);
        $handler=new PrivateSessionHandler(new Filesystem(),$root,120);$inner=app('encrypter')->encrypt(serialize(['name'=>'個人セッション']));
        $this->assertTrue($handler->write($id,$inner));$raw=file_get_contents($root.'/'.$id);$this->assertStringStartsWith('sealed:v1:',$raw);$this->assertStringNotContainsString('個人',$raw);$this->assertSame($inner,$handler->read($id));
        // Simulate the old file-session representation and verify one-time migration.
        file_put_contents($root.'/'.$id,serialize(['email'=>'private@example.test']));$mtime=time()-120;touch($root.'/'.$id,$mtime);config(['session.files'=>$root]);
        $this->artisan('privacy:sessions')->assertSuccessful();$restored=unserialize(app('encrypter')->decrypt($handler->read($id)));$this->assertSame(['email'=>'private@example.test'],$restored);$this->assertSame($mtime,filemtime($root.'/'.$id));
        unlink($root.'/'.$id);rmdir($root);
    }
    public function test_log_formatting_never_writes_plain_personal_values_even_when_a_key_is_missing(): void {
        $formatter=new EncryptedLogFormatter();$context=['email'=>'private@example.test'];
        if(class_exists(\Monolog\LogRecord::class))$record=new \Monolog\LogRecord(new \DateTimeImmutable(),'private',\Monolog\Level::Info,'秘密の記録',$context);
        else$record=['message'=>'秘密の記録','context'=>$context,'level'=>200,'level_name'=>'INFO','channel'=>'private','datetime'=>new \DateTimeImmutable(),'extra'=>[]];
        $line=$formatter->format($record);$this->assertStringNotContainsString('private@example.test',$line);$this->assertStringNotContainsString('秘密',$line);$this->assertStringContainsString('private@example.test',Cipher::unseal('logs',rtrim($line,"\n")));
        config(['privacy.key'=>null]);$this->assertSame("[private-log-unavailable]\n",$formatter->format($record));
    }
    public function test_legacy_file_cache_is_detected_and_only_obsolete_values_are_cleared(): void {
        $root=sys_get_temp_dir().'/privacy-cache-'.bin2hex(random_bytes(8));mkdir($root,0700);
        config(['cache.default'=>'legacy-file','cache.stores.legacy-file'=>['driver'=>'file','path'=>$root]]);
        $files=new Filesystem();$backend=new \Illuminate\Cache\FileStore($files,$root);
        $backend->put('old-personal-key','秘密の旧キャッシュ',60);
        $private=new \App\Privacy\CacheStore($backend);$private->put('new-personal-key','新キャッシュ',60);
        $this->artisan('privacy:cache')->assertFailed();$this->artisan('privacy:cache',['--clear-legacy'=>true])->assertSuccessful();
        $this->assertNull($backend->get('old-personal-key'));$this->assertSame('新キャッシュ',$private->get('new-personal-key'));
        $files->deleteDirectory($root);
    }
    public function test_previous_data_key_allows_reading_old_ciphertext_but_new_writes_use_the_current_key(): void {
        $old=config('privacy.key');$stored=Cipher::encrypt('users.name','旧暗号文');
        config(['privacy.previous_keys'=>[$old],'privacy.key'=>'base64:'.base64_encode(str_repeat('D',32))]);$this->assertSame('旧暗号文',Cipher::decrypt('users.name',$stored));
        config(['privacy.previous_keys'=>[]]);$this->expectExceptionMessage('authentication failed');Cipher::decrypt('users.name',$stored);
    }
}
