<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_returns', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('received_at');
            $table->date('resubmitted_at')->nullable()->after('remarks');
            $table->date('resolved_at')->nullable()->after('resubmitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('claim_returns', function (Blueprint $table) {
            $table->dropColumn(['remarks', 'resubmitted_at', 'resolved_at']);
        });
    }
};
