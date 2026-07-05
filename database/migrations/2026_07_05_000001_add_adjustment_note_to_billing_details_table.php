<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_details', function (Blueprint $table) {
            // 手動調整の証跡（返戻・監査対応時に調整根拠を追えるようにする）
            $table->text('adjustment_note')->nullable()->after('status')->comment('手動調整の理由');
            $table->unsignedBigInteger('adjusted_by')->nullable()->after('adjustment_note')->comment('調整者 user_id');
            $table->timestamp('adjusted_at')->nullable()->after('adjusted_by')->comment('調整日時');
        });
    }

    public function down(): void
    {
        Schema::table('billing_details', function (Blueprint $table) {
            $table->dropColumn(['adjustment_note', 'adjusted_by', 'adjusted_at']);
        });
    }
};
