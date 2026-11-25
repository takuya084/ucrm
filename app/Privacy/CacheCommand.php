<?php
namespace App\Privacy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
class CacheCommand extends Command
{
    protected $signature='privacy:cache {--clear-legacy : Remove only obsolete cache entries after all writers are stopped}';
    protected $description='Detect legacy file/database cache values without printing their contents';
    private function privateValue(string $serialized): bool {
        $value=@unserialize($serialized,['allowed_classes'=>false]);
        if(!is_string($value)||!str_starts_with($value,'sealed:v1:'))return false;
        Cipher::unseal('cache',$value);return true;
    }
    public function handle(): int {
        $name=config('cache.default');$store=config('cache.stores.'.$name);if(($store['driver']??'')==='private-cache')$store=config('cache.stores.'.$store['backend']);
        $driver=$store['driver']??'';$legacy=0;$verified=0;$removed=0;$clear=(bool)$this->option('clear-legacy');
        if($clear&&!app()->isDownForMaintenance()&&!app()->runningUnitTests())throw new RuntimeException('Stop writers and enter maintenance mode before clearing legacy caches.');
        if(in_array($driver,['array','null'],true)){$this->line('{"legacy":0,"verified":0,"removed":0}');return self::SUCCESS;}
        if($driver==='file'){
            $root=$store['path'];if(is_link($root))throw new RuntimeException('Cache links are not allowed.');
            if(is_dir($root))foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS)) as $item){
                if($item->isLink())throw new RuntimeException('Cache links are not allowed.');if(!$item->isFile()||$item->getFilename()==='.gitignore')continue;
                if(!preg_match('/^[a-f0-9]{40}$/D',$item->getFilename()))throw new RuntimeException('Unexpected file in application cache directory.');
                $bytes=file_get_contents($item->getPathname());if(strlen($bytes)>=10&&$this->privateValue(substr($bytes,10))){$verified++;continue;}
                $legacy++;if($clear){if(!unlink($item->getPathname()))throw new RuntimeException('Cannot remove obsolete cache entry.');$removed++;}
            }
        }elseif($driver==='database'){
            if(($store['table']??'cache')!=='cache'||($store['connection']??null)!==null)throw new RuntimeException('Custom cache tables/connections require an explicit scoped migration.');
            if(\Illuminate\Support\Facades\Schema::hasTable('cache'))foreach(DB::table('cache')->get(['key','value']) as $row){
                if($this->privateValue($row->value)){$verified++;continue;}$legacy++;if($clear)$removed+=DB::table('cache')->where('key',$row->key)->delete();
            }
        }else throw new RuntimeException('Legacy external caches require a separately scoped migration; no shared cache is flushed.');
        $this->line(json_encode(compact('legacy','verified','removed')));return $legacy>$removed?self::FAILURE:self::SUCCESS;
    }
}
