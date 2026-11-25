<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {\App\Privacy\Migrator::prepare();}
    public function down(): void {throw new RuntimeException('Personal-data encryption cannot be rolled back to plaintext. Restore a verified encrypted backup with its keys.');}
};
