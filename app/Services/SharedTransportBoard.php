<?php

namespace App\Services;

use App\Models\Facility;

class SharedTransportBoard
{
    public function __construct(private YoyakuApiService $api) {}

    public function forDate(int $facilityId, string $date): ?array
    {
        $facility = Facility::findOrFail($facilityId);
        if (!$facility->yoyaku_business_id) return null;
        $data = $this->api->getTransport($date, (int) $facility->yoyaku_business_id, $facilityId);
        if ($data === null) return ['state' => 'unavailable', 'visits' => [], 'assignments' => [], 'staff' => []];
        $locations = ['school' => '学校', 'home' => '自宅', 'parents' => '親送迎', 'others' => 'その他'];
        $visits = collect($data['reservations'])->map(fn ($row) => [
            'childId' => (int) $row['user_id'], 'name' => $row['name'],
        ])->unique('childId')->values();
        $assignments = [];
        foreach ($data['reservations'] as $row) {
            foreach (['pickup', 'dropoff'] as $direction) {
                $time = $row[$direction.'_time'] ?? null;
                $location = $row[$direction.'_location'] ?? null;
                if (!$time || $location === 'parents') continue;
                $assignments[] = [
                    'reservation_id' => (int) $row['id'], 'revision' => (int) $row['revision'],
                    'child_id' => (int) $row['user_id'], 'direction' => $direction,
                    'staff_id' => $row[$direction.'_staff_id'], 'staff_name' => $row[$direction.'_staff_name'],
                    'scheduled_time' => $time, 'location' => $locations[$location] ?? $location,
                ];
            }
        }
        usort($assignments, fn ($a, $b) => strcmp($a['scheduled_time'], $b['scheduled_time'])
            ?: $a['reservation_id'] <=> $b['reservation_id']);
        return ['state' => 'connected', 'visits' => $visits, 'assignments' => $assignments, 'staff' => $data['staff']];
    }
}
