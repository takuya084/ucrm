<?php
namespace App\Privacy;
use RuntimeException;
final class Files
{
    public static function root(): string {return config('filesystems.disks.local.root');}
    public static function paths(?string $root=null): \Generator {
        $root=$root??self::root();if(!is_dir($root))return;
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $item){
            if($item->isLink())throw new RuntimeException('A private storage link is not allowed.');
            if(!$item->isFile())continue;$relative=substr($item->getPathname(),strlen(rtrim($root,DIRECTORY_SEPARATOR))+1);
            if(str_starts_with($relative,'public'.DIRECTORY_SEPARATOR)||$item->getFilename()==='.gitignore')continue;
            yield str_replace(DIRECTORY_SEPARATOR,'/',$relative)=>$item->getPathname();
        }
    }
    public static function migrate(bool $verify=false): array {
        $counts=['encrypted'=>0,'verified'=>0,'plaintext'=>0];
        foreach(self::paths() as $path) {
            if(filesize($path)>134217728)throw new RuntimeException('Private file exceeds supported 64 MiB limit.');
            $raw=file_get_contents($path);
            if(str_starts_with($raw,'sealed:v1:')){Cipher::unseal('files',$raw);$counts['verified']++;continue;}
            if(strlen($raw)>67108864)throw new RuntimeException('Legacy private file exceeds supported 64 MiB limit.');
            $counts['plaintext']++;if($verify)continue;
            $temp=$path.'.private-new';$out=fopen($temp,'xb');if(!$out)throw new RuntimeException('Private file migration already started.');chmod($temp,0600);
            $sealed=Cipher::seal('files',$raw);$written=fwrite($out,$sealed);fclose($out);
            if($written!==strlen($sealed)||Cipher::unseal('files',file_get_contents($temp))!==$raw||hash_file('sha256',$path)!==hash('sha256',$raw))throw new RuntimeException('Private file changed or copy verification failed.');
            if(!rename($temp,$path))throw new RuntimeException('Cannot replace private file.');$counts['encrypted']++;
        }
        return $counts;
    }
}
