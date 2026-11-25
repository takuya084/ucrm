<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class EncryptSensitiveData extends Command {
    protected $signature='app:encrypt-sensitive-data {--dry-run}';
    protected $description='Compatibility alias for privacy:migrate';
    public function handle(): int {return $this->call('privacy:migrate', ['--dry-run'=>$this->option('dry-run')]);}
}
