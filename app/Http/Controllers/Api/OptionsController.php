<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClaimType;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Category;
use App\Models\CompanyBankAccount;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\ClaimSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Opsi dropdown ringan untuk form (Phase 17). Berbeda dari endpoint master
 * data (yang butuh permission kelola), opsi ini hanya berisi item AKTIF dengan
 * field minimal dan tersedia untuk semua user terautentikasi.
 */
class OptionsController extends Controller
{
    /** TTL cache opsi (master data jarang berubah). */
    private const CACHE_SECONDS = 60;

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Cache::remember('options.categories', self::CACHE_SECONDS,
                fn () => Category::active()->orderBy('name')->get(['id', 'name', 'max_amount'])),
        ]);
    }

    /**
     * Jenis pengajuan + definisi field tambahannya (form dinamis di frontend).
     * Sumbernya enum ClaimType, jadi tidak perlu cache/DB.
     *
     * Disaring per user: Employee hanya menerima biaya & lembur, sedangkan
     * pengadaan barang/layanan butuh permission reimbursement.procurement.
     *
     * ?section= mempersempit lagi ke jenis milik satu menu saja, supaya form
     * Pengadaan Barang tidak menawarkan jenis dari menu Reimbursement.
     */
    public function claimTypes(Request $request): JsonResponse
    {
        $user = $request->user();
        $section = ClaimSection::tryFind($request->query('section'));

        $types = $section ? $section->claimTypesFor($user) : ClaimType::casesFor($user);

        return response()->json([
            'data' => array_map(fn (ClaimType $type) => $type->toOption($user), $types),
        ]);
    }

    public function departments(): JsonResponse
    {
        return response()->json([
            'data' => Cache::remember('options.departments', self::CACHE_SECONDS,
                fn () => Department::active()->orderBy('name')->get(['id', 'name', 'code'])),
        ]);
    }

    /**
     * Proyek yang boleh dipilih saat mengajukan. Karyawan/magang hanya melihat
     * proyek tempat ia DITUGASKAN (pivot project_user) atau yang dipegangnya —
     * sejalan dengan aturan App\Rules\AssignedProject. Pemegang project.manage
     * melihat semuanya (daftar penuh ini yang di-cache).
     */
    public function projects(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('super_admin') || $user->hasPermission('project.manage')) {
            return response()->json([
                'data' => Cache::remember('options.projects', self::CACHE_SECONDS,
                    fn () => Project::active()->orderBy('name')->get(['id', 'name', 'code'])),
            ]);
        }

        return response()->json([
            'data' => Project::active()->assignedTo($user)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Kandidat anggota proyek: seluruh user aktif (butuh project.manage —
     * dijaga di route). Dipakai form Master Data > Project.
     */
    public function projectMembers(): JsonResponse
    {
        return response()->json([
            'data' => User::query()
                ->where('is_active', true)
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'department_id'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'department' => $u->department?->name,
                ]),
        ]);
    }

    public function banks(): JsonResponse
    {
        return response()->json([
            'data' => Cache::remember('options.banks', self::CACHE_SECONDS,
                fn () => Bank::active()->orderBy('name')->get(['id', 'name', 'code'])),
        ]);
    }

    /** Rekening perusahaan aktif (sumber pembayaran) untuk form Finance. */
    public function companyAccounts(): JsonResponse
    {
        return response()->json([
            'data' => CompanyBankAccount::active()->with('bank:id,code')->orderBy('label')
                ->get(['id', 'bank_id', 'label', 'account_number'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'label' => $a->label,
                    'bank_code' => $a->bank?->code,
                    'masked_number' => $a->masked_number,
                ]),
        ]);
    }

    /**
     * Kandidat pemegang proyek untuk form master project (butuh project.manage
     * — dijaga di route). Berisi user aktif ber-role project_manager.
     */
    public function projectManagers(): JsonResponse
    {
        return response()->json([
            'data' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', 'project_manager'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    /** Untuk form user (butuh user.view — dijaga di route). */
    public function roles(): JsonResponse
    {
        return response()->json([
            'data' => Role::orderBy('name')->get(['id', 'name', 'display_name']),
        ]);
    }

    /** Untuk form role (butuh role.manage — dijaga di route). */
    public function permissions(): JsonResponse
    {
        return response()->json([
            'data' => Permission::orderBy('name')->get(['id', 'name', 'display_name']),
        ]);
    }
}
