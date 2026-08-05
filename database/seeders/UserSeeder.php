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
 * Seed struktur organisasi contoh. Password semua akun: "password".
 *
 * Tiap departemen berdiri sendiri dan lengkap — Manager, Supervisor, Admin,
 * Project Manager, beberapa Karyawan, dan Staf Magang — supaya alur end-to-end
 * (submit → approve Manager/Supervisor → Finance → bayar) bisa diuji di mana
 * pun tanpa meminjam orang dari departemen lain. Ini penting karena Admin,
 * Manager, dan Supervisor TIDAK memegang `data.viewAllDepartments`: cakupan
 * mereka otomatis tersaring ke departemennya sendiri.
 *
 * Rantai atasan: Direktur → Manager → Supervisor → Karyawan/Magang.
 * Seeder idempotent (firstOrCreate per email) sehingga aman diulang.
 */
class UserSeeder extends Seeder
{
    /** Jajaran pusat — lintas departemen (punya data.viewAllDepartments). */
    private const HQ = [
        ['super_admin', 'Super Admin', 'super@fundback.test', 'IT'],
        ['director', 'Bagas Direktur', 'direktur@fundback.test', 'IT'],
    ];

    /**
     * Isi tiap departemen, URUT: manager dulu (agar bisa jadi atasan), lalu
     * supervisor, lalu sisanya. Format tiap baris: [role, nama, email].
     */
    private const ORG = [
        'IT' => [
            ['manager', 'Budi Manager', 'manager@fundback.test'],
            ['supervisor', 'Fajar Supervisor', 'supervisor@fundback.test'],
            ['admin', 'Andi Admin', 'admin@fundback.test'],
            ['project_manager', 'Rina Project Manager', 'pm@fundback.test'],
            ['employee', 'Citra Employee', 'employee@fundback.test'],
            ['employee', 'Hendra Wijaya', 'hendra.it@fundback.test'],
            ['employee', 'Sinta Maharani', 'sinta.it@fundback.test'],
            ['employee', 'Yoga Pratama', 'yoga.it@fundback.test'],
            ['intern', 'Gita Magang', 'magang@fundback.test'],
            ['intern', 'Rafi Alfarizi', 'rafi.it@fundback.test'],
        ],
        'FIN' => [
            ['manager', 'Dimas Prayoga', 'dimas.fin@fundback.test'],
            ['supervisor', 'Nadia Rahmawati', 'nadia.fin@fundback.test'],
            ['admin', 'Bayu Kurniawan', 'bayu.fin@fundback.test'],
            ['project_manager', 'Vera Anggraini', 'vera.fin@fundback.test'],
            ['finance', 'Dewi Finance', 'finance@fundback.test'],
            ['finance', 'Lestari Puspita', 'lestari.fin@fundback.test'],
            ['auditor', 'Eka Auditor', 'auditor@fundback.test'],
            ['employee', 'Galih Saputra', 'galih.fin@fundback.test'],
            ['employee', 'Mira Oktaviani', 'mira.fin@fundback.test'],
            ['intern', 'Aldi Nugraha', 'aldi.fin@fundback.test'],
        ],
        'HR' => [
            ['manager', 'Sari Wulandari', 'sari.hr@fundback.test'],
            ['supervisor', 'Rendi Firmansyah', 'rendi.hr@fundback.test'],
            ['admin', 'Putri Handayani', 'putri.hr@fundback.test'],
            ['project_manager', 'Iqbal Ramadhan', 'iqbal.hr@fundback.test'],
            ['employee', 'Tika Permata', 'tika.hr@fundback.test'],
            ['employee', 'Bagus Setiawan', 'bagus.hr@fundback.test'],
            ['employee', 'Nurul Aini', 'nurul.hr@fundback.test'],
            ['intern', 'Dinda Safitri', 'dinda.hr@fundback.test'],
        ],
        'MKT' => [
            ['manager', 'Reza Mahendra', 'reza.mkt@fundback.test'],
            ['supervisor', 'Alya Kusuma', 'alya.mkt@fundback.test'],
            ['admin', 'Fikri Ardiansyah', 'fikri.mkt@fundback.test'],
            ['project_manager', 'Nabila Zahra', 'nabila.mkt@fundback.test'],
            ['employee', 'Danu Prakoso', 'danu.mkt@fundback.test'],
            ['employee', 'Kirana Dewi', 'kirana.mkt@fundback.test'],
            ['employee', 'Wahyu Utomo', 'wahyu.mkt@fundback.test'],
            ['intern', 'Salsa Aprilia', 'salsa.mkt@fundback.test'],
        ],
        'OPS' => [
            ['manager', 'Agus Riyanto', 'agus.ops@fundback.test'],
            ['supervisor', 'Lina Marlina', 'lina.ops@fundback.test'],
            ['admin', 'Teguh Santoso', 'teguh.ops@fundback.test'],
            ['project_manager', 'Yuni Astuti', 'yuni.ops@fundback.test'],
            ['employee', 'Bima Saputra', 'bima.ops@fundback.test'],
            ['employee', 'Rio Firdaus', 'rio.ops@fundback.test'],
            ['employee', 'Ratna Sari', 'ratna.ops@fundback.test'],
            ['employee', 'Joko Susilo', 'joko.ops@fundback.test'],
            ['intern', 'Elang Pratama', 'elang.ops@fundback.test'],
        ],
    ];

    /** Bank rekening karyawan, dipakai bergiliran agar datanya bervariasi. */
    private const BANKS = ['BCA', 'MANDIRI', 'BRI', 'BNI', 'BSI', 'CIMB', 'PERMATA', 'BTN'];

    /** Auditor read-only — tak pernah dibayar, jadi tak perlu rekening. */
    private const NO_BANK_ACCOUNT = ['auditor'];

    /** Penomoran berurutan untuk telepon & nomor rekening contoh. */
    private int $seq = 0;

    /** @var array<string, int> kode bank → id */
    private array $bankIds = [];

    public function run(): void
    {
        $departments = Department::pluck('id', 'code');
        $this->bankIds = Bank::whereIn('code', self::BANKS)->pluck('id', 'code')->all();

        // Pusat dibuat lebih dulu: manager tiap departemen melapor ke Direktur.
        $director = null;
        foreach (self::HQ as [$roleSlug, $name, $email, $deptCode]) {
            $user = $this->makeUser($name, $email, $roleSlug, $departments[$deptCode] ?? null);

            if ($roleSlug === 'director') {
                $director = $user;
            }
        }

        foreach (self::ORG as $deptCode => $people) {
            $deptId = $departments[$deptCode] ?? null;
            $manager = null;
            $supervisor = null;

            foreach ($people as [$roleSlug, $name, $email]) {
                // Atasan langsung mengikuti jenjang; fallback ke Manager bila
                // departemen belum punya Supervisor.
                $atasan = match ($roleSlug) {
                    'manager' => $director,
                    'employee', 'intern' => $supervisor ?? $manager,
                    default => $manager,
                };

                $user = $this->makeUser($name, $email, $roleSlug, $deptId, $atasan?->id);

                if ($roleSlug === 'manager') {
                    $manager = $user;
                } elseif ($roleSlug === 'supervisor') {
                    $supervisor = $user;
                }
            }
        }
    }

    private function makeUser(string $name, string $email, string $roleSlug, ?int $deptId, ?int $managerId = null): User
    {
        $this->seq++;

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'department_id' => $deptId,
                'manager_id' => $managerId,
                'phone' => sprintf('08%09d', 120_000_000 + $this->seq),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // firstOrCreate tak menyentuh baris yang sudah ada, jadi posisi akun
        // lama diselaraskan ulang di sini agar bagan organisasi di seeder ini
        // selalu jadi acuan saat seed diulang.
        $user->forceFill(['department_id' => $deptId, 'manager_id' => $managerId])->save();

        $role = Role::where('name', $roleSlug)->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        if (! in_array($roleSlug, self::NO_BANK_ACCOUNT, true)) {
            $this->givePrimaryAccount($user);
        }

        return $user;
    }

    /**
     * Rekening utama untuk penerima pembayaran. Dilewati bila user sudah punya
     * rekening utama — indeks unik parsial hanya mengizinkan satu per user.
     */
    private function givePrimaryAccount(User $user): void
    {
        if (BankAccount::where('user_id', $user->id)->where('is_primary', true)->exists()) {
            return;
        }

        $bankId = $this->bankIds[self::BANKS[$this->seq % count(self::BANKS)]] ?? null;
        if (! $bankId) {
            return;
        }

        BankAccount::create([
            'user_id' => $user->id,
            'bank_id' => $bankId,
            'account_number' => sprintf('%010d', 3_210_000_000 + $this->seq),
            'account_holder_name' => $user->name,
            'is_primary' => true,
            'is_active' => true,
        ]);
    }
}
