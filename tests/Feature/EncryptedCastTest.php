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

        $raw = (object) DB::connection()->getPdo()->query('SELECT * FROM children WHERE id='.(int)$child->id)->fetch(\PDO::FETCH_ASSOC);

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

}
