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
 * Placeholder modul — route sudah dilindungi RBAC sejak Phase 7. Halaman/
 * controller sesungguhnya menggantikan placeholder ini pada fase berikutnya
 * (Phase 8-16). Contoh proteksi role vs permission ada di sini.
 */
Route::middleware(['auth', 'verified', 'active'])->group(function () {
    $placeholder = fn (string $title, string $phase) => fn () => Inertia::render('Placeholder', [
        'title' => $title,
        'phase' => $phase,
    ]);

    Route::get('/reimbursements', $placeholder('Reimbursement', 'Phase 9'))
        ->name('reimbursements.index');

    Route::get('/approvals', $placeholder('Persetujuan', 'Phase 10'))
        ->middleware('role:manager,finance')->name('approvals.index');

    Route::get('/payments', $placeholder('Pembayaran', 'Phase 11'))
        ->middleware('permission:payment.view')->name('payments.index');

    Route::get('/bank-accounts', $placeholder('Rekening Bank', 'Phase 11'))
        ->middleware('permission:bankaccount.manage')->name('bank-accounts.index');

    Route::get('/master', $placeholder('Master Data', 'Phase 8'))
        ->middleware('permission:user.view')->name('master.index');

    Route::get('/reports', $placeholder('Laporan', 'Phase 14'))
        ->middleware('permission:report.view')->name('reports.index');

    Route::get('/audit-logs', $placeholder('Audit Log', 'Phase 15'))
        ->middleware('permission:audit.view')->name('audit-logs.index');
});

require __DIR__.'/auth.php';
