<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReimbursementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ApprovalNotesRequest;
use App\Http\Resources\ApprovalResource;
use App\Http\Resources\ReimbursementResource;
use App\Models\Reimbursement;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $service) {}

    /** Riwayat approval (timeline persetujuan). */
    public function history(Reimbursement $reimbursement): AnonymousResourceCollection
    {
        $this->authorize('view', $reimbursement);

        return ApprovalResource::collection(
            $reimbursement->approvals()->with('approver')->orderByDesc('acted_at')->get(),
        );
    }

    public function approve(Request $request, Reimbursement $reimbursement): ReimbursementResource
    {
        $this->authorizeAction($reimbursement);

        $notes = $request->validate(['notes' => ['nullable', 'string', 'max:1000']])['notes'] ?? null;
        $reimbursement = $this->service->approve($reimbursement, $request->user(), $notes);

        return new ReimbursementResource($reimbursement->load('category'));
    }

    public function reject(ApprovalNotesRequest $request, Reimbursement $reimbursement): ReimbursementResource
    {
        $this->authorizeAction($reimbursement);

        $reimbursement = $this->service->reject($reimbursement, $request->user(), $request->validated()['notes']);

        return new ReimbursementResource($reimbursement->load('category'));
    }

    public function revision(ApprovalNotesRequest $request, Reimbursement $reimbursement): ReimbursementResource
    {
        $this->authorizeAction($reimbursement);

        $reimbursement = $this->service->requestRevision($reimbursement, $request->user(), $request->validated()['notes']);

        return new ReimbursementResource($reimbursement->load('category'));
    }

    /** Otorisasi berdasarkan level yang berlaku untuk status sekarang. */
    private function authorizeAction(Reimbursement $reimbursement): void
    {
        $ability = match ($reimbursement->status) {
            ReimbursementStatus::Submitted => 'approveManager',
            ReimbursementStatus::ManagerApproved => 'approveFinance',
            default => abort(422, 'Reimbursement tidak berada pada status yang dapat diproses.'),
        };

        $this->authorize($ability, $reimbursement);
    }
}
