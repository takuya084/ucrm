<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptedCastTest extends TestCase
{
    use RefreshDatabase;

    private function createChild(array $attributes = []): Child
    {
        $facility = Facility::create(['name' => 'テスト事業所', 'capacity_per_day' => 10]);

        return Child::create(array_merge([
            'facility_id'     => $facility->id,
            'name'            => 'テスト児童',
            'contract_status' => 'active',
        ], $attributes));
    }

    public function test_要配慮情報はDB上で暗号化され読み出し時に復号される(): void
    {
        $child = $this->createChild([
            'disability_note' => '自閉スペクトラム症',
            'allergy_note'    => '卵アレルギー',
            'care_note'       => 'てんかん発作に注意',
        ]);

        $raw = DB::table('children')->where('id', $child->id)->first();

        // DBの生データは平文ではない
        $this->assertNotSame('自閉スペクトラム症', $raw->disability_note);
        $this->assertNotSame('卵アレルギー', $raw->allergy_note);
        $this->assertNotSame('てんかん発作に注意', $raw->care_note);

        // モデル経由では復号される
        $fresh = $child->fresh();
        $this->assertSame('自閉スペクトラム症', $fresh->disability_note);
        $this->assertSame('卵アレルギー', $fresh->allergy_note);
        $this->assertSame('てんかん発作に注意', $fresh->care_note);
    }

    public function test_暗号化導入前の平文データもそのまま読める(): void
    {
        $child = $this->createChild();

        // 暗号化導入前の平文データを直接挿入
        DB::table('children')->where('id', $child->id)->update([
            'care_note' => '平文の配慮事項',
        ]);

        $this->assertSame('平文の配慮事項', $child->fresh()->care_note);
    }

    public function test_一括暗号化コマンドが平文データを暗号化する(): void
    {
        $child = $this->createChild();
        DB::table('children')->where('id', $child->id)->update([
            'care_note' => '平文の配慮事項',
        ]);

        $this->artisan('app:encrypt-sensitive-data')->assertSuccessful();

        $raw = DB::table('children')->where('id', $child->id)->value('care_note');
        $this->assertNotSame('平文の配慮事項', $raw);
        $this->assertSame('平文の配慮事項', $child->fresh()->care_note);

        // 再実行しても二重暗号化されない
        $this->artisan('app:encrypt-sensitive-data')->assertSuccessful();
        $this->assertSame('平文の配慮事項', $child->fresh()->care_note);
    }
}
