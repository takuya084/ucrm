<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('daily_transport_assignments', function (Blueprint $table) {
  $table->unsignedBigInteger('assistant_staff_id')->nullable();
 }); }
 public function down(): void { Schema::table('daily_transport_assignments', function (Blueprint $table) {
  $table->dropColumn(['assistant_staff_id']);
 }); }
};
