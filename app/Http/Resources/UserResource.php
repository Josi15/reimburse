<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'department_id' => $this->department_id,
            'manager_id' => $this->manager_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager?->id,
                'name' => $this->manager?->name,
            ]),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
