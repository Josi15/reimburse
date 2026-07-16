<?php

use App\Models\Category;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('employee cannot manage categories', function () {
    Sanctum::actingAs(userWithRole('employee'));
    $this->getJson('/api/categories')->assertForbidden();
});

test('admin can create a category with plafon and see formatted amount', function () {
    Sanctum::actingAs(userWithRole('admin'));

    $this->postJson('/api/categories', [
        'name' => 'Transport',
        'code' => 'TRANS',
        'max_amount' => 1_000_000,
    ])->assertCreated()
        ->assertJsonPath('data.max_amount', 1000000)
        ->assertJsonPath('data.formatted_max_amount', 'Rp 1.000.000');
});

test('max_amount must be positive', function () {
    Sanctum::actingAs(userWithRole('admin'));

    $this->postJson('/api/categories', ['name' => 'X', 'code' => 'X1', 'max_amount' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors(['max_amount']);
});

test('admin can list and search categories', function () {
    Category::factory()->create(['name' => 'Medical', 'code' => 'MED']);
    Sanctum::actingAs(userWithRole('admin'));

    $data = $this->getJson('/api/categories?q=medic')->assertOk()->json('data');
    expect($data)->toHaveCount(1)->and($data[0]['code'])->toBe('MED');
});
