<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Inquiry;
use App\Models\Child;
use App\Http\Requests\StoreInquiryRequest;
use App\Http\Requests\UpdateInquiryRequest;

class InquiryController extends Controller
{
    public function index(Request $request)
    {
        $facilityId = auth()->user()->staff?->facility_id;

        $query = Inquiry::with(['child:id,name', 'staff:id,name'])
            ->when($facilityId, fn($q) => $q->whereHas('child', fn($q2) => $q2->where('facility_id', $facilityId)))
            ->orderBy('contacted_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->boolean('escalated')) {
            $query->where('is_escalated', true);
        }

        $inquiries = $query->paginate(20)->withQueryString();

        return Inertia::render('Inquiries/Index', [
            'inquiries'      => $inquiries,
            'filters'        => $request->only(['status', 'category', 'escalated']),
            'statusLabels'   => Inquiry::STATUS_LABELS,
            'categoryLabels' => Inquiry::CATEGORY_LABELS,
        ]);
    }

    public function create(Request $request)
    {
        $facilityId = auth()->user()->staff?->facility_id;

        $children = Child::when($facilityId, fn($q) => $q->where('facility_id', $facilityId))
            ->where('contract_status', 'active')
            ->orderBy('name_kana')
            ->get(['id', 'name']);

        return Inertia::render('Inquiries/Create', [
            'children'             => $children,
            'categoryLabels'       => Inquiry::CATEGORY_LABELS,
            'contactMethodLabels'  => Inquiry::CONTACT_METHOD_LABELS,
            'prefillChildId'       => $request->input('child_id') ? (int) $request->input('child_id') : null,
        ]);
    }

    public function store(StoreInquiryRequest $request)
    {
        $data = $request->validated();
        $data['staff_id'] = auth()->user()->staff?->id;

        Inquiry::create($data);

        return redirect()->route('inquiries.index')
            ->with(['message' => '問い合わせを登録しました。', 'status' => 'success']);
    }

    public function show(Inquiry $inquiry)
    {
        $this->authorizeInquiry($inquiry);

        $inquiry->load(['child:id,name', 'guardian:id,name', 'staff:id,name']);

        return Inertia::render('Inquiries/Show', [
            'inquiry'            => $inquiry,
            'statusLabels'       => Inquiry::STATUS_LABELS,
            'categoryLabels'     => Inquiry::CATEGORY_LABELS,
            'contactMethodLabels'=> Inquiry::CONTACT_METHOD_LABELS,
        ]);
    }

    public function edit(Inquiry $inquiry)
    {
        $this->authorizeInquiry($inquiry);

        $facilityId = auth()->user()->staff?->facility_id;
        $children = Child::when($facilityId, fn($q) => $q->where('facility_id', $facilityId))
            ->where('contract_status', 'active')
            ->orderBy('name_kana')
            ->get(['id', 'name']);

        $inquiry->load(['child:id,name']);

        return Inertia::render('Inquiries/Edit', [
            'inquiry'            => $inquiry,
            'children'           => $children,
            'statusLabels'       => Inquiry::STATUS_LABELS,
            'categoryLabels'     => Inquiry::CATEGORY_LABELS,
            'contactMethodLabels'=> Inquiry::CONTACT_METHOD_LABELS,
        ]);
    }

    public function update(UpdateInquiryRequest $request, Inquiry $inquiry)
    {
        $this->authorizeInquiry($inquiry);

        $inquiry->update($request->validated());

        return redirect()->route('inquiries.show', $inquiry->id)
            ->with(['message' => '問い合わせを更新しました。', 'status' => 'success']);
    }

    public function destroy(Inquiry $inquiry)
    {
        $this->authorizeInquiry($inquiry);
        $inquiry->delete();

        return redirect()->route('inquiries.index')
            ->with(['message' => '問い合わせを削除しました。', 'status' => 'success']);
    }

    private function authorizeInquiry(Inquiry $inquiry): void
    {
        $facilityId = auth()->user()->staff?->facility_id;
        if ($facilityId) {
            abort_if($inquiry->child->facility_id !== $facilityId, 403);
        }
    }
}
