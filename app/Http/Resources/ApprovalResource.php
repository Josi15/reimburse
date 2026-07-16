<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level->value,
            'level_label' => $this->level->label(),
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'notes' => $this->notes,
            'acted_at' => $this->acted_at,
            'approver' => $this->whenLoaded('approver', fn () => [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ]),
        ];
    }
}
