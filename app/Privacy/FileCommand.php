<?php
namespace App\Privacy;
use Illuminate\Console\Command;
class FileCommand extends Command
{
    protected $signature='privacy:files {--verify : Refuse plaintext without changing files}';
    protected $description='Encrypt or verify existing private attachments without displaying contents';
    public function handle(): int {$c=Files::migrate((bool)$this->option('verify'));$this->line(json_encode($c,JSON_THROW_ON_ERROR));return $this->option('verify')&&$c['plaintext']?self::FAILURE:self::SUCCESS;}
}
