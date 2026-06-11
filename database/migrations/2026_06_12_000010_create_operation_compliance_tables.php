<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 虐待防止・身体拘束適正化の委員会/研修記録（未実施は減算対象）
        Schema::create('prevention_committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->comment('abuse_prevention(虐待防止), restraint(身体拘束適正化)');
            $table->string('category', 20)->default('committee')->comment('committee(委員会), training(研修)');
            $table->dateTime('held_at');
            $table->json('attendees')->nullable()->comment('出席者(JSON)');
            $table->text('minutes')->nullable()->comment('議事・研修内容');
            $table->string('document_path')->nullable();
            $table->timestamps();
            $table->index(['facility_id', 'type', 'held_at']);
        });

        // 身体拘束の記録（やむを得ず行った場合の態様・時間・理由の記録義務）
        Schema::create('physical_restraint_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->dateTime('occurred_at')->comment('実施日時');
            $table->integer('duration_minutes')->nullable()->comment('実施時間（分）');
            $table->text('method')->comment('拘束の態様');
            $table->text('reason')->comment('緊急やむを得ない理由（切迫性・非代替性・一時性）');
            $table->dateTime('guardian_notified_at')->nullable()->comment('保護者への報告日時');
            $table->timestamps();
            $table->softDeletes();
        });

        // BCP（業務継続計画。未策定は減算対象）
        Schema::create('business_continuity_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->comment('infection(感染症), disaster(災害)');
            $table->string('document_path')->nullable()->comment('計画書ファイル');
            $table->date('established_at')->nullable()->comment('策定日');
            $table->date('last_reviewed_at')->nullable()->comment('最終見直し日');
            $table->date('last_training_at')->nullable()->comment('最終研修・訓練日');
            $table->timestamps();
            $table->unique(['facility_id', 'type']);
        });

        // 安全計画（児童福祉法施行規則・R5.4義務化）
        Schema::create('safety_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('fiscal_year', 4)->comment('年度');
            $table->string('document_path')->nullable();
            $table->date('established_at')->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['facility_id', 'fiscal_year']);
        });

        // 自己評価結果の公表（放デイ/児発ガイドライン。未公表は減算対象）
        Schema::create('self_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('fiscal_year', 4)->comment('年度');
            $table->date('guardian_survey_at')->nullable()->comment('保護者評価の実施日');
            $table->date('published_at')->nullable()->comment('公表日');
            $table->string('published_url')->nullable()->comment('公表先URL');
            $table->string('document_path')->nullable();
            $table->timestamps();
            $table->unique(['facility_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_evaluations');
        Schema::dropIfExists('safety_plans');
        Schema::dropIfExists('business_continuity_plans');
        Schema::dropIfExists('physical_restraint_records');
        Schema::dropIfExists('prevention_committees');
    }
};
