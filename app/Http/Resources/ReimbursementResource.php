<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReimbursementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reimbursement_number' => $this->reimbursement_number,
            'claim_type' => [
                'value' => $this->claim_type->value,
                'label' => $this->claim_type->label(),
                'color' => $this->claim_type->color(),
                'icon' => $this->claim_type->icon(),
            ],
            'details' => $this->details,
            // Detail siap tampil (label → nilai terformat) untuk halaman detail.
            'display_details' => $this->display_details,
            'title' => $this->title,
            'description' => $this->description,
            'reason' => $this->reason,
            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'currency' => $this->currency,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'is_editable' => $this->isEditable(),
            // Tahap persetujuan yang sedang ditunggu (null bila tidak ada).
            // Sudah memperhitungkan ambang nominal Direksi, sehingga frontend
            // tak perlu menduplikasi aturannya.
            'pending_approval_level' => $this->pendingApprovalLevel() === null ? null : [
                'value' => $this->pendingApprovalLevel()->value,
                'label' => $this->pendingApprovalLevel()->label(),
            ],
            'is_ready_for_payment' => $this->isReadyForPayment(),
            'needs_director_approval' => $this->needsDirectorApproval(),
            'expense_date' => $this->expense_date?->toDateString(),
            'submitted_at' => $this->submitted_at,
            'completed_at' => $this->completed_at,
            'category_id' => $this->category_id,
            'project_id' => $this->project_id,
            'department_id' => $this->department_id,
            'bank_account_id' => $this->bank_account_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
