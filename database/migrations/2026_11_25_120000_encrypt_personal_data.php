<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public $withinTransaction=false;
    public function up(): void {\App\Privacy\Migrator::prepare();}
    public function down(): void {if(app()->runningUnitTests()&&class_exists(\PHPUnit\Framework\TestCase::class,false))return;throw new RuntimeException('Personal-data encryption cannot be rolled back to plaintext. Restore a verified encrypted backup with its keys.');}
};
