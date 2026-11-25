<?php
namespace App\Privacy;
use Illuminate\Filesystem\FilesystemAdapter;
use RuntimeException;
class PrivateFilesystem extends FilesystemAdapter
{
    public function put($path,$contents,$options=[]) {
        if($contents instanceof \Illuminate\Http\File||$contents instanceof \Illuminate\Http\UploadedFile)return $this->putFile($path,$contents,$options);
        if(is_resource($contents)){$contents=stream_get_contents($contents,67108865);}
        if(!is_string($contents)||strlen($contents)>67108864)throw new RuntimeException('Private attachment exceeds 64 MiB.');
        return parent::put($path,Cipher::seal('files',$contents),$options);
    }
    public function get($path) {
        $stored=parent::get($path);return $stored===null?null:Cipher::unseal('files',$stored);
    }
    public function readStream($path) {
        $value=$this->get($path);$stream=fopen('php://memory','w+b');fwrite($stream,$value);rewind($stream);return $stream;
    }
    public function writeStream($path,$resource,array $options=[]) {return $this->put($path,$resource,$options);}
    public function size($path) {return strlen($this->get($path));}
    public function mimeType($path) {$f=new \finfo(FILEINFO_MIME_TYPE);return $f->buffer($this->get($path))?:'application/octet-stream';}
    public function path($path) {throw new RuntimeException('Private files must be accessed through the authorized storage stream, never by a plaintext filesystem path.');}
    public function url($path) {throw new RuntimeException('Private files do not have public URLs.');}
}
