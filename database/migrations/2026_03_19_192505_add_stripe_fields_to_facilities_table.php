<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('billing_type')->default('stripe')->after('yoyaku_business_id');
            $table->boolean('is_active')->default(true)->after('billing_type');
            $table->string('subscription_status')->nullable()->after('is_active');
            $table->timestamp('subscription_ended_at')->nullable()->after('subscription_status');
            $table->string('stripe_checkout_session_id')->nullable()->after('subscription_ended_at');
            $table->string('stripe_customer_id')->nullable()->index()->after('stripe_checkout_session_id');
            $table->string('stripe_subscription_id')->nullable()->unique()->after('stripe_customer_id');
        });
    }

    public function down()
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropUnique(['stripe_subscription_id']);
            $table->dropIndex(['stripe_customer_id']);
            $table->dropColumn([
                'billing_type',
                'is_active',
                'subscription_status',
                'subscription_ended_at',
                'stripe_checkout_session_id',
                'stripe_customer_id',
                'stripe_subscription_id',
            ]);
        });
    }
};
