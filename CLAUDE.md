# uCRM — 放課後等デイサービス・児童発達支援 業務支援システム

Laravel 12 + Inertia 2 (Vue 3) + MySQL。利用児童・出欠・送迎・個別支援計画・国保連請求を扱う。
児童の障害情報・医療情報など**要配慮個人情報を扱うシステム**であることを常に前提にすること。

## 開発環境の注意（WSL + XAMPP）

- コードは `/mnt/c/xampp/htdocs/laravel/laravel_uCRM`（Windows 側の XAMPP で稼働）
- WSL 側 PHP は 8.4 で **pdo_mysql なし** → `php artisan migrate` 等の DB 操作は **Windows/XAMPP 側で実行**
- composer の `platform.php` は **8.2.12（XAMPP実機）に固定済み** — WSL(8.4)で解決しても本番非互換の依存が入らない。外さないこと
- テストは SQLite in-memory で動く: `php artisan test`（DB 不要・WSL で実行可）
- `latestOfMany` 系リレーションに select でカラム制限する場合は必ずテーブル名で修飾する（集計サブクエリと JOIN され ambiguous になる）
- ファイルは CRLF 改行。perl/sed での一括置換は `\r?\n` を考慮すること

## 監査対応（2026-06 実施）

- 監査レポート対応のチェックリスト: **`docs/audit-checklist.md`** — 項目を対応したら必ずチェックを更新する
- 残項目と前提資料:
  - フレームワーク更新（P1-4）は **2026-07-12 完了**（Laravel 12 / Inertia 2 / 脆弱性勧告 0件。`feature/framework-upgrade`）
  - 国保連CSV準拠対応: `docs/nhif-csv-todo.md`（残タスク=取込送信システムでの実機検証等）
  - サービスコード・単位数は捏造禁止。こども家庭庁告示の単位数表から取込むこと

## デプロイ時の必須作業（このコミット群を本番反映する際）

1. `php artisan migrate`（Windows/XAMPP 側で実行）
2. `php artisan app:encrypt-sensitive-data` — 既存の児童要配慮情報（障がい・アレルギー・配慮事項）の平文を暗号化
3. `npm run build` — フロント変更（児童フォームの AI 同意チェックボックス等）の反映
4. 本番は `composer install --no-dev`（debugbar を含めないこと）
5. cron で `php artisan schedule:run` が毎分動いていることを確認（請求出力ファイルの保持期限削除が日次実行される）
6. `.env` 本番設定: `APP_DEBUG=false` / `SESSION_SECURE_COOKIE=true` / Webhook を使う施設は `yoyaku_webhook_secret` 必須（未設定施設は受信拒否される）

## 実装上の決まりごと

- **テナント分離**: クエリ・バリデーションは必ず `facilityId()` でスコープする。FormRequest の `exists` は
  `Rule::exists(...)->where('facility_id', ...)` を使う（過去に IDOR があった: P0-2/P0-3）
- **請求データの保護**: 確定済み（confirmed/submitted/completed）の請求期間は再計算・出欠変更不可。
  `BillingPeriod::isLocked()` でガードする
- **記録の削除**: 出欠・支援記録・モニタリング・個別支援計画は物理削除禁止（SoftDeletes。保存義務対象）
- **監査ログ**: 個人情報を扱うモデルには `\App\Models\Concerns\Auditable` を付与。
  クエリビルダの一括 update/delete はイベントが発火しないため `AuditLog::record()` を直接呼ぶ
- **外部AI**: 児童の実名を外部 API に送らない（OpenAiService は仮名化済み）。
  AI 下書きは `ai_draft_consented_at`（保護者同意）必須
- **加算・制度**: 報酬単価・加算要件は推測で実装しない。告示・留意事項通知・自治体ルールを確認し、
  不明な点は条件 JSON（time_category / monthly_limit / rate_based 等）の設定で吸収する
- **2FA**: Fortify は 2FA エンジンのみ（`Fortify::ignoreRoutes()` 済み・ルートは routes/auth.php に自前定義）。
  ログイン分岐は `LoginRequest::authenticateOrGetTwoFactorUser()`。Fortify の他機能を有効化しないこと

## テスト

- `php artisan test` — 全テスト（76件）。請求エンジンは `tests/Feature/Billing/` にあり、
  請求ロジックを触ったら必ず実行・追加する
- 主要画面の 200 確認は `tests/Feature/PageSmokeTest.php`。画面を追加したらここにパスを足す
