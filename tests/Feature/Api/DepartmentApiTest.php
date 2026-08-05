<?php

use App\Models\Department;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

// ---- Authentication & authorization --------------------------------------

test('guests are rejected', function () {
    $this->getJson('/api/departments')->assertUnauthorized();
});

test('users without permission are forbidden', function () {
    Sanctum::actingAs(userWithRole('employee'));
    $this->getJson('/api/departments')->assertForbidden();
});

// ---- Index: pagination, search, filter, sort ------------------------------

test('admin can list departments with pagination envelope', function () {
    Department::factory()->count(3)->create();
    Sanctum::actingAs(userWithRole('admin'));

    $this->getJson('/api/departments')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'code', 'is_active', 'users_count']],
            'meta' => ['current_page', 'per_page', 'total'],
            'links',
        ]);
});

test('index supports search', function () {
    Department::factory()->create(['name' => 'Engineering', 'code' => 'ENG']);
    Department::factory()->create(['name' => 'Marketing', 'code' => 'MKT']);
    Sanctum::actingAs(userWithRole('admin'));

    $res = $this->getJson('/api/departments?q=engine')->assertOk()->json('data');

    expect($res)->toHaveCount(1)
        ->and($res[0]['code'])->toBe('ENG');
});

test('index supports is_active filter and sorting', function () {
    Department::factory()->create(['name' => 'Alpha', 'code' => 'A1', 'is_active' => true]);
    Department::factory()->create(['name' => 'Bravo', 'code' => 'B1', 'is_active' => false]);
    Sanctum::actingAs(userWithRole('admin'));

    // Departemen milik admin (dibuat factory user) ikut terdaftar, jadi yang
    // diperiksa adalah keanggotaan kodenya, bukan jumlah baris.
    $active = collect($this->getJson('/api/departments?is_active=true')->assertOk()->json('data'))
        ->pluck('code');
    expect($active)->toContain('A1')->not->toContain('B1');

    $sorted = collect($this->getJson('/api/departments?sort=name&direction=desc')->assertOk()->json('data'))
        ->pluck('name');
    expect($sorted->search('Bravo'))->toBeLessThan($sorted->search('Alpha'));
});

// ---- Store + validation ---------------------------------------------------

test('admin can create a department', function () {
    Sanctum::actingAs(userWithRole('admin'));

    $this->postJson('/api/departments', [
        'name' => 'Procurement',
        'code' => 'PROC',
    ])->assertCreated()->assertJsonPath('data.code', 'PROC');

    $this->assertDatabaseHas('departments', ['code' => 'PROC']);
});

test('store validates required fields and unique code', function () {
    Department::factory()->create(['code' => 'DUP']);
    Sanctum::actingAs(userWithRole('admin'));

    $this->postJson('/api/departments', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'code']);

    $this->postJson('/api/departments', ['name' => 'X', 'code' => 'DUP'])
        ->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

// ---- Show / Update --------------------------------------------------------

test('admin can update a department', function () {
    $dept = Department::factory()->create(['name' => 'Old']);
    Sanctum::actingAs(userWithRole('admin'));

    $this->putJson("/api/departments/{$dept->id}", ['name' => 'New Name'])
        ->assertOk()->assertJsonPath('data.name', 'New Name');
});

// ---- Soft delete + restore ------------------------------------------------

test('destroy soft-deletes and restore brings it back', function () {
    $dept = Department::factory()->create();
    Sanctum::actingAs(userWithRole('admin'));

    $this->deleteJson("/api/departments/{$dept->id}")->assertNoContent();
    $this->assertSoftDeleted('departments', ['id' => $dept->id]);

    // Tidak muncul di list default, muncul di only_trashed.
    $codes = collect($this->getJson('/api/departments')->json('data'))->pluck('code');
    expect($codes)->not->toContain($dept->code);

    $trashed = collect($this->getJson('/api/departments?only_trashed=true')->json('data'))->pluck('code');
    expect($trashed)->toContain($dept->code);

    $this->postJson("/api/departments/{$dept->id}/restore")->assertOk();
    $this->assertDatabaseHas('departments', ['id' => $dept->id, 'deleted_at' => null]);
});
