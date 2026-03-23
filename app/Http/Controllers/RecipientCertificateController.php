<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecipientCertificateRequest;
use App\Http\Requests\UpdateRecipientCertificateRequest;
use App\Models\Child;
use App\Models\RecipientCertificate;
use Inertia\Inertia;

class RecipientCertificateController extends Controller
{
    /** 登録フォーム */
    public function create(Child $child)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        return Inertia::render('Children/Certificate/Create', [
            'child' => $child->only('id', 'name'),
        ]);
    }

    /** 登録処理 */
    public function store(StoreRecipientCertificateRequest $request, Child $child)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        // 新しくactiveにする場合、既存のactiveを expiredに更新
        if ($request->status === 'active') {
            $child->recipientCertificates()
                ->where('status', 'active')
                ->update(['status' => 'expired']);
        }

        $child->recipientCertificates()->create($request->validated());

        return to_route('children.show', $child)
            ->with(['message' => '受給者証を登録しました。', 'status' => 'success']);
    }

    /** 編集フォーム */
    public function edit(Child $child, RecipientCertificate $certificate)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        abort_if($certificate->child_id !== $child->id, 404);

        return Inertia::render('Children/Certificate/Edit', [
            'child'       => $child->only('id', 'name'),
            'certificate' => $certificate,
        ]);
    }

    /** 更新処理 */
    public function update(
        UpdateRecipientCertificateRequest $request,
        Child $child,
        RecipientCertificate $certificate
    ) {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        abort_if($certificate->child_id !== $child->id, 404);

        // 有効に変更する場合、他のactiveをexpiredに
        if ($request->status === 'active' && $certificate->status !== 'active') {
            $child->recipientCertificates()
                ->where('status', 'active')
                ->where('id', '!=', $certificate->id)
                ->update(['status' => 'expired']);
        }

        $certificate->update($request->validated());

        return to_route('children.show', $child)
            ->with(['message' => '受給者証を更新しました。', 'status' => 'success']);
    }

    /** 削除 */
    public function destroy(Child $child, RecipientCertificate $certificate)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        abort_if($certificate->child_id !== $child->id, 404);

        $certificate->delete();

        return to_route('children.show', $child)
            ->with(['message' => '受給者証を削除しました。', 'status' => 'success']);
    }
}
