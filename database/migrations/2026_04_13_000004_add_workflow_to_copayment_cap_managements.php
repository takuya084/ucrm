<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copayment_cap_managements', function (Blueprint $table) {
            $table->string('form_type', 20)->default('paper')->after('status')
                ->comment('paper:紙 / electronic:電子');
            $table->string('contract_status', 20)->default('contracted')->after('form_type')
                ->comment('contracted:契約中 / pending:契約中（未開始）/ terminated:解約');
            $table->timestamp('actual_confirmed_at')->nullable()->after('contract_status')
                ->comment('実績確定日時');
            $table->timestamp('sent_at')->nullable()->after('actual_confirmed_at')
                ->comment('関連事業所への送付日時');
            $table->timestamp('received_at')->nullable()->after('sent_at')
                ->comment('関連事業所からの受領日時');
            $table->timestamp('confirmed_at')->nullable()->after('received_at')
                ->comment('上限管理確定日時');
            $table->text('remarks')->nullable()->after('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('copayment_cap_managements', function (Blueprint $table) {
            $table->dropColumn([
                'form_type',
                'contract_status',
                'actual_confirmed_at',
                'sent_at',
                'received_at',
                'confirmed_at',
                'remarks',
            ]);
        });
    }
};
