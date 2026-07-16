<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
 * Master Data REST API (Phase 8). Semua endpoint butuh autentikasi Sanctum
 * dan permission spesifik (Super Admin di-bypass Gate::before).
 */
Route::middleware('auth:sanctum')->group(function () {

    // ---- Department (permission: department.manage) ----------------------
    Route::middleware('permission:department.manage')->group(function () {
        Route::apiResource('departments', DepartmentController::class);
        Route::post('departments/{id}/restore', [DepartmentController::class, 'restore']);
    });

    // ---- Category (permission: category.manage) --------------------------
    Route::middleware('permission:category.manage')->group(function () {
        Route::apiResource('categories', CategoryController::class);
        Route::post('categories/{id}/restore', [CategoryController::class, 'restore']);
    });

    // ---- User (permission granular per aksi) -----------------------------
    Route::get('users', [UserController::class, 'index'])->middleware('permission:user.view');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:user.view');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:user.delete');
    Route::post('users/{id}/restore', [UserController::class, 'restore'])->middleware('permission:user.create');

    // ---- Role (permission: role.manage) ----------------------------------
    Route::middleware('permission:role.manage')->group(function () {
        Route::apiResource('roles', RoleController::class);
    });
});
