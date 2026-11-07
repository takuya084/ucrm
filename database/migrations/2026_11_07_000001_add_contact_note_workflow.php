<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('contact_notes', function (Blueprint $table) {
        $table->uuid('guardian_entry_ref')->nullable()->unique();
        $table->timestamp('guardian_checked_at')->nullable();
        $table->unsignedBigInteger('guardian_checked_revision')->nullable();
        $table->unsignedBigInteger('guardian_checked_by')->nullable();
        $table->string('publication_hash',64)->nullable();
        $table->timestamp('guardian_confirmed_at')->nullable();
        $table->string('guardian_confirmed_hash',64)->nullable();
    }); }
    public function down(): void { Schema::table('contact_notes', function (Blueprint $table) {
        $table->dropUnique(['guardian_entry_ref']);
        $table->dropColumn(['guardian_entry_ref','guardian_checked_at','guardian_checked_revision','guardian_checked_by',
            'publication_hash','guardian_confirmed_at','guardian_confirmed_hash']);
    }); }
};
