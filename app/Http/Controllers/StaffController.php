<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\Staff;
use App\Models\StaffQualification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StaffController extends Controller
{
    const ROLE_LABELS = [
        'admin'  => '管理者',
        'leader' => '児発管',
        'staff'  => '職員',
    ];

    public function index()
    {
        $staffMembers = Staff::where('facility_id', $this->facilityId())
            ->with('user:id,email')
            ->orderByRaw("FIELD(role, 'admin', 'leader', 'staff')")
            ->orderBy('name')
            ->get(['id', 'user_id', 'name', 'role', 'is_active', 'joined_at']);

        return Inertia::render('Staff/Index', [
            'staffMembers' => $staffMembers,
            'roleLabels'   => self::ROLE_LABELS,
        ]);
    }

    public function create()
    {
        return Inertia::render('Staff/Create', [
            'roleLabels' => self::ROLE_LABELS,
        ]);
    }

    public function store(StoreStaffRequest $request)
    {
        $facilityId = $this->facilityId();
        $email = strtolower(trim($request->email));

        $result = DB::transaction(function () use ($request, $facilityId, $email) {
            $user = User::create([
                'name'              => $request->name,
                'email'             => $email,
                'password'          => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ]);

            $staff = Staff::create([
                'user_id'     => $user->id,
                'facility_id' => $facilityId,
                'name'        => $request->name,
                'role'        => $request->role,
                'is_active'   => true,
                'joined_at'   => now()->toDateString(),
            ]);

            return [$user, $staff];
        });

        // パスワード設定メール送信（失敗しても登録は完了扱い）
        try {
            Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $e) {
            Log::error('Staff password reset mail failed', [
                'email'   => $email,
                'message' => $e->getMessage(),
            ]);
        }

        return to_route('staff.index')
            ->with(['message' => '職員を登録しました。パスワード設定メールを送信しました。', 'status' => 'success']);
    }

    public function edit(Staff $staff)
    {
        abort_if($staff->facility_id !== $this->facilityId(), 403);

        $staff->load('user:id,email', 'qualifications');

        return Inertia::render('Staff/Edit', [
            'staff'              => $staff->only('id', 'name', 'role', 'is_active', 'user'),
            'roleLabels'         => self::ROLE_LABELS,
            'qualifications'     => $staff->qualifications->pluck('qualification')->values(),
            'qualificationTypes' => StaffQualification::TYPES,
        ]);
    }

    public function update(UpdateStaffRequest $request, Staff $staff)
    {
        abort_if($staff->facility_id !== $this->facilityId(), 403);

        $staff->update($request->only('name', 'role', 'is_active'));

        // 資格の同期
        $qualifications = $request->input('qualifications', []);
        $staff->qualifications()->delete();
        foreach ($qualifications as $q) {
            $staff->qualifications()->create(['qualification' => $q]);
        }

        return to_route('staff.index')
            ->with(['message' => '職員情報を更新しました。', 'status' => 'success']);
    }

    public function destroy(Staff $staff)
    {
        abort_if($staff->facility_id !== $this->facilityId(), 403);

        // 自分自身は削除不可
        abort_if($staff->user_id === auth()->id(), 403, '自分自身は削除できません。');

        $staff->delete();

        return to_route('staff.index')
            ->with(['message' => '職員を削除しました。', 'status' => 'success']);
    }
}
