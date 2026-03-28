<?php

namespace App\Console\Commands;

use App\Models\Facility;
use Illuminate\Console\Command;

class SwitchFacilityBilling extends Command
{
    protected $signature = 'facility:switch-billing
                            {facility_id : 事業所ID}
                            {type : 切り替え先（free または stripe）}
                            {--stripe-customer-id= : Stripe顧客ID（stripe時に必要）}
                            {--stripe-subscription-id= : StripeサブスクリプションID（stripe時に必要）}';

    protected $description = '事業所の課金タイプを切り替えます（free ⇔ stripe）';

    public function handle(): int
    {
        $facility = Facility::find($this->argument('facility_id'));

        if (!$facility) {
            $this->error('事業所が見つかりません。');
            return self::FAILURE;
        }

        $type = $this->argument('type');

        if (!in_array($type, ['free', 'stripe'], true)) {
            $this->error('type は free または stripe を指定してください。');
            return self::FAILURE;
        }

        if ($facility->billing_type === $type) {
            $this->warn("この事業所は既に {$type} です。");
            return self::SUCCESS;
        }

        // 現在の状態を表示
        $this->info('【変更前】');
        $this->showFacility($facility);

        if ($type === 'free') {
            return $this->switchToFree($facility);
        }

        return $this->switchToStripe($facility);
    }

    private function switchToFree(Facility $facility): int
    {
        if (!$this->confirm('この事業所を無料プランに切り替えますか？（Stripe情報はクリアされます）')) {
            $this->info('キャンセルしました。');
            return self::SUCCESS;
        }

        $facility->billing_type = 'free';
        $facility->subscription_status = 'free';
        $facility->is_active = true;
        $facility->stripe_customer_id = null;
        $facility->stripe_subscription_id = null;
        $facility->stripe_checkout_session_id = null;
        $facility->subscription_ended_at = null;
        $facility->save();

        $this->info('【変更後】');
        $this->showFacility($facility);
        $this->info('無料プランに切り替えました。Stripe側のサブスクリプションは別途キャンセルしてください。');

        return self::SUCCESS;
    }

    private function switchToStripe(Facility $facility): int
    {
        $customerId = $this->option('stripe-customer-id')
            ?: $this->ask('Stripe顧客ID（cus_xxx）');

        $subscriptionId = $this->option('stripe-subscription-id')
            ?: $this->ask('StripeサブスクリプションID（sub_xxx）');

        if (!$customerId || !$subscriptionId) {
            $this->error('Stripe顧客IDとサブスクリプションIDの両方が必要です。');
            return self::FAILURE;
        }

        if (Facility::where('stripe_subscription_id', $subscriptionId)
                ->where('id', '!=', $facility->id)->exists()) {
            $this->error('このサブスクリプションIDは既に別の事業所で使用されています。');
            return self::FAILURE;
        }

        if (!$this->confirm("この事業所を有料プラン（Stripe）に切り替えますか？")) {
            $this->info('キャンセルしました。');
            return self::SUCCESS;
        }

        $facility->billing_type = 'stripe';
        $facility->subscription_status = 'active';
        $facility->is_active = true;
        $facility->stripe_customer_id = $customerId;
        $facility->stripe_subscription_id = $subscriptionId;
        $facility->subscription_ended_at = null;
        $facility->save();

        $this->info('【変更後】');
        $this->showFacility($facility);
        $this->info('有料プラン（Stripe）に切り替えました。');

        return self::SUCCESS;
    }

    private function showFacility(Facility $facility): void
    {
        $this->table(
            ['項目', '値'],
            [
                ['事業所ID', $facility->id],
                ['事業所名', $facility->name],
                ['billing_type', $facility->billing_type],
                ['subscription_status', $facility->subscription_status ?? '(null)'],
                ['is_active', $facility->is_active ? 'true' : 'false'],
                ['stripe_customer_id', $facility->stripe_customer_id ?? '(null)'],
                ['stripe_subscription_id', $facility->stripe_subscription_id ?? '(null)'],
            ]
        );
    }
}
