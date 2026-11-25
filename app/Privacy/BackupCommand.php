<?php
namespace App\Privacy;
use Illuminate\Console\Command;
class BackupCommand extends Command
{
    protected $signature='privacy:backup {path : New encrypted backup file} {--offline : Application and workers have been stopped externally}';
    protected $description='Create and verify a streaming encrypted database/private-files backup';
    public function handle(): int {Backup::create($this->argument('path'),(bool)$this->option('offline'));$this->info('Encrypted backup created and authenticated. Keys are not included.');return self::SUCCESS;}
}
