<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Reimbursement;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->category = Category::factory()->create(['max_amount' => null]);
    $this->department = Department::factory()->create();
});

function worker(string $role): User
{
    $user = userWithRole($role);
    $user->update(['department_id' => test()->department->id]);

    return $user->fresh();
}

/** Klaim lembur 2 jam; tarifnya sengaja TIDAK dikirim (ditentukan server). */
function overtimePayload(array $override = []): array
{
    return array_merge([
        'claim_type' => 'overtime',
        'category_id' => test()->category->id,
        'title' => 'Lembur rilis',
        'reason' => 'Deploy di luar jam kerja',
        'details' => [
            'overtime_date' => now()->subDay()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '21:00',
            'hours' => 2,
            'work_description' => 'Deploy dan monitoring',
        ],
    ], $override);
}

// ---- Tangga tarif per jabatan ---------------------------------------------

test('the overtime rate climbs by 10rb from employee upwards', function () {
    $expected = [
        'employee' => 30_000,
        'supervisor' => 40_000,
        'manager' => 50_000,
        'finance' => 60_000,
        'admin' => 70_000,
        'director' => 80_000,
    ];

    foreach ($expected as $role => $rate) {
        expect(worker($role)->overtimeRate())->toBe($rate, "jabatan {$role}");
    }
});

test('positions without overtime rights have no rate', function () {
    expect(worker('intern')->overtimeRate())->toBeNull()
        ->and(worker('auditor')->overtimeRate())->toBeNull();
});

test('the form shows each position its own locked rate', function (string $role, int $rate) {
    Sanctum::actingAs(worker($role));

    $types = $this->getJson('/api/options/claim-types')->assertOk()->json('data');
    $overtime = collect($types)->firstWhere('value', 'overtime');
    $field = collect($overtime['fields'])->firstWhere('key', 'hourly_rate');

    expect($field['readonly'])->toBeTrue()
        ->and($field['fixed_value'])->toBe($rate);
})->with([
    'employee' => ['employee', 30_000],
    'manager' => ['manager', 50_000],
    'director' => ['director', 80_000],
]);

// ---- Nominal diturunkan dari tarif jabatan --------------------------------

test('the claim amount follows the rate of the submitter position', function (string $role, int $expected) {
    Sanctum::actingAs(worker($role));

    $response = $this->postJson('/api/reimbursements', overtimePayload())->assertCreated();

    expect($response->json('data.amount'))->toBe($expected)
        ->and($response->json('data.details.hourly_rate'))->toBe($expected / 2);
})->with([
    'employee 2 jam x 30rb' => ['employee', 60_000],
    'supervisor 2 jam x 40rb' => ['supervisor', 80_000],
    'manager 2 jam x 50rb' => ['manager', 100_000],
    'director 2 jam x 80rb' => ['director', 160_000],
]);

test('a rate sent by the client is ignored in favour of the position rate', function () {
    Sanctum::actingAs(worker('employee'));

    $response = $this->postJson('/api/reimbursements', overtimePayload([
        'details' => [
            'overtime_date' => now()->subDay()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '21:00',
            'hours' => 2,
            'hourly_rate' => 5_000_000,   // percobaan menaikkan tarif sendiri
            'work_description' => 'Deploy',
        ],
    ]))->assertCreated();

    expect($response->json('data.details.hourly_rate'))->toBe(30_000)
        ->and($response->json('data.amount'))->toBe(60_000);
});

test('editing a draft recomputes the amount from the current rate', function () {
    $employee = worker('employee');
    Sanctum::actingAs($employee);

    $id = $this->postJson('/api/reimbursements', overtimePayload())->assertCreated()->json('data.id');
    expect(Reimbursement::find($id)->amount)->toBe(60_000);

    // Naik jabatan menjadi Manager → tarifnya ikut naik saat draft disunting.
    // actingAs menahan instance user, jadi ambil ulang seperti request berikutnya.
    $employee->roles()->sync([Role::where('name', 'manager')->firstOrFail()->id]);
    Sanctum::actingAs($employee->fresh());

    $this->putJson("/api/reimbursements/{$id}", [
        'claim_type' => 'overtime',
        'details' => [
            'overtime_date' => now()->subDay()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '22:00',
            'hours' => 3,
            'work_description' => 'Deploy dan monitoring',
        ],
    ])->assertOk();

    // 3 jam x 50.000 (tarif Manager)
    expect(Reimbursement::find($id)->amount)->toBe(150_000);
});

test('a position without a rate gets a clear explanation', function () {
    // Employee kehilangan tarifnya (mis. belum disetel Admin), tapi masih
    // memegang izin mengajukan lembur.
    Role::where('name', 'employee')->update(['overtime_rate' => null]);

    Sanctum::actingAs(worker('employee'));

    $response = $this->postJson('/api/reimbursements', overtimePayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('details.hourly_rate');

    expect($response->json('errors')['details.hourly_rate'][0])
        ->toContain('belum memiliki tarif upah lembur');
});

test('a user holding two positions is paid at the higher rate', function () {
    $user = worker('employee');
    $user->roles()->attach(Role::where('name', 'manager')->firstOrFail());

    expect($user->fresh()->overtimeRate())->toBe(50_000);
});
