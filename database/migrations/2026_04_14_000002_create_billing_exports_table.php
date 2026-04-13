<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_period_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('bundle')
                ->comment('bundle:複式ZIP / billing:請求のみ / performance:実績のみ / cap_mgmt:上限管理のみ');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedInteger('file_size')->default(0);
            $table->json('included_files')->nullable();
            $table->json('warnings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_submitted')->default(false)->comment('国保連送信済フラグ');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'billing_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_exports');
    }
};
