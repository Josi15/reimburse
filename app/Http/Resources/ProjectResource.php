<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
            ]),
            // Anggota proyek (karyawan/magang yang ditugaskan).
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email ?? null,
                'department' => $m->relationLoaded('department') ? $m->department?->name : null,
            ])->all()),
            'member_ids' => $this->whenLoaded('members', fn () => $this->members->pluck('id')->all()),
            'members_count' => $this->whenCounted('members'),
            'budget' => $this->budget,
            'formatted_budget' => $this->budget !== null
                ? 'Rp '.number_format((int) $this->budget, 0, ',', '.')
                : null,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
