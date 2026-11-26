<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public $withinTransaction=false;
    public function up(): void {\App\Privacy\SchemaSetup::prepare();}
    public function down(): void {throw new RuntimeException('Use a fresh database; this migration does not decrypt stored personal data.');}
};
