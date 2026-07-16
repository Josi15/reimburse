<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission granular RBAC. Dipetakan ke role via pivot permission_role.
 */
class Permission extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'guard_name',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
