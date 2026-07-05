<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_periods', function (Blueprint $table) {
            // 国保連の支払決定通知との突合（差異があれば返戻・過誤を確認する）
            $table->unsignedBigInteger('payment_decided_amount')->nullable()->after('notes')->comment('国保連の支払決定額');
            $table->date('payment_decided_at')->nullable()->after('payment_decided_amount')->comment('支払決定日（通知記載日）');
            $table->text('payment_difference_note')->nullable()->after('payment_decided_at')->comment('差異の原因・対応メモ');
        });
    }

    public function down(): void
    {
        Schema::table('billing_periods', function (Blueprint $table) {
            $table->dropColumn(['payment_decided_amount', 'payment_decided_at', 'payment_difference_note']);
        });
    }
};
