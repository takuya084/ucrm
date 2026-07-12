# フレームワーク更新計画（P1-4）

## 実施結果（2026-07-12 完了・feature/framework-upgrade）

- Laravel 9.52 → **12.63** / Sanctum 2 → **4** / Inertia 0.6 → **2**（サーバ・クライアント）/ PHPUnit 9 → **11**
- `composer audit`: 12件 → **0件**
- `composer config platform.php 8.2.12` で XAMPP 実機の PHP に固定（WSL でも `--ignore-platform-req` 不要に）
- doctrine/dbal・carbonphp/carbon-doctrine-types は削除（L11+ は dbal 不要）
- 発見・修正した移行起因の不具合:
  1. Carbon 3 で `diffIn*()` が符号付き float に変更 → 算定時間数（NhifCsvExportService）・
     受給者証残日数（RecipientCertificate）・webhook リプレイ防御（YoyakuWebhookController）の3箇所を修正
  2. `latestOfMany` の制限付き eager load が集計サブクエリとの JOIN で ambiguous column に
     → ChildrenController の select をテーブル名修飾。PageSmokeTest（主要7画面）で回帰ガード
- 各段階（9→10→11→12→Inertia）でコミットを分割。全67テストパス・実画面（ログイン〜主要一覧・詳細）確認済み
- デプロイ時: `php artisan migrate`（personal_access_tokens.expires_at 追加）と `npm run build` が必要

---

以下は実施前の計画（記録として保持）。

## 現状（2026-06 時点）

| 依存 | 現行 | 状態 |
|---|---|---|
| PHP | ^8.0.2 | 8.0 は 2023-11 EOL |
| Laravel | 9.52 | セキュリティ修正 2024-02 終了 |
| laravel/sanctum | ^2.8 | EOL |
| inertiajs/inertia-laravel | ^0.6 | 旧系列（PHP 8.4 非対応の制約あり） |
| laravel/breeze | ^1 | 旧系列 |

`composer audit` で 7 パッケージに 12 件の脆弱性勧告が出ている（2026-06-12 時点）。
要配慮個人情報を扱うため、**最優先で計画的に実施すること**。

## 推奨手順（段階アップグレード）

1. **準備**
   - テストを全て通す（済: 33件）。アップグレード中の回帰検知の生命線
   - 本番相当データでステージング環境を用意
2. **Laravel 9 → 10**（PHP 8.1+）
   - `laravel/framework:^10`, `sanctum:^3`, `inertia-laravel:^0.6→^1.0`
   - 変更点: 型宣言の追加、`$dates` 廃止 → `$casts`
3. **Laravel 10 → 11**（PHP 8.2+）
   - `sanctum:^4`, `breeze:^2`
   - 変更点: スケルトン構成変更（Kernel 廃止 → bootstrap/app.php）。
     ただし既存構成のままでも動作するため、段階移行可
4. **Laravel 11 → 12**（任意・最新化）
5. **各段階で**: `composer audit` がゼロになるまで依存更新 → 全テスト → 主要画面の手動確認

## 注意点（このリポジトリ固有）

- `doctrine/dbal` は ^3.6 に固定済み（4.x は Laravel 9/10 と非互換）。L11 以降は dbal 不要になるため削除可
- `barryvdh/laravel-dompdf` / `league/csv` / `stripe-php` はメジャー互換確認のこと
- Inertia 0.6 → 1.x はフロント（@inertiajs/inertia → @inertiajs/vue3）の import 変更が必要。
  `resources/js` 全ページに影響するため、専用ブランチで一括変換する
- WSL 側 PHP は 8.4 のため、`--ignore-platform-req=php` を使わずに済むようになる
