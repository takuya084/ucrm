<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YoyakuApiService
{
    private string $baseUrl;
    private string $token;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl  = config('services.p_yoyaku.base_url');
        $this->token    = config('services.p_yoyaku.api_token') ?? '';
        $this->timeout  = (int) config('services.p_yoyaku.timeout', 5);
    }

    /**
     * 指定日・事業所の送迎予約一覧を取得する
     * 失敗時は null を返す（呼び出し元でフォールバック処理を行う）
     *
     * @return array|null
     */
    public function getDailySchedule(string $date, int $businessId): ?array
    {
        if (empty($this->token) || empty($this->baseUrl)) {
            return null;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/api/schedule/daily", [
                    'date'        => $date,
                    'business_id' => $businessId,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('YoyakuAPI: non-200 response', [
                'status'      => $response->status(),
                'date'        => $date,
                'business_id' => $businessId,
            ]);
            return null;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('YoyakuAPI: connection failed', [
                'message'     => $e->getMessage(),
                'date'        => $date,
                'business_id' => $businessId,
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('YoyakuAPI: unexpected error', [
                'message'     => $e->getMessage(),
                'date'        => $date,
                'business_id' => $businessId,
            ]);
            return null;
        }
    }
}
