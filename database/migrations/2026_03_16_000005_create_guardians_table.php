<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->enum('relationship', ['father', 'mother', 'grandfather', 'grandmother', 'other'])->default('mother');
            $table->string('tel_primary')->nullable();       // 主連絡先電話番号
            $table->string('tel_secondary')->nullable();     // 副連絡先電話番号
            $table->string('email')->nullable();
            $table->string('line_id')->nullable();
            $table->string('address')->nullable();
            $table->string('postcode', 8)->nullable();
            $table->enum('preferred_contact', ['tel', 'line', 'email', 'in_person'])->default('tel');
            $table->text('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
