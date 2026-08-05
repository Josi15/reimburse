<?php

use App\Enums\ClaimType;
use App\Models\Reimbursement;
use App\Support\Navigation;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * Pengadaan Barang dan Layanan & Server adalah modul yang berdiri sendiri:
 * menu, alamat, daftar, dan form terpisah dari Reimbursement. Upah lembur juga
 * tidak lagi ditampilkan di form pengajuan.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('procurement menus appear only for roles allowed to request them', function () {
    $hrefs = fn (string $role) => collect(Navigation::for(userWithRole($role)))->pluck('href');

    expect($hrefs('supervisor'))
        ->toContain('/reimbursements')
        ->toContain('/goods')
        ->toContain('/services');

    // Employee & Magang: hanya biaya (dan lembur untuk Employee).
    expect($hrefs('employee'))
        ->toContain('/reimbursements')
        ->not->toContain('/goods')
        ->not->toContain('/services');
});

test('the dedicated procurement pages are guarded by the same permission', function () {
    $this->actingAs(userWithRole('employee'))->get('/goods')->assertForbidden();
    $this->actingAs(userWithRole('employee'))->get('/goods/create')->assertForbidden();

    $this->actingAs(userWithRole('supervisor'))->get('/services')->assertOk();
    $this->actingAs(userWithRole('supervisor'))->get('/services/create')->assertOk();
});

test('each section only lists its own claim types', function () {
    $employee = employeeUser();
    Reimbursement::factory()->create(['user_id' => $employee->id, 'claim_type' => ClaimType::Expense]);
    Reimbursement::factory()->goods()->create(['user_id' => $employee->id]);

    Sanctum::actingAs(userWithRole('supervisor'));

    $types = fn (string $section) => collect(
        $this->getJson("/api/reimbursements?section={$section}")->assertOk()->json('data'),
    )->pluck('claim_type.value')->unique();

    expect($types('reimbursement'))->toContain('expense')->not->toContain('goods')
        ->and($types('goods'))->toContain('goods')->not->toContain('expense');
});

test('the claim type picker is narrowed to the section', function () {
    Sanctum::actingAs(userWithRole('supervisor'));

    $values = fn (string $section) => collect(
        $this->getJson("/api/options/claim-types?section={$section}")->assertOk()->json('data'),
    )->pluck('value');

    expect($values('reimbursement')->all())->toBe(['expense', 'overtime'])
        ->and($values('goods')->all())->toBe(['goods'])
        ->and($values('services')->all())->toBe(['service']);
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
