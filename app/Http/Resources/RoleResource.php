<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'users_count' => $this->whenCounted('users'),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
