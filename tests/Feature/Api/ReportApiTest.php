<?php

use App\Models\Reimbursement;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

// ---- Report list + summary + filters -------------------------------------

test('report requires report.view permission', function () {
    Sanctum::actingAs(userWithRole('employee'));
    $this->getJson('/api/reports/reimbursements')->assertForbidden();
});

test('report returns filtered rows with a summary', function () {
    Reimbursement::factory()->paid()->count(2)->create(['amount' => 500_000]);
    Reimbursement::factory()->submitted()->create(['amount' => 300_000]);
    Sanctum::actingAs(userWithRole('manager'));

    $res = $this->getJson('/api/reports/reimbursements')->assertOk();

    expect($res->json('summary.count'))->toBe(3)
        ->and($res->json('summary.total_amount'))->toBe(1_300_000);
});

test('report can filter by status', function () {
    Reimbursement::factory()->paid()->count(2)->create();
    Reimbursement::factory()->submitted()->create();
    Sanctum::actingAs(userWithRole('manager'));

    $res = $this->getJson('/api/reports/reimbursements?status=paid')->assertOk();

    expect($res->json('summary.count'))->toBe(2);
});

// ---- Export ---------------------------------------------------------------

test('report exports to CSV', function () {
    Reimbursement::factory()->count(2)->create();
    Sanctum::actingAs(userWithRole('manager'));

    $this->get('/api/reports/reimbursements/export?format=csv')
        ->assertOk()->assertDownload('laporan-reimbursement.csv');
});

test('report exports to XLSX', function () {
    Reimbursement::factory()->count(2)->create();
    Sanctum::actingAs(userWithRole('manager'));

    $this->get('/api/reports/reimbursements/export?format=xlsx')
        ->assertOk()->assertDownload('laporan-reimbursement.xlsx');
});

test('report exports to PDF', function () {
    Reimbursement::factory()->count(2)->create();
    Sanctum::actingAs(userWithRole('manager'));

    $res = $this->get('/api/reports/reimbursements/export?format=pdf')->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
});

test('an unknown export format is rejected', function () {
    Sanctum::actingAs(userWithRole('manager'));
    $this->get('/api/reports/reimbursements/export?format=xml')->assertStatus(422);
});

// ---- Global search --------------------------------------------------------

test('global search finds own claims for an employee', function () {
    $me = employeeUser();
    Reimbursement::factory()->create(['user_id' => $me->id, 'title' => 'Perjalanan Bandung']);
    Reimbursement::factory()->create(['title' => 'Perjalanan Medan']); // milik orang lain
    Sanctum::actingAs($me);

    $data = $this->getJson('/api/search?q=Perjalanan')->assertOk()->json('data');

    expect($data['reimbursements'])->toHaveCount(1)
        ->and($data['users'])->toBeEmpty(); // employee tidak punya user.view
});

test('admin search also returns matching users', function () {
    Sanctum::actingAs(userWithRole('admin'));
    userWithRole('finance'); // ada user finance untuk dicari

    $data = $this->getJson('/api/search?q=@example')->assertOk()->json('data');

    expect(count($data['users']))->toBeGreaterThan(0);
});
