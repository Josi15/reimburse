<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyAccountDepositResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_bank_account_id' => $this->company_bank_account_id,
            'company_account' => new CompanyBankAccountResource($this->whenLoaded('companyBankAccount')),
            'amount' => $this->amount,
            'formatted_amount' => 'Rp '.number_format((int) $this->amount, 0, ',', '.'),
            'deposited_at' => $this->deposited_at?->toDateString(),
            'note' => $this->note,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at,
        ];
    }
}
