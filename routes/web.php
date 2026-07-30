<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'active'])->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
 * Halaman React (Phase 17). Data diambil client-side dari REST API (/api/*)
 * via Sanctum SPA; route web hanya merender shell Inertia + proteksi RBAC.
 */
Route::middleware(['auth', 'verified', 'active'])->group(function () {
    // Reimbursement
    Route::get('/reimbursements', fn () => Inertia::render('Reimbursements/Index'))
        ->name('reimbursements.index');
    Route::get('/reimbursements/create', fn () => Inertia::render('Reimbursements/Form'))
        ->name('reimbursements.create');
    Route::get('/reimbursements/{id}', fn (int $id) => Inertia::render('Reimbursements/Show', ['id' => $id]))
        ->whereNumber('id')->name('reimbursements.show');
    Route::get('/reimbursements/{id}/edit', fn (int $id) => Inertia::render('Reimbursements/Form', ['id' => $id]))
        ->whereNumber('id')->name('reimbursements.edit');

    // Persetujuan (Manager/Finance)
    Route::get('/approvals', fn () => Inertia::render('Approvals/Index'))
        ->middleware('role:manager,finance')->name('approvals.index');

    // Pembayaran
    Route::get('/payments', fn () => Inertia::render('Payments/Index'))
        ->middleware('permission:payment.view')->name('payments.index');

    // Rekening bank milik sendiri
    Route::get('/bank-accounts', fn () => Inertia::render('BankAccounts/Index'))
        ->middleware('permission:bankaccount.manage')->name('bank-accounts.index');

    // Master data (tab per permission di halaman)
    Route::get('/master', fn () => Inertia::render('Master/Index'))
        ->middleware('permission:user.view')->name('master.index');

    // Laporan
    Route::get('/reports', fn () => Inertia::render('Reports/Index'))
        ->middleware('permission:report.view')->name('reports.index');

    // Activity log (Auditor/Admin)
    Route::get('/audit-logs', fn () => Inertia::render('AuditLogs/Index'))
        ->middleware('permission:audit.view')->name('audit-logs.index');

    // Notifikasi in-app
    Route::get('/notifications', fn () => Inertia::render('Notifications/Index'))
        ->name('notifications.index');
});

require __DIR__.'/auth.php';
