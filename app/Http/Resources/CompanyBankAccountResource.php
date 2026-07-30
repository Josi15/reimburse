<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyBankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_id' => $this->bank_id,
            'bank' => new BankResource($this->whenLoaded('bank')),
            'label' => $this->label,
            'account_number' => $this->account_number,
            'masked_number' => $this->masked_number,
            'account_holder_name' => $this->account_holder_name,
            'opening_balance' => $this->opening_balance,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
