<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->unsignedBigInteger('yoyaku_user_id')->nullable()->after('memo')
                ->comment('p-yoyaku の users.id（別DBのため外部キー制約なし）');
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn('yoyaku_user_id');
        });
    }
};
