<?php

use App\Enums\ClaimType;
use App\Support\Navigation;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * Pengadaan barang & layanan punya pintu masuk sendiri di sidebar, dan upah
 * lembur tidak lagi ditampilkan di form pengajuan.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('procurement menus appear only for roles allowed to request them', function () {
    $hrefs = fn (string $role) => collect(Navigation::for(userWithRole($role)))->pluck('href');

    expect($hrefs('supervisor'))
        ->toContain('/reimbursements/goods')
        ->toContain('/reimbursements/services');

    // Employee & Magang: hanya biaya (dan lembur untuk Employee).
    expect($hrefs('employee'))
        ->not->toContain('/reimbursements/goods')
        ->not->toContain('/reimbursements/services');
});

test('the dedicated procurement pages are guarded by the same permission', function () {
    $this->actingAs(userWithRole('employee'))->get('/reimbursements/goods')->assertForbidden();

    $this->actingAs(userWithRole('supervisor'))->get('/reimbursements/services')->assertOk();
});

test('the overtime rate is hidden from the claim form fields', function () {
    Sanctum::actingAs(userWithRole('employee'));

    $overtime = collect($this->getJson('/api/options/claim-types')->assertOk()->json('data'))
        ->firstWhere('value', 'overtime');

    $rate = collect($overtime['fields'])->firstWhere('key', 'hourly_rate');

    // Field tetap ada (server memakainya untuk menghitung nominal), tapi
    // ditandai hidden agar tidak dirender di form.
    expect($rate['hidden'])->toBeTrue()
        ->and($rate['fixed_value'])->toBe(30_000);
});

test('the overtime rate is also kept out of the claim detail rows', function () {
    $rows = ClaimType::Overtime->displayDetails([
        'overtime_date' => '2026-08-01',
        'hours' => 3,
        'hourly_rate' => 30_000,
        'work_description' => 'Rilis produksi',
    ]);

    expect(collect($rows)->pluck('label'))->not->toContain('Upah per Jam');
});
