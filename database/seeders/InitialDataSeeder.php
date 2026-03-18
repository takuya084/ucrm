<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InitialDataSeeder extends Seeder
{
    /**
     * 開発用初期データ
     * php artisan db:seed --class=InitialDataSeeder で実行
     */
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        // ─── 事業所（既存があればスキップ）───────────────────────
        $facilityId = DB::table('facilities')->value('id');
        if (!$facilityId) {
            $facilityId = DB::table('facilities')->insertGetId([
                'name'             => 'サンプル放課後等デイサービス',
                'address'          => '東京都渋谷区1-1-1',
                'tel'              => '03-1234-5678',
                'capacity_per_day' => 10,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // ─── ユーザー＆スタッフ（重複チェック付き）────────────
        $accounts = [
            ['email' => 'admin@example.com',  'name' => '管理者 太郎',    'role' => 'admin'],
            ['email' => 'leader@example.com', 'name' => '支援責任者 花子', 'role' => 'leader'],
            ['email' => 'staff@example.com',  'name' => '現場職員 次郎',  'role' => 'staff'],
        ];

        foreach ($accounts as $account) {
            // ユーザーが既存であれば取得、なければ作成
            $existingUser = DB::table('users')->where('email', $account['email'])->first();
            if ($existingUser) {
                $userId = $existingUser->id;
            } else {
                $userId = DB::table('users')->insertGetId([
                    'name'              => $account['name'],
                    'email'             => $account['email'],
                    'password'          => Hash::make('password'),
                    'email_verified_at' => $now,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }

            // スタッフレコードが未作成なら作成
            $staffExists = DB::table('staff')->where('user_id', $userId)->exists();
            if (!$staffExists) {
                DB::table('staff')->insert([
                    'user_id'     => $userId,
                    'facility_id' => $facilityId,
                    'name'        => $account['name'],
                    'role'        => $account['role'],
                    'is_active'   => 1,
                    'joined_at'   => $today,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }

        $this->command->info('初期データを作成しました。');
        $this->command->table(
            ['メール', 'パスワード', 'ロール'],
            [
                ['admin@example.com',  'password', 'admin（管理者）'],
                ['leader@example.com', 'password', 'leader（支援責任者）'],
                ['staff@example.com',  'password', 'staff（現場職員）'],
            ]
        );
    }
}
