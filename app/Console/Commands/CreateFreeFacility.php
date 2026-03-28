<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateFreeFacility extends Command
{
    protected $signature = 'facility:create-free
                            {--name= : 事業所名}
                            {--email= : 管理者メールアドレス}
                            {--admin-name= : 管理者氏名}
                            {--password= : パスワード}
                            {--address= : 住所}
                            {--tel= : 電話番号}
                            {--capacity=10 : 1日の定員}';

    protected $description = '無料プランの事業所を作成します（Stripe不要）';

    public function handle(): int
    {
        $facilityName = $this->option('name') ?: $this->ask('事業所名');
        $email        = $this->option('email') ?: $this->ask('管理者メールアドレス');
        $adminName    = $this->option('admin-name') ?: $this->ask('管理者氏名');
        $password     = $this->option('password') ?: $this->secret('パスワード');
        $address      = $this->option('address') ?: $this->ask('住所（任意）', '');
        $tel          = $this->option('tel') ?: $this->ask('電話番号（任意）', '');
        $capacity     = (int) $this->option('capacity');

        if (User::where('email', $email)->exists()) {
            $this->error("メールアドレス {$email} は既に登録されています。");
            return self::FAILURE;
        }

        $result = DB::transaction(function () use ($facilityName, $email, $adminName, $password, $address, $tel, $capacity) {
            $facility = Facility::create([
                'name'                => $facilityName,
                'address'             => $address ?: null,
                'tel'                 => $tel ?: null,
                'capacity_per_day'    => $capacity,
                'billing_type'        => 'free',
                'is_active'           => true,
                'subscription_status' => 'free',
            ]);

            $user = User::create([
                'name'              => $adminName,
                'email'             => strtolower(trim($email)),
                'password'          => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            Staff::create([
                'user_id'     => $user->id,
                'facility_id' => $facility->id,
                'name'        => $adminName,
                'role'        => 'admin',
                'is_active'   => true,
                'joined_at'   => now(),
            ]);

            return $facility;
        });

        $this->info("無料事業所を作成しました。");
        $this->table(
            ['事業所名', '事業所ID', 'メール', 'billing_type'],
            [[$result->name, $result->id, $email, 'free']]
        );

        return self::SUCCESS;
    }
}
