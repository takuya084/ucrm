<?php
namespace App\Privacy;
use Illuminate\Console\Command;
class MaintenanceCommand extends Command
{
    protected $signature='privacy:migrate {--dry-run : Only inspect legacy data} {--verify : Refuse any plaintext data}';
    protected $description='Encrypt/verify existing personal data without printing values';
    public function handle(): int {
        $verify=(bool)$this->option('verify');$counts=Migrator::scan(!$verify,(bool)$this->option('dry-run'));
        $this->line(json_encode($counts,JSON_THROW_ON_ERROR));
        return $verify&&$counts['plaintext']>0?self::FAILURE:self::SUCCESS;
    }
}
