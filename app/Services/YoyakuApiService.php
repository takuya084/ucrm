<?php

namespace App\Services;

use App\Models\Facility;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YoyakuApiService
{
    private string $baseUrl;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.houkago_plus.base_url') ?? '', '/');
        $this->timeout = (int) config('services.houkago_plus.timeout', 5);
    }

    /**
     * 当該施設に紐付くトークンで Http クライアントを構築
     */
    private function client(?int $facilityId = null): ?PendingRequest
    {
        // Every request must identify its facility and use only that facility's
        // credentials. A global token could silently address another business.
        if ($facilityId === null || $facilityId <= 0 || empty($this->baseUrl)) {
            return null;
        }
        $token = Facility::whereKey($facilityId)->value('yoyaku_api_token');
        if (!is_string($token) || trim($token) === '') {
            return null;
        }
        return Http::withToken($token)->acceptJson()->timeout($this->timeout);
    }

    /**
     * 指定日・事業所の送迎予約一覧
     */
    public function getDailySchedule(string $date, int $businessId, ?int $facilityId = null): ?array
    {
        $client = $this->client($facilityId);
        if (!$client) return null;

        return $this->safe(fn() =>
            $client->get("{$this->baseUrl}/api/schedule/daily", [
                'date'        => $date,
                'business_id' => $businessId,
            ])
        );
    }

    /**
     * 実績一覧（乗降時刻付き）
     */
    public function getActuals(string $date, int $businessId, ?int $facilityId = null): ?array
    {
        $client = $this->client($facilityId);
        if (!$client) return null;

        return $this->safe(fn() =>
            $client->get("{$this->baseUrl}/api/schedule/actuals", [
                'date'        => $date,
                'business_id' => $businessId,
            ])
        );
    }

    /**
     * 予約作成（冪等: external_ref を渡すと upsert）
     */
    public function createBooking(array $payload, ?int $facilityId = null): ?array
    {
        $client = $this->client($facilityId);
        if (!$client) return null;

        return $this->safe(fn() =>
            $client->post("{$this->baseUrl}/api/yoyaku", $payload)
        );
    }

    /** No fallback to legacy create: an unconfirmed cancellation must fail visibly. */
    public function syncBooking(array $payload, int $facilityId): ?string
    {
        $client = $this->client($facilityId);
        if (!$client) throw new \RuntimeException('Booking synchronization failed');
        try {
            $response=$client->put("{$this->baseUrl}/api/integration/bookings",$payload);
            if (!$response->successful() || $response->json('ok')!==true
                || !is_bool($response->json('applied'))) {
                throw new \RuntimeException('Booking synchronization failed');
            }
            $token=$response->json('cancellation_token');
            if ($token!==null && (!is_string($token) || !\Illuminate\Support\Str::isUuid($token))) {
                throw new \RuntimeException('Booking synchronization failed');
            }
            return $token;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Booking synchronization failed');
        }
    }

    public function updateBooking(int $yoyakuId, array $payload, ?int $facilityId = null): ?array
    {
        $client = $this->client($facilityId);
        if (!$client) return null;

        return $this->safe(fn() =>
            $client->put("{$this->baseUrl}/api/yoyaku/{$yoyakuId}", $payload)
        );
    }

    public function deleteBooking(int $yoyakuId, ?int $facilityId = null): bool
    {
        $client = $this->client($facilityId);
        if (!$client) return false;

        $result = $this->safe(fn() =>
            $client->delete("{$this->baseUrl}/api/yoyaku/{$yoyakuId}")
        );
        return $result !== null;
    }

    /**
     * 連絡帳を p-yoyaku へ配信（冪等: external_ref で upsert）。
     * 保護者に公開する内容のみを送ること（内部の見立ては送らない）。
     */
    public function upsertContactNote(array $payload, ?int $facilityId = null): ?array
    {
        $client = $this->client($facilityId);
        if (!$client) return null;

        return $this->safe(fn() =>
            $client->post("{$this->baseUrl}/api/contact-notes", $payload)
        );
    }

    /**
     * p-yoyaku 側の利用者(児童)一覧。既存アカウントとの突合参照用
     */
    public function listUsers(int $businessId, ?int $facilityId = null): ?array
    {
        $client = $this->client($facilityId);
        if (!$client) return null;

        return $this->safe(fn() =>
            $client->get("{$this->baseUrl}/api/users", [
                'business_id' => $businessId,
            ])
        );
    }

    /**
     * p-yoyaku 側に利用者(児童)アカウントを作成（冪等: external_ref を渡すと upsert）。
     * 新規作成時のみレスポンスに平文パスワードが含まれる。
     */
    public function createUser(array $payload, ?int $facilityId = null): ?array
    {
        $client = $this->client($facilityId);
        if (!$client) return null;

        return $this->safe(fn() =>
            $client->post("{$this->baseUrl}/api/users", $payload)
        );
    }

    /**
     * Http 呼び出しの共通例外ハンドリング
     */
    private function safe(\Closure $fn): ?array
    {
        try {
            $response = $fn();
            if ($response->successful()) {
                return $response->json();
            }
            Log::warning('YoyakuAPI: non-2xx', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('YoyakuAPI: connection failed', ['message' => $e->getMessage()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('YoyakuAPI: unexpected error', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
