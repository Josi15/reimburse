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
            'expense_date' => $this->expense_date?->toDateString(),
            'submitted_at' => $this->submitted_at,
            'completed_at' => $this->completed_at,
            'category_id' => $this->category_id,
            'department_id' => $this->department_id,
            'bank_account_id' => $this->bank_account_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
