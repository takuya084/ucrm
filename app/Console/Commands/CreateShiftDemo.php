<?php
namespace App\Console\Commands;

use App\Support\ShiftDemoData;
use Illuminate\Console\Command;

class CreateShiftDemo extends Command
{
    protected $signature = 'demo:shift-samples {--month=2026-12}';
    protected $description = '標準のオフラインデモ専用：独立した事業所に職員10名と2か月分のシフトを追加';

    public function handle(ShiftDemoData $data): int
    {
        $password=bin2hex(random_bytes(18));
        $receipt=$data->create((string)$this->option('month'),$password);
        $this->line(json_encode($receipt+['password'=>$password],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
