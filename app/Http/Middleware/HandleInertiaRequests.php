<?php

namespace App\Http\Middleware;

use App\Support\ClaimSection;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user) {
            $user->loadMissing('roles.permissions', 'department');
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'roles' => $user->roles->pluck('name'),
                    // Role utama (untuk label identitas di UI). Diambil dari
                    // display_name agar sumber teksnya tetap master data role.
                    'role' => $user->roles->pluck('name')->first(),
                    'role_label' => $user->roles->pluck('display_name')->first()
                        ?? $user->roles->pluck('name')->first(),
                    'permissions' => $user->roles->flatMap->permissions->pluck('name')->unique()->values(),
                    'reimbursement_limit' => $user->reimbursementLimit(),
                    // Departemen melekat pada pengaju: form pengajuan hanya
                    // menampilkannya, tidak lagi meminta memilih.
                    'department_id' => $user->department_id,
                    'department_name' => $user->department?->name,
                    // Menentukan apakah UI perlu menawarkan filter lintas
                    // departemen (Admin/Supervisor: tidak).
                    'sees_all_departments' => $user->seesAllDepartments(),
                ] : null,
            ],
            // Menu sidebar dinamis sesuai hak akses user.
            'navigation' => $user ? Navigation::for($user) : [],
            // Pembagian modul pengajuan (Reimbursement / Pengadaan Barang /
            // Layanan & Server). Dibagikan apa adanya — halaman detail perlu
            // mengenali bagian sebuah pengajuan dari jenisnya, termasuk saat
            // dibuka lewat tautan notifikasi atau antrean persetujuan.
            'claimSections' => $user ? array_map(
                fn (ClaimSection $section) => $section->toArray(),
                ClaimSection::all(),
            ) : [],
            // Flash message untuk Toast di frontend.
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
