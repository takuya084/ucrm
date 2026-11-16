<?php

namespace App\Http\Controllers;

use App\Models\DailyActivityPlan;
use App\Models\Program;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DailyActivityPlanController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $validated['date'] ?? today()->toDateString();
        $year = (int) substr($date, 0, 4);
        $facilityId = $this->facilityId();

        return Inertia::render('DailyActivities/Index', [
            'date' => $date,
            'year' => $year,
            'categories' => DailyActivityPlan::CATEGORIES,
            'activities' => DailyActivityPlan::forDate($facilityId, $date),
            'annualEvents' => DailyActivityPlan::where('facility_id', $facilityId)
                ->where('category', 'event')
                ->whereBetween('activity_date', [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)])
                ->with('staff:id,name')
                ->orderBy('activity_date')
                ->orderBy('starts_at')
                ->orderBy('id')
                ->get(),
            'programs' => Program::where('facility_id', $facilityId)->active()
                ->orderBy('name')->get(['id', 'name', 'category']),
            'staff' => Staff::where('facility_id', $facilityId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $facilityId = $this->facilityId();
        $fields = $this->fields($request, $facilityId);
        $activity = new DailyActivityPlan($fields);
        $activity->facility_id = $facilityId;
        $activity->save();

        return to_route('daily-activities.index', ['date' => $activity->activity_date]);
    }

    public function update(Request $request, DailyActivityPlan $activity)
    {
        $facilityId = $this->facilityId();
        abort_if($activity->facility_id !== $facilityId, 403);
        $activity->update($this->fields($request, $facilityId));

        return to_route('daily-activities.index', ['date' => $activity->activity_date]);
    }

    public function destroy(DailyActivityPlan $activity)
    {
        abort_if($activity->facility_id !== $this->facilityId(), 403);
        $date = $activity->activity_date;
        $activity->delete();

        return to_route('daily-activities.index', ['date' => $date]);
    }

    private function fields(Request $request, int $facilityId): array
    {
        $fields = $request->validate([
            'activity_date' => ['required', 'date_format:Y-m-d'],
            'category' => ['required', Rule::in(array_keys(DailyActivityPlan::CATEGORIES))],
            'program_id' => ['present', 'nullable', 'integer', Rule::exists('programs', 'id')->where('facility_id', $facilityId)],
            'title' => ['required_without:program_id', 'nullable', 'string', 'max:255'],
            'staff_id' => ['present', 'nullable', 'integer', Rule::exists('staff', 'id')->where('facility_id', $facilityId)],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'materials' => ['nullable', 'string', 'max:2000'],
        ]);

        $fields['title'] = $fields['title'] ?? Program::where('facility_id', $facilityId)
            ->findOrFail($fields['program_id'])->name;

        return $fields;
    }
}
