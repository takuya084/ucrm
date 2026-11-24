# 選択欄のコピペ操作を両アプリで統一

入力用・絞り込み用のプルダウンは、候補を選ぶ操作を維持したまま、⌘/Ctrl+C・Vでコピー・貼り付けできます。コピーは表示名、貼り付けはその欄に現在登録されている選択肢との一致で判定します。

未登録・無効・選択できない時刻・複数候補で特定できない値は、元の入力を変えず警告します。同名の候補はコピー時に番号を付けて区別します。複数選択欄は1列の複数行をまとめて検証します。

シフト表・勤務パターン・送迎表の範囲コピペは専用処理を優先します。送迎表も、不正な値が1つあれば貼り付け全体を拒否する動作に統一します。候補のある入力欄は通常のテキスト入力と部分コピーも維持します。

共通処理はdocumentでイベントを受けるため、Vue/Inertiaの画面遷移や後から追加・更新されるフォームにも適用されます。シフトの専用処理が処理済みのイベントを二重適用しません。

## 確認した選択欄

| アプリ | ファイル | selectタグ | 共通部品の利用 | 独自候補 |
|---|---|---:|---:|---|
| p-yoyaku | `resources/views/business/edit.blade.php` | 1 | 2 |  |
| p-yoyaku | `resources/views/business/settings.blade.php` | 1 | 2 |  |
| p-yoyaku | `resources/views/components/select.blade.php` | 1 | 0 |  |
| p-yoyaku | `resources/views/components/time-select.blade.php` | 1 | 0 |  |
| p-yoyaku | `resources/views/profile/register.blade.php` | 1 | 2 |  |
| p-yoyaku | `resources/views/subscribe-create.blade.php` | 0 | 2 |  |
| p-yoyaku | `resources/views/yoyaku/adeditYoyaku.blade.php` | 3 | 0 |  |
| p-yoyaku | `resources/views/yoyaku/index.blade.php` | 4 | 0 |  |
| p-yoyaku | `resources/views/yoyaku/transport_assignment.blade.php` | 1 | 0 |  |
| p-yoyaku | `public/transport-grid/grid.js` | 3 | 0 | あり |
| UCRM | `resources/js/Components/DailyTransportBoard.vue` | 5 | 0 |  |
| UCRM | `resources/js/Components/ShiftCell.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/Billing/CapManagement/Index.vue` | 1 | 0 |  |
| UCRM | `resources/js/Pages/Billing/CapManagement/Show.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/Billing/DailyRecords/Index.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/Billing/ErrorClaims/Create.vue` | 1 | 0 |  |
| UCRM | `resources/js/Pages/Billing/Invoices/Index.vue` | 1 | 0 |  |
| UCRM | `resources/js/Pages/Billing/Invoices/Show.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/Billing/Returns/Index.vue` | 6 | 0 |  |
| UCRM | `resources/js/Pages/Children/Certificate/Create.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/Children/Certificate/Edit.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/Children/Create.vue` | 3 | 0 |  |
| UCRM | `resources/js/Pages/Children/Edit.vue` | 3 | 0 |  |
| UCRM | `resources/js/Pages/Children/Index.vue` | 1 | 0 |  |
| UCRM | `resources/js/Pages/ContactNotes/Index.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/DailyActivities/Index.vue` | 3 | 0 |  |
| UCRM | `resources/js/Pages/ExternalFacilities/_Form.vue` | 1 | 0 |  |
| UCRM | `resources/js/Pages/Facility/Edit.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/Inquiries/Create.vue` | 5 | 0 |  |
| UCRM | `resources/js/Pages/Inquiries/Edit.vue` | 5 | 0 |  |
| UCRM | `resources/js/Pages/Inquiries/Index.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/OperationRecords/Index.vue` | 3 | 0 |  |
| UCRM | `resources/js/Pages/SchoolCalendar/Index.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/StaffWorkPatterns/Edit.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/SupportPlans/Show.vue` | 2 | 0 |  |
| UCRM | `resources/js/Pages/SupportRecords/Create.vue` | 1 | 0 |  |
| UCRM | `resources/js/Pages/SupportRecords/Edit.vue` | 1 | 0 |  |
| UCRM | `resources/js/Pages/UsageRecords/Index.vue` | 3 | 0 |  |
| UCRM | `public/transport-grid/grid.js` | 3 | 0 | あり |
| UCRM | `public/absence-board/board.js` | 5 | 0 |  |

| p-yoyaku | `public/absence-board/board.js` | 5 | 0 |  |

タグ数はソース上の記述箇所です。繰り返し表示される児童・職員・日付の行数ではありません。

## 検証

表示名と内部IDの区別、同名候補、無効な選択肢、時刻、複数選択、引用符を含むExcelデータを検証します。実ブラウザでは通常の選択欄と実際の児童フォーム、画面の再描画後の欄、シフトと送迎の専用範囲処理との共存をPC・スマホで確認します。
