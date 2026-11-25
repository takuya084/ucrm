<?php
namespace App\Privacy;
use Illuminate\Console\Command;
use RuntimeException;
class SessionsCommand extends Command
{
    protected $signature='privacy:sessions {--verify}';
    protected $description='Seal legacy file sessions, preserving their data and expiry';
    public function handle(): int {
        $counts=['encrypted'=>0,'verified'=>0,'plaintext'=>0];$root=config('session.files');
        if(!is_dir($root)){$this->line(json_encode($counts));return self::SUCCESS;}
        foreach(glob($root.'/*')?:[] as $file){
            if(is_link($file))throw new RuntimeException('Session links are not allowed.');if(!is_file($file)||!preg_match('/^[A-Za-z0-9]{40}$/D',basename($file)))continue;
            $raw=file_get_contents($file);if($raw==='')continue;
            if(str_starts_with($raw,'sealed:v1:')){app('encrypter')->decrypt(Cipher::unseal('sessions',$raw));$counts['verified']++;continue;}
            $counts['plaintext']++;if($this->option('verify'))continue;
            $old=json_decode(base64_decode($raw,true)?:'',true);
            if(is_array($old)&&isset($old['iv'],$old['value'])){app('encrypter')->decrypt($raw);$inner=$raw;}else$inner=app('encrypter')->encrypt($raw);
            $sealed=Cipher::seal('sessions',$inner);$temp=$file.'.private-new';$mtime=filemtime($file);$out=fopen($temp,'xb');if(!$out)throw new RuntimeException('Session migration already started.');chmod($temp,0600);$n=fwrite($out,$sealed);fclose($out);
            if($n!==strlen($sealed)||Cipher::unseal('sessions',file_get_contents($temp))!==$inner||hash_file('sha256',$file)!==hash('sha256',$raw))throw new RuntimeException('Session changed during migration.');touch($temp,$mtime);rename($temp,$file);$counts['encrypted']++;
        }
        $this->line(json_encode($counts));return $this->option('verify')&&$counts['plaintext']?self::FAILURE:self::SUCCESS;
    }
}
