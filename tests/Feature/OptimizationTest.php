<?php

use App\Models\Bank;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

// ---- Memoisasi permission (Phase 20) ---------------------------------------

test('repeated permission checks only query the database once', function () {
    $user = userWithRole('finance'); // instance fresh, relasi belum termuat

    DB::enableQueryLog();
    foreach (range(1, 10) as $i) {
        $user->hasPermission('payment.process');
        $user->hasPermission('report.view');
        $user->hasPermission('audit.view');
    }
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Maksimal 2 query (roles + permissions via eager load), sisanya memo.
    expect($count)->toBeLessThanOrEqual(2);
});

// ---- Index kinerja -----------------------------------------------------------

test('the composite user_id+status index exists on reimbursements', function () {
    $exists = DB::select(
        "SELECT 1 FROM pg_indexes WHERE indexname = 'reimbursements_user_status_index'",
    );

    expect($exists)->not->toBeEmpty();
});

// ---- Cache endpoint options ---------------------------------------------------

test('options endpoints serve from cache within the TTL', function () {
    Sanctum::actingAs(employeeUser());
    Bank::factory()->create();

    $first = $this->getJson('/api/options/banks')->assertOk()->json('data');

    // Bank baru belum terlihat karena respons masih dari cache (TTL 60 dtk).
    Bank::factory()->create();
    $second = $this->getJson('/api/options/banks')->assertOk()->json('data');

    expect(count($second))->toBe(count($first));
});
