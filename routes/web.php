<?php

use App\Http\Controllers\ProfileController;
use App\Support\ClaimSection;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
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
    /*
     * Tiga bagian pengajuan yang berdiri sendiri — Reimbursement, Pengadaan
     * Barang, dan Layanan & Server — masing-masing dengan daftar, form, dan
     * halaman detailnya sendiri. Pembagiannya didefinisikan sekali di
     * App\Support\ClaimSection dan dipakai juga oleh menu sidebar.
     */
    foreach (ClaimSection::all() as $section) {
        Route::middleware($section->permissions ? 'permission:'.implode(',', $section->permissions) : [])
            ->group(function () use ($section) {
                $payload = $section->toArray();

                Route::get($section->path, fn () => Inertia::render('Reimbursements/Index', [
                    'section' => $payload,
                ]))->name("{$section->key}.index");

                Route::get("{$section->path}/create", fn (Request $request) => Inertia::render('Reimbursements/Form', [
                    'section' => $payload,
                    'type' => $request->query('type'),
                ]))->name("{$section->key}.create");

                Route::get("{$section->path}/{id}", fn (int $id) => Inertia::render('Reimbursements/Show', [
                    'id' => $id,
                    'section' => $payload,
                ]))->whereNumber('id')->name("{$section->key}.show");

                Route::get("{$section->path}/{id}/edit", fn (int $id) => Inertia::render('Reimbursements/Form', [
                    'id' => $id,
                    'section' => $payload,
                ]))->whereNumber('id')->name("{$section->key}.edit");
            });
    }

    // Persetujuan (Manager/Finance)
    Route::get('/approvals', fn () => Inertia::render('Approvals/Index'))
        ->middleware('role:manager,finance')->name('approvals.index');

    // Pembayaran
    Route::get('/payments', fn () => Inertia::render('Payments/Index'))
        ->middleware('permission:payment.view')->name('payments.index');

    // Anggaran proyek (Project Manager & pemantau anggaran)
    // Nama route sengaja "project-budgets.*" agar tidak bentrok dengan nama
    // route API projects.* dari apiResource.
    Route::get('/projects', fn () => Inertia::render('Projects/Index'))
        ->middleware('permission:project.budget.view')->name('project-budgets.index');
    Route::get('/projects/{id}', fn (int $id) => Inertia::render('Projects/Show', ['id' => $id]))
        ->whereNumber('id')->middleware('permission:project.budget.view')->name('project-budgets.show');

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
