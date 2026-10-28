# ucrm / p-yoyaku 連携検証台帳

## 対象と判定範囲

- ucrm基準: `f45f55855f5485c8df52725d93846c5fd069e71a`（main）
- p-yoyaku基準: `56b0697908e0391a047c59044e6d70a06b90f458`（develop）
- 架空データのみ。実Mac、Gateway、MCP、実DB、Stripe、メール、実APIは操作しない。
- 現段階は送受信契約と個別の実コードの検証。両アプリ同時起動のHTTP総合E2Eではない。

## 実装されている経路

| 方向 | 入口 | 用途 |
|---|---|---|
| ucrm → p-yoyaku | GET /api/users、POST /api/users | 児童照合・作成 |
| ucrm → p-yoyaku | GET /api/schedule/daily、/actuals | 送迎予定・実績取得 |
| ucrm → p-yoyaku | PUT /api/integration/bookings | 番号付き予約同期・明示取消（旧APIは一般APIとして維持） |
| p-yoyaku → ucrm | POST /api/webhooks/yoyaku | 予約作成・変更・削除通知 |
| ucrm → p-yoyaku | POST /api/contact-notes | **送り手のみ。対象p-yoyakuのroutes/api.phpに受信経路がない** |
| p-yoyaku → ucrm | contact_note.read / guardian_entry | **受け手のみ。対象p-yoyakuに送信側・連絡帳機能が見当たらない** |

APIはSanctumトークン＋admin、通知は事業所ごとのHMAC署名。認証方法は別である。

## 前段階で修正した受信不具合

- sent_atの欠落・空欄がCarbonで現在時刻と解釈されていた。明示的な絶対時刻のみ許可する。
- 未知のイベントも予約更新に進んでいた。既知5イベントに限定する。
- 存在しない日付が翌月へ繰り上がって処理され得た。暦上の日付を検証する。
- SQLiteで同じ通知を再送・削除済みレコードへ送信すると一意制約エラー。日付部分で既存を照合する。
- 型の違うID・payload・時刻等をDB書込み前に拒否する。

既存出欠statusを勝手に書き換えない。前段階の検証は順番どおりの再送まで。今回、取消・順序逆転防止を追加した。詳細は[BOOKING_CANCELLATION.md](BOOKING_CANCELLATION.md)。取消では送迎フラグに加えて時刻も解除し、ローカルで欠席・取消・削除した出欠は通知で復活させない。

## 送信側との契約

`tests/fixtures/integration/booking.json`を両リポジトリに同一内容で置く。
p-yoyaku側は実Observer→実SendYoyakuWebhook→HTTP fakeのJSONとHMACを照合する。
ucrm側は同じJSONを実Webhookルートに送り、DB更新と送り返し抑止を確認する。
fixtureを独立に変更して合格扱いにせず、両リポジトリの差分・SHA256一致を照合する。

## 未解決事項（連携全体を合格にしない理由）

1. **取消送信：今回修正**。明示取消・取消確認番号・削除記録を導入。既存データを一括修復する操作は未実施。
2. **順序逆転：今回修正**。送信時刻とは別の永続番号を導入し、送信時に最新状態を投影。MySQL独立プロセスの並行登録・取消も検証対象に追加。
3. **連絡帳**: 受信APIと保護者側の表示・既読・家庭記入が対象p-yoyakuに未実装。公開範囲を確認し、内部支援記録を送らない仕様で実装・検証する。
4. **送信失敗：予約経路は今回修正**。例外でキューの失敗管理へ伝える。未送信一覧・手動再送の運用UI、他API経路は未対応。
5. **事業所設定**: 施設トークン未設定時の共通トークンへのfallback、事業所の重複紐付け、児童の重複紐付けを検査する。
6. **送信時刻形式：今回修正**。予約時刻をHH:mmへ統一し、明示cancelledで送迎なしと取消を区別する。
7. **請求・実績**: importActualsを含む確定済み請求月の保護、出欠と送迎実績を混同しないことを検証する。
8. **同時起動E2E**: 両リポジトリはprivate。既存CIのGITHUB_TOKENだけで別リポジトリを取得できるとは仮定しない。固定SHAの両ソースを安全に用意できる隔離環境で、実認証・実HTTP・再送・切断・復帰を確認する。契約テスト成功で代用しない。

## 次の実施順

今回の取消・順序制御PRを確認後、児童/事業所紐付け → 連絡帳の不足実装 → 両アプリ接続E2Eへ進む。
各段階で失敗を再現してから修正し、単体・ブラウザの既存検証も維持する。
