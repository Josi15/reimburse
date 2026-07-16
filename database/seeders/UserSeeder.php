<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seed satu user contoh untuk tiap role. Password semua akun: "password".
 * Employee diberi manager & satu rekening utama agar alur end-to-end
 * (submit → approve → bayar) bisa langsung diuji.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $it = Department::where('code', 'IT')->first();
        $fin = Department::where('code', 'FIN')->first();
        $bca = Bank::where('code', 'BCA')->first();

        // Buat manager lebih dulu agar bisa jadi atasan employee.
        $manager = $this->makeUser('Budi Manager', 'manager@rms.test', 'manager', $it?->id);

        $definitions = [
            ['Super Admin', 'super@rms.test', 'super_admin', $it?->id, null],
            ['Andi Admin', 'admin@rms.test', 'admin', $it?->id, null],
            ['Citra Employee', 'employee@rms.test', 'employee', $it?->id, $manager->id],
            ['Dewi Finance', 'finance@rms.test', 'finance', $fin?->id, null],
            ['Eka Auditor', 'auditor@rms.test', 'auditor', $fin?->id, null],
        ];

        foreach ($definitions as [$name, $email, $roleSlug, $deptId, $managerId]) {
            $user = $this->makeUser($name, $email, $roleSlug, $deptId, $managerId);

            // Beri rekening utama untuk employee (uji alur pembayaran).
            if ($roleSlug === 'employee' && $bca) {
                BankAccount::firstOrCreate(
                    ['user_id' => $user->id, 'bank_id' => $bca->id, 'account_number' => '1234567890'],
                    ['account_holder_name' => $name, 'is_primary' => true, 'is_active' => true],
                );
            }
        }
    }

    private function makeUser(string $name, string $email, string $roleSlug, ?int $deptId, ?int $managerId = null): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'department_id' => $deptId,
                'manager_id' => $managerId,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $role = Role::where('name', $roleSlug)->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        return $user;
    }
}
