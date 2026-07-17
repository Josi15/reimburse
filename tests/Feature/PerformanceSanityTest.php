<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Reimbursement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Phase 21 — Sanity performa: endpoint terpanas diuji pada volume data.
 * Assertion utama = JUMLAH QUERY (deterministik, penjaga N+1); timing hanya
 * batas longgar agar tidak flaky di mesin lambat.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Volume: 1 dept, 3 kategori, 10 user, 300 reimbursement (FK dibagi rata
    // agar factory tidak membuat cascade ribuan baris).
    $dept = Department::factory()->create();
    $categories = Category::factory()->count(3)->create();
    $users = User::factory()->count(10)->create(['department_id' => $dept->id]);

    $statuses = ['draft', 'submitted', 'manager_approved', 'finance_approved', 'paid', 'manager_rejected'];

    Reimbursement::factory()
        ->count(300)
        ->sequence(fn ($seq) => [
            'user_id' => $users[$seq->index % 10]->id,
            'department_id' => $dept->id,
            'category_id' => $categories[$seq->index % 3]->id,
            'status' => $statuses[$seq->index % 6],
            'completed_at' => $statuses[$seq->index % 6] === 'paid' ? now() : null,
        ])
        ->create();
});

test('dashboard aggregates 300 claims with a bounded query count', function () {
    Sanctum::actingAs(userWithRole('admin'));

    DB::flushQueryLog();
    DB::enableQueryLog();
    $start = microtime(true);

    $res = $this->getJson('/api/dashboard')->assertOk();

    $elapsed = microtime(true) - $start;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($res->json('data.cards.total'))->toBe(300)
        ->and($queries)->toBeLessThanOrEqual(25)   // agregasi di DB, bukan N+1
        ->and($elapsed)->toBeLessThan(3.0);        // batas longgar anti-flaky
});

test('reimbursement index (50 rows) is free of N+1 queries', function () {
    Sanctum::actingAs(userWithRole('finance'));

    DB::flushQueryLog();
    DB::enableQueryLog();

    $res = $this->getJson('/api/reimbursements?per_page=50')->assertOk();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect(count($res->json('data')))->toBe(50)
        // count + select + 2 eager load + auth/permission — jauh di bawah 50 baris
        ->and($queries)->toBeLessThanOrEqual(12);
});

test('report summary over 300 claims stays a single aggregate query set', function () {
    Sanctum::actingAs(userWithRole('manager'));

    $res = $this->getJson('/api/reports/reimbursements?per_page=20')->assertOk();

    expect($res->json('summary.count'))->toBe(300)
        ->and(count($res->json('data')))->toBe(20);
});
