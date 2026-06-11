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

## P1: 〜1ヶ月（法令・基盤リスク）

- [x] P1-1: OpenAI 送信データの仮名化（実名除去）＋ AI 利用同意フラグ（児童単位）
      ※要実行: `php artisan migrate`（Windows/XAMPP側）と `npm run build`。TLS検証無効化（withoutVerifying）も同時に修正
- [ ] P1-2: 監査ログ（audit_logs）導入 — 個人情報の閲覧・変更・出力の記録
- [ ] P1-3: 請求エンジンのテスト整備（BillingCalculationService / CopaymentCapService / ServiceCodeResolver）
- [ ] P1-4: Laravel 11/12 + PHP 8.3 への移行（Sanctum 含む）
- [ ] P1-5: 出欠記録の物理削除廃止（SoftDeletes + 請求確定済み月の編集ガード）

## P2: 〜3ヶ月（請求の制度適合 — 完了まで実運用請求は不可）

- [ ] P2-1: R6 基本報酬の時間区分対応（個別支援計画の支援時間フィールド + 区分判定）
- [ ] P2-2: R6 加算・減算マスタ刷新（家族支援・個別サポートⅠ〜Ⅲ・専門的支援体制/実施ほか）
- [ ] P2-3: 加算の月回数上限制御（欠席時対応加算 月4回 等のカウンタ）
- [ ] P2-4: 欠席時対応の記録テーブル（連絡日時・対応者・相談援助内容）
- [ ] P2-5: 処遇改善加算の月次%計算ロジック
- [ ] P2-6: 国保連インタフェース仕様準拠CSV（請求書・明細書・実績記録票・上限管理結果票）
- [ ] P2-7: 契約情報テーブル（契約支給量・契約開始日・記入欄番号）
- [ ] P2-8: 就学前無償化・多子軽減・自治体助成の負担額処理
- [ ] P2-9: 上限管理の配分方式見直し（管理事業所優先充当方式・自治体ルール設定化）
- [ ] P2-10: 明細書への上限管理結果反映（管理事業所番号・結果コード・結果額）

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
