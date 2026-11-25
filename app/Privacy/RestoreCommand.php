<?php
namespace App\Privacy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class RestoreCommand extends Command
{
    protected $signature='privacy:restore {path} {connection : Configured, empty recovery database} {files : New empty private directory}';
    protected $description='Verify and restore an encrypted backup into empty recovery targets only';
    public function handle(): int {Backup::restore($this->argument('path'),DB::connection($this->argument('connection'))->getPdo(),$this->argument('files'));$this->info('Restored into empty recovery targets. Keep the data/lookup keys separate and verify before switching applications.');return self::SUCCESS;}
}
