<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logika bisnis manajemen user (master data). Menjamin penulisan user + role
 * bersifat atomik, dan menegakkan pagar hak istimewa: hanya Super Admin yang
 * boleh menyentuh akun Super Admin atau memberikan role Super Admin.
 */
class UserService
{
    public function create(User $actor, array $data): User
    {
        $this->assertRolesAllowed($actor, $data['role_ids'] ?? []);

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],          // di-hash otomatis oleh cast
                'phone' => $data['phone'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'email_verified_at' => now(),
            ]);

            $user->roles()->sync($data['role_ids']);

            return $user;
        });
    }

    public function update(User $actor, User $user, array $data): User
    {
        $this->assertCanManage($actor, $user);

        if (array_key_exists('role_ids', $data)) {
            $this->assertRolesAllowed($actor, $data['role_ids']);
        }

        // Cegah user menonaktifkan akunnya sendiri (mengunci diri sendiri).
        if ($actor->id === $user->id && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ]);
        }

        return DB::transaction(function () use ($user, $data) {
            $user->fill(collect($data)->only([
                'name', 'email', 'phone', 'department_id', 'manager_id', 'is_active',
            ])->toArray());

            if (! empty($data['password'])) {
                $user->password = $data['password'];      // di-hash otomatis oleh cast
            }

            $user->save();

            if (isset($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            return $user;
        });
    }

    public function delete(User $actor, User $user): void
    {
        if ($actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ]);
        }

        $this->assertCanManage($actor, $user);

        $user->delete();
    }

    /** Hanya Super Admin yang boleh mengubah/menghapus akun Super Admin lain. */
    private function assertCanManage(User $actor, User $target): void
    {
        if ($target->hasRole('super_admin') && ! $actor->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'user' => 'Hanya Super Admin yang dapat mengelola akun Super Admin.',
            ]);
        }
    }

    /** Hanya Super Admin yang boleh memberikan role Super Admin. */
    private function assertRolesAllowed(User $actor, array $roleIds): void
    {
        if ($actor->hasRole('super_admin') || empty($roleIds)) {
            return;
        }

        $superAdminId = Role::where('name', 'super_admin')->value('id');

        if ($superAdminId && in_array($superAdminId, array_map('intval', $roleIds), true)) {
            throw ValidationException::withMessages([
                'role_ids' => 'Hanya Super Admin yang dapat memberikan role Super Admin.',
            ]);
        }
    }
}
