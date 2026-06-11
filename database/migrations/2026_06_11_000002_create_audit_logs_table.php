<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->comment('操作ユーザー（null=システム/Webhook）');
            $table->foreignId('facility_id')->nullable()->comment('操作ユーザーの所属施設');
            $table->string('action', 20)->comment('created/updated/deleted/viewed/exported');
            $table->string('auditable_type')->nullable()->comment('対象モデル');
            $table->unsignedBigInteger('auditable_id')->nullable()->comment('対象ID');
            $table->json('old_values')->nullable()->comment('変更前の値（変更分のみ）');
            $table->json('new_values')->nullable()->comment('変更後の値（変更分のみ）');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['facility_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
