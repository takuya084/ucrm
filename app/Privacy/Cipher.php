<?php
namespace App\Privacy;
use Illuminate\Encryption\Encrypter;
use RuntimeException;

final class Cipher
{
    public const PREFIX='pii:v1:';
    public static function key(string $name): string {
        $value=(string) config('privacy.'.$name);
        $raw=str_starts_with($value,'base64:')?base64_decode(substr($value,7),true):false;
        if (!is_string($raw)||strlen($raw)!==32) throw new RuntimeException('Privacy key missing or invalid: '.$name);
        return $raw;
    }
    private static function encrypter(string $purpose): Encrypter {
        return new Encrypter(hash_hkdf('sha256',self::key('key'),32,'personal-data/'.$purpose),'AES-256-GCM');
    }
    public static function encrypt(string $context, mixed $value): string {
        if ($value instanceof \DateTimeInterface) $value=$value->format('Y-m-d H:i:s');
        return self::PREFIX.self::encrypter('database')->encryptString(json_encode(['context'=>$context,'value'=>$value],JSON_THROW_ON_ERROR));
    }
    public static function decrypt(string $context, mixed $value): mixed {
        if ($value===null) return null;
        if (!is_string($value)||!str_starts_with($value,self::PREFIX)) throw new RuntimeException('Unencrypted personal data detected; run privacy:migrate before opening the application.');
        try {
            $decoded=json_decode(self::encrypter('database')->decryptString(substr($value,strlen(self::PREFIX))),true,512,JSON_THROW_ON_ERROR);
            if (($decoded['context']??null)!==$context || !array_key_exists('value',$decoded)) throw new RuntimeException();
            return $decoded['value'];
        } catch (\Throwable) {throw new RuntimeException('Personal data authentication failed.');}
    }
    public static function decryptProjection(string $value, ?string $expected=null): mixed {
        if ($expected!==null) return self::decrypt($expected,$value);
        try {
            $decoded=json_decode(self::encrypter('database')->decryptString(substr($value,strlen(self::PREFIX))),true,512,JSON_THROW_ON_ERROR);
            [$table,$column]=explode('.',(string)($decoded['context']??''),2);
            if (!Registry::protected($table,$column)) throw new RuntimeException();
            return $decoded['value'];
        } catch (\Throwable) {throw new RuntimeException('Personal data projection authentication failed.');}
    }
    public static function index(string $context,mixed $value): ?string {
        if ($value===null) return null;
        if ($value instanceof \DateTimeInterface) $value=$value->format('Y-m-d H:i:s');
        if (is_bool($value)) $value=(int)$value;
        $parts=explode('.',$context,2);$type=Registry::fields($parts[0])[$parts[1]??'']??'text';
        if($type==='date')$value=substr((string)$value,0,10);
        if($type==='number'&&is_numeric($value))$value=rtrim(rtrim(bcadd((string)$value,'0',8),'0'),'.');
        $value=mb_strtolower(trim((string)$value),'UTF-8');
        if (str_ends_with($context,'.email')||str_ends_with($context,'.active_email')) $value=trim($value);
        return hash_hmac('sha256',$context."\0".$value,self::key('index_key'));
    }
    public static function seal(string $purpose,string $value): string {
        return 'sealed:v1:'.self::encrypter($purpose)->encryptString($value);
    }
    public static function unseal(string $purpose,string $value): string {
        if (!str_starts_with($value,'sealed:v1:')) throw new RuntimeException('Unencrypted private file/cache detected.');
        try {return self::encrypter($purpose)->decryptString(substr($value,10));}
        catch (\Throwable) {throw new RuntimeException('Private file/cache authentication failed.');}
    }
}
