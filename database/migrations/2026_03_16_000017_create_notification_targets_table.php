<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->onDelete('cascade');
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->enum('channel', ['email', 'line'])->default('email');
            $table->string('address');                       // メールアドレス or LINE ID
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->dateTime('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_targets');
    }
};
