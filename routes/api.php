<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReimbursementController;
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

    // ---- Reimbursement (Phase 9) — otorisasi via ReimbursementPolicy -----
    Route::post('reimbursements/{reimbursement}/submit', [ReimbursementController::class, 'submit']);
    Route::delete('reimbursements/{reimbursement}/attachments/{attachment}', [ReimbursementController::class, 'destroyAttachment']);

    // ---- Approval (Phase 10) — Manager → Finance -------------------------
    Route::get('reimbursements/{reimbursement}/approvals', [ApprovalController::class, 'history']);
    Route::post('reimbursements/{reimbursement}/approve', [ApprovalController::class, 'approve']);
    Route::post('reimbursements/{reimbursement}/reject', [ApprovalController::class, 'reject']);
    Route::post('reimbursements/{reimbursement}/revision', [ApprovalController::class, 'revision']);

    Route::apiResource('reimbursements', ReimbursementController::class);

    // ---- Master Bank (permission: bank.manage) ---------------------------
    Route::middleware('permission:bank.manage')->group(function () {
        Route::apiResource('banks', BankController::class);
        Route::post('banks/{id}/restore', [BankController::class, 'restore']);
    });

    // ---- Rekening karyawan (permission: bankaccount.manage; scoped ke diri)
    Route::middleware('permission:bankaccount.manage')->group(function () {
        Route::post('bank-accounts/{bank_account}/primary', [BankAccountController::class, 'setPrimary']);
        Route::apiResource('bank-accounts', BankAccountController::class);
    });

    // ---- Payment (Phase 11) ----------------------------------------------
    Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payment.view');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->middleware('permission:payment.view');
    // Proses pembayaran diotorisasi PaymentPolicy (permission payment.process + status).
    Route::post('reimbursements/{reimbursement}/pay', [PaymentController::class, 'store']);
});
