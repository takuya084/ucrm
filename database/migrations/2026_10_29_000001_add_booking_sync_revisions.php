<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('usage_records', function (Blueprint $t) {
            $t->unsignedBigInteger('yoyaku_sync_revision')->default(0);
            $t->uuid('yoyaku_cancellation_token')->nullable();
        });
        Schema::create('yoyaku_booking_states', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('facility_id');
            $t->unsignedBigInteger('yoyaku_user_id');
            $t->date('date');
            $t->unsignedBigInteger('revision');
            $t->char('payload_hash',64);
            $t->boolean('cancelled');
            $t->uuid('cancellation_token')->nullable();
            $t->unique(['facility_id','yoyaku_user_id','date'],'yoyaku_booking_stream');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('yoyaku_booking_states');
        Schema::table('usage_records', fn(Blueprint $t)=>$t->dropColumn(['yoyaku_sync_revision','yoyaku_cancellation_token']));
    }
};
