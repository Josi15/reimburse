<?php

use App\Models\Bank;
use App\Models\BankAccount;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('employee cannot manage banks', function () {
    Sanctum::actingAs(userWithRole('employee'));
    $this->getJson('/api/banks')->assertForbidden();
});

test('admin can create and list banks', function () {
    Sanctum::actingAs(userWithRole('admin'));

    $this->postJson('/api/banks', ['name' => 'Bank Jago', 'code' => 'JAGO'])
        ->assertCreated()->assertJsonPath('data.code', 'JAGO');

    $this->getJson('/api/banks?q=jago')->assertOk()
        ->assertJsonPath('data.0.code', 'JAGO');
});

test('a bank in use cannot be deleted', function () {
    $bank = Bank::factory()->create();
    BankAccount::factory()->create(['bank_id' => $bank->id]);
    Sanctum::actingAs(userWithRole('admin'));

    $this->deleteJson("/api/banks/{$bank->id}")->assertStatus(422);
});
