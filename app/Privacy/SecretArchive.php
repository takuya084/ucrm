<?php
namespace App\Privacy;
use RuntimeException;
/** Authenticated, framed streaming archive. No plaintext temporary archive is created. */
final class SecretArchive
{
    public const MAGIC="PBACKUP1\n";
    public static function write(string $path,iterable $records): void {
        if(!function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push'))throw new RuntimeException('Sodium is required for encrypted backups.');
        $key=Cipher::key('backup_key');if(hash_equals($key,Cipher::key('key')))throw new RuntimeException('Backup and data keys must differ.');
        $out=fopen($path,'xb');if(!$out)throw new RuntimeException('Backup destination must be a new file.');chmod($path,0600);
        [$state,$header]=sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        try {
            self::writeAll($out,self::MAGIC.$header);
            foreach($records as $record) {
                $plain=json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
                if(strlen($plain)>16777216)throw new RuntimeException('Backup record exceeds 16 MiB.');
                $cipher=sodium_crypto_secretstream_xchacha20poly1305_push($state,$plain,'',SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
                self::writeAll($out,pack('N',strlen($cipher)).$cipher);
            }
            $last=sodium_crypto_secretstream_xchacha20poly1305_push($state,'','',SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
            self::writeAll($out,pack('N',strlen($last)).$last);fflush($out);
        } catch(\Throwable $e){fclose($out);@unlink($path);throw $e;}
        fclose($out);sodium_memzero($state);
    }
    public static function records(string $path): \Generator {
        $in=fopen($path,'rb');if(!$in)throw new RuntimeException('Backup is unavailable.');
        try {
            if(self::readAll($in,strlen(self::MAGIC))!==self::MAGIC)throw new RuntimeException('Not an encrypted backup.');
            $state=sodium_crypto_secretstream_xchacha20poly1305_init_pull(self::readAll($in,SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES),Cipher::key('backup_key'));
            while(!feof($in)) {
                $length=unpack('Nlength',self::readAll($in,4))['length'];if($length<17||$length>16777233)throw new RuntimeException('Invalid backup frame.');
                $decoded=sodium_crypto_secretstream_xchacha20poly1305_pull($state,self::readAll($in,$length));
                if($decoded===false)throw new RuntimeException('Backup authentication failed.');
                [$plain,$tag]=$decoded;
                if($tag===SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    if($plain!==''||fread($in,1)!=='')throw new RuntimeException('Invalid backup ending.');return;
                }
                if($tag!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE)throw new RuntimeException('Invalid backup tag.');
                $record=json_decode($plain,true,512,JSON_THROW_ON_ERROR);if(!is_array($record))throw new RuntimeException('Invalid backup record.');yield $record;
            }
            throw new RuntimeException('Truncated encrypted backup.');
        } finally {fclose($in);}
    }
    private static function writeAll($stream,string $bytes): void {
        while($bytes!==''){$n=fwrite($stream,$bytes);if($n===false||$n===0)throw new RuntimeException('Backup write failed.');$bytes=substr($bytes,$n);}
    }
    private static function readAll($stream,int $length): string {
        $out='';while(strlen($out)<$length){$part=fread($stream,$length-strlen($out));if($part===false||$part==='')throw new RuntimeException('Truncated encrypted backup.');$out.=$part;}return $out;
    }
}
