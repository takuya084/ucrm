# フレームワーク更新計画（P1-4）

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
