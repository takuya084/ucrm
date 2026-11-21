<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('absence_notices', function (Blueprint $table) {
   $table->id(); $table->foreignId('tenant_id')->constrained('facilities');
   $table->foreignId('child_id')->constrained('children'); $table->date('date');
   $table->unsignedInteger('revision'); $table->json('payload'); $table->timestamps();
   $table->unique(['tenant_id','child_id','date']);
  });
  Schema::create('absence_notice_versions', function (Blueprint $table) {
   $table->id(); $table->foreignId('absence_notice_id')->constrained('absence_notices');
   $table->unsignedBigInteger('actor_id'); $table->unsignedInteger('revision'); $table->json('payload'); $table->timestamp('created_at');
  });
  Schema::create('absence_calendars', function (Blueprint $table) {
   $table->id(); $table->foreignId('tenant_id')->unique()->constrained('facilities');
   $table->json('payload'); $table->unsignedInteger('revision'); $table->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('absence_notice_versions'); Schema::dropIfExists('absence_notices'); Schema::dropIfExists('absence_calendars'); }
};
