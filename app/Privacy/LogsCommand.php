<?php
namespace App\Privacy;
use Illuminate\Console\Command;
use RuntimeException;
class LogsCommand extends Command
{
    protected $signature='privacy:logs {--verify} {--migrate-legacy} {--read= : Authorized decryption to terminal only}';
    protected $description='Verify/seal legacy app logs, or decrypt a selected log to the terminal';
    public function handle(): int {
        $root=storage_path('logs');$plain=0;$verified=0;
        if($this->option('read')){
            $file=realpath($this->option('read'));$base=realpath($root);
            if(!$file||!$base||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||is_link($this->option('read')))throw new RuntimeException('Only a private app log may be read.');
            $in=fopen($file,'rb');while(($line=fgets($in))!==false){if($line==="[private-log-unavailable]\n"){$this->output->write($line);continue;}$this->output->write(Cipher::unseal('logs',rtrim($line,"\r\n")));}fclose($in);return self::SUCCESS;
        }
        foreach(glob($root.'/*.log')?:[] as $file){
            if(is_link($file))throw new RuntimeException('Log links are not allowed.');$bytes=file_get_contents($file);if($bytes==='')continue;
            $lines=explode("\n",rtrim($bytes,"\n"));$valid=true;
            foreach($lines as $line){if($line==='[private-log-unavailable]')continue;if(!str_starts_with($line,'sealed:v1:')){$valid=false;break;}Cipher::unseal('logs',$line);}
            if($valid){$verified++;continue;}$plain++;
            if($this->option('migrate-legacy')){
                $sealed=Cipher::seal('logs',$bytes)."\n";$temp=$file.'.private-new';$h=fopen($temp,'xb');if(!$h)throw new RuntimeException('A log migration is already in progress.');chmod($temp,0600);$n=fwrite($h,$sealed);fclose($h);
                if($n!==strlen($sealed)||Cipher::unseal('logs',rtrim(file_get_contents($temp),"\n"))!==$bytes||hash_file('sha256',$file)!==hash('sha256',$bytes))throw new RuntimeException('Log migration verification failed.');rename($temp,$file);
            }
        }
        $this->line(json_encode(compact('plain','verified')));return $this->option('verify')&&$plain?self::FAILURE:self::SUCCESS;
    }
}
