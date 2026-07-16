<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'reimbursement_id' => $this->reimbursement_id,
            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'currency' => $this->currency,
            'method' => [
                'value' => $this->method->value,
                'label' => $this->method->label(),
            ],
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at,
            'processed_by' => $this->processed_by,
            'processor' => $this->whenLoaded('processor', fn () => [
                'id' => $this->processor->id,
                'name' => $this->processor->name,
            ]),
            'bank_account' => new BankAccountResource($this->whenLoaded('bankAccount')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
        ];
    }
}
