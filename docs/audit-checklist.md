# 監査指摘事項 対応チェックリスト

監査日: 2026-06-11 / 対応ブランチ: `feature/audit-fixes`

凡例: `[x]` 対応済み / `[ ]` 未対応 / `(済: コミットID)` を完了時に追記

---

## P0: 即時対応（セキュリティ・データ破壊リスク）

- [x] P0-1: 未認証ルートの削除（`/analysis`, `/inertia-test`, `/component-test`, `/inertia/*`）
- [x] P0-2: 出欠一括登録のクロステナントIDOR修正（`BulkStoreUsageRecordRequest` の child_id 施設スコープ）
- [x] P0-3: 受給者証の `external_facility_ids` 施設スコープ修正（Store/Update 両リクエスト）
- [x] P0-4: Yoyaku Webhook の HMAC 認証必須化（フェイルクローズ）
- [x] P0-5: 確定済み請求期間の再計算ガード（確定・送信済みデータの破壊防止）
- [x] P0-6: `barryvdh/laravel-debugbar` を require-dev へ移動（本番デプロイは `composer install --no-dev` を徹底）
- [x] P0-7: `.env.example` の本番向け既定値整備（SESSION_SECURE_COOKIE 等の注意書き）
- [x] P0-8: 【テスト整備中に発見】欠席日にも基本報酬・一般加算が算定される過請求バグを修正
      （BillingCalculationService / ServiceCodeResolver — 欠席日は欠席時対応加算のみ算定）
- [x] P0-9: 【テスト整備中に発見】上限管理計算が常に実行時エラーになるバグを修正
      （CopaymentCapService::calculateCap のクロージャに $certificate が未キャプチャ）

## P1: 〜1ヶ月（法令・基盤リスク）

- [x] P1-1: OpenAI 送信データの仮名化（実名除去）＋ AI 利用同意フラグ（児童単位）
      ※要実行: `php artisan migrate`（Windows/XAMPP側）と `npm run build`。TLS検証無効化（withoutVerifying）も同時に修正
- [x] P1-2: 監査ログ（audit_logs）導入 — 個人情報の閲覧・変更・出力の記録
      ※Eloquentイベント経由のため一括update/deleteは未記録（該当箇所は順次 AuditLog::record() 直接呼び出しを追加）。要 `php artisan migrate`
- [x] P1-3: 請求エンジンのテスト整備（BillingCalculationService 8件・全25件パス）
      ※phpunit を SQLite in-memory 化、doctrine/dbal を Laravel 9 互換の ^3.6 に固定、
      無効化済みの自己登録を前提とした旧 RegistrationTest を現仕様に合わせ更新。
      CopaymentCapService・CSV出力のテストは P2 の方式見直しと併せて追加予定
- [ ] P1-4: Laravel 11/12 + PHP 8.3 への移行（Sanctum 含む）
- [x] P1-5: 出欠記録の物理削除廃止（SoftDeletes + 請求確定済み月の編集ガード）
      ※出欠一括保存・実績一括更新・Webhook の3経路すべてに適用。要 `php artisan migrate`

## P2: 〜3ヶ月（請求の制度適合 — 完了まで実運用請求は不可）

- [x] P2-1: R6 基本報酬の時間区分対応（support_plans に計画支援時間・5領域を追加、
      conditions.time_category による区分判定をテスト付きで実装）
- [ ] P2-2: R6 加算・減算マスタ刷新 — 仕組みは対応済み（time_category/monthly_limit/rate_based 条件）。
      単位数・サービスコードの実データはこども家庭庁告示・国保中央会の単位数表CSVから取込が必要
      （捏造した単位数で請求しないこと。`billing.settings.service-codes.import` で取込）
- [x] P2-3: 加算の月回数上限制御（conditions.monthly_limit。欠席時対応加算(Ⅰ)に月4回を設定済み）
- [x] P2-4: 欠席時対応の記録テーブル（absence_supports: 連絡日時・対応者・相談援助内容）※入力UIは今後
- [x] P2-5: 処遇改善加算の月次%計算（treatment_improvement_settings × 合計単位数。端数処理は四捨五入
      — 自治体・告示の取扱いを要確認）
- [ ] P2-6: 国保連インタフェース仕様準拠CSV — 国保中央会「電子請求受付システム インタフェース仕様書
      （障害児支援）」の最新版を入手して実装する必要あり（docs/nhif-csv-todo.md 参照）
- [x] P2-7: 契約情報テーブル（contracts: 契約支給量・契約開始日・記入欄番号）※入力UIは今後
- [x] P2-8: 就学前無償化（is_free_of_charge → 負担0円）・多子軽減区分・保護者氏名フィールド
- [x] P2-9: 上限管理を管理事業所優先充当方式に変更（config/billing.php で按分方式にも切替可）
- [x] P2-10: 明細書への上限管理結果反映（billing_details に結果コード・管理事業所番号・結果額を同期）

## P3: 〜6ヶ月（実地指導・監査対応）

- [ ] P3-1: アセスメント記録（assessments テーブル）
- [ ] P3-2: 個別支援計画プロセス（担当者会議録・原案承認フロー・交付記録）
- [ ] P3-3: 保護者同意の電子署名（同意ログ・タイムスタンプ・改ざん防止ハッシュ）
- [ ] P3-4: 虐待防止・身体拘束適正化の記録（委員会・研修・身体拘束記録）
- [ ] P3-5: BCP・安全計画・自己評価結果公表の管理
- [ ] P3-6: 記録の保存期間管理（retention_until・削除防止・訂正履歴）

## P4: 強化

- [ ] P4-1: 要配慮個人情報の暗号化（encrypted cast）
- [ ] P4-2: 職員アカウントの2FA
- [ ] P4-3: 請求CSV/PDF 出力ファイルの保持期限付き管理
- [ ] P4-4: 同日複数サービス対応（usage_records ユニーク制約の見直し）
- [ ] P4-5: uCRM残骸の削除（Customer/Item/Purchase/Analysis/InertiaTest）
- [ ] P4-6: セキュリティヘッダ（CSP 等）
- [ ] P4-7: facility スコープのグローバルスコープ化（BelongsToFacility trait）
