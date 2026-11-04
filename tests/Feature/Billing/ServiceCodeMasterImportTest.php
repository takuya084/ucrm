<?php

namespace Tests\Feature\Billing;

use App\Models\Facility;
use App\Models\ServiceCodeMaster;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * サービスコードマスターCSV取込（プレビュー→承認の2段階）のテスト。
 * 即時反映による誤反映を防ぐための安全策（docs/nhif-csv-todo.md）。
 *
 * NOTE: withoutMiddleware() を使用している。この環境では EncryptCookies 等の
 * グローバルミドルウェアが MissingAppKeyException を起こす既存の問題があり
 * （本テストに限らず $this->post() を使う既存テスト全般で再現する、本ブランチの
 * 変更とは無関係の環境課題）、ミドルウェアを外してコントローラのロジックのみを検証する。
 */
class ServiceCodeMasterImportTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;
    private User $leaderUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        $this->facility = Facility::create([
            'name'             => 'テスト事業所',
            'service_type'     => 'houday',
            'area_unit_price'  => 10.00,
            'capacity_per_day' => 10,
        ]);

        $this->leaderUser = User::factory()->create();
        Staff::create([
            'user_id'     => $this->leaderUser->id,
            'facility_id' => $this->facility->id,
            'name'        => 'テストリーダー',
            'role'        => 'leader',
            'is_active'   => true,
        ]);

        ServiceCodeMaster::create([
            'revision_date' => '2024-04-01',
            'service_type'  => 'houday',
            'service_code'  => '631000',
            'service_name'  => '放デイ1・授業終了後（定員10人以下）',
            'unit_count'    => 604,
            'unit_type'     => 'per_day',
            'category'      => 'base',
            'valid_from'    => '2024-04-01',
        ]);
    }

    private function csvContent(): string
    {
        $header = "報酬改定日,サービス種別,サービスコード,サービス内容名,単位数,単位種別,区分,有効開始日,有効終了日\n";
        // 既存コード631000は単位数を変更、636100は新規
        $changed = "2024-04-01,houday,631000,放デイ1・授業終了後（定員10人以下）,650,per_day,base,2024-04-01,\n";
        $new     = "2024-04-01,houday,636100,送迎加算（片道）,54,per_time,addition,2024-04-01,\n";

        return $header . $changed . $new;
    }

    public function test_preview_does_not_modify_database(): void
    {
        $this->actingAs($this->leaderUser);

        $response = $this->post(route('billing.settings.service-codes.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('codes.csv', $this->csvContent()),
        ]);

        $response->assertSessionHas('import_token');
        $response->assertSessionHas('import_diff');

        $diff = $response->getSession()->get('import_diff');
        $this->assertCount(1, $diff['new']);
        $this->assertCount(1, $diff['changed']);
        $this->assertSame(604, $diff['changed'][0]['changed_fields']['unit_count']['from']);
        $this->assertSame(650, $diff['changed'][0]['changed_fields']['unit_count']['to']);

        // プレビューだけではDBは変わらない
        $this->assertSame(604, ServiceCodeMaster::where('service_code', '631000')->first()->unit_count);
        $this->assertDatabaseMissing('service_code_masters', ['service_code' => '636100']);
    }

    public function test_apply_reflects_previewed_changes(): void
    {
        $this->actingAs($this->leaderUser);

        $previewResponse = $this->post(route('billing.settings.service-codes.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('codes.csv', $this->csvContent()),
        ]);
        $token = $previewResponse->getSession()->get('import_token');

        $applyResponse = $this->post(route('billing.settings.service-codes.import.apply'), [
            'import_token' => $token,
        ]);
        $applyResponse->assertSessionHas('status', 'success');

        $this->assertSame(650, ServiceCodeMaster::where('service_code', '631000')->first()->unit_count);
        $this->assertDatabaseHas('service_code_masters', ['service_code' => '636100', 'unit_count' => 54]);
    }

    public function test_apply_rejects_expired_or_unknown_token(): void
    {
        $this->actingAs($this->leaderUser);

        $response = $this->post(route('billing.settings.service-codes.import.apply'), [
            'import_token' => 'not-a-real-token',
        ]);

        $response->assertSessionHas('status', 'warning');
        $this->assertSame(604, ServiceCodeMaster::where('service_code', '631000')->first()->unit_count);
    }
}
