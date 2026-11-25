<?php
namespace App\Privacy;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
/** ZIP STORE archive assembled only in RAM; persistent output goes through encrypted storage. */
final class PrivateZip
{
    private string $path;private array $entries=[];private int $bytes=0;
    public function open(string $path,int $flags=0): bool {$this->path=$path;return true;}
    public function addFile(string $path,string $name): bool {return $this->addFromString($name,Storage::disk('local')->get($path));}
    public function addFromString(string $name,string $bytes): bool {
        $name=preg_replace('~[\\\\/\x00-\x1f]~u','_',$name);
        if(!$name||isset($this->entries[$name]))throw new RuntimeException('Invalid or duplicate ZIP entry.');
        $this->bytes+=strlen($bytes);if($this->bytes>50331648)throw new RuntimeException('Private ZIP exceeds 48 MiB.');
        $this->entries[$name]=$bytes;return true;
    }
    public function close(): bool {
        $body='';$central='';$offset=0;$flags=0x0800;
        foreach($this->entries as $name=>$bytes) {
            $size=strlen($bytes);$crc=crc32($bytes);$length=strlen($name);
            $local=pack('VvvvvvVVVvv',0x04034b50,20,$flags,0,0,0,$crc,$size,$size,$length,0).$name.$bytes;
            $central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,$flags,0,0,0,$crc,$size,$size,$length,0,0,0,0,0,$offset).$name;
            $body.=$local;$offset+=strlen($local);
        }
        $n=count($this->entries);$archive=$body.$central.pack('VvvvvVVv',0x06054b50,0,0,$n,$n,strlen($central),strlen($body),0);
        return Storage::disk('local')->put($this->path,$archive);
    }
}
