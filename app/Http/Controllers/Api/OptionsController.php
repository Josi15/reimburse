<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Category;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

/**
 * Opsi dropdown ringan untuk form (Phase 17). Berbeda dari endpoint master
 * data (yang butuh permission kelola), opsi ini hanya berisi item AKTIF dengan
 * field minimal dan tersedia untuk semua user terautentikasi.
 */
class OptionsController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::active()->orderBy('name')->get(['id', 'name', 'max_amount']),
        ]);
    }

    public function departments(): JsonResponse
    {
        return response()->json([
            'data' => Department::active()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function banks(): JsonResponse
    {
        return response()->json([
            'data' => Bank::active()->orderBy('name')->get(['id', 'name', 'code']),
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
