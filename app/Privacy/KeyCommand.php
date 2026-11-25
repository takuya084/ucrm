<?php
namespace App\Privacy;
use Illuminate\Console\Command;
use RuntimeException;
class KeyCommand extends Command
{
    protected $signature='privacy:keys {path : New private key file, outside the public directory}';
    protected $description='Create separate data, lookup and backup keys without displaying them';
    public function handle(): int {
        $path=$this->argument('path');$parent=realpath(dirname($path));$public=realpath(public_path());$storage=realpath(storage_path('app'));$database=realpath(database_path());
        if(($storage&&($parent===$storage||str_starts_with($parent,$storage.DIRECTORY_SEPARATOR)))||($database&&($parent===$database||str_starts_with($parent,$database.DIRECTORY_SEPARATOR))))throw new RuntimeException('Keep keys outside database and private-file backup directories.');
        if(!$parent||($public&&($parent===$public||str_starts_with($parent,$public.DIRECTORY_SEPARATOR)))||file_exists($path)||is_link($path))throw new RuntimeException('Key path must be a new private file outside public storage.');
        $handle=fopen($path,'x');if(!$handle)throw new RuntimeException('Cannot create key file');chmod($path,0600);
        foreach(['DATA_ENCRYPTION_KEY','DATA_LOOKUP_KEY','DATA_BACKUP_KEY'] as $name)fwrite($handle,$name.'=base64:'.base64_encode(random_bytes(32))."\n");
        fclose($handle);$this->info('Created private keys. Import this file as environment configuration; retain a separate protected recovery copy.');return self::SUCCESS;
    }
}
