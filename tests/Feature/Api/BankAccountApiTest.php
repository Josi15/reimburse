<?php

use App\Models\Bank;
use App\Models\BankAccount;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->bank = Bank::factory()->create(['is_active' => true]);
});

function accountPayload(array $overrides = []): array
{
    return array_merge([
        'bank_id' => test()->bank->id,
        'account_number' => '1234567890',
        'account_holder_name' => 'Citra Employee',
    ], $overrides);
}

test('first account is automatically primary', function () {
    Sanctum::actingAs(employeeUser());

    $this->postJson('/api/bank-accounts', accountPayload())
        ->assertCreated()->assertJsonPath('data.is_primary', true);
});

test('setting a new primary unsets the previous one', function () {
    $user = employeeUser();
    $first = BankAccount::factory()->primary()->create(['user_id' => $user->id, 'bank_id' => $this->bank->id]);
    $second = BankAccount::factory()->create(['user_id' => $user->id, 'bank_id' => $this->bank->id]);
    Sanctum::actingAs($user);

    $this->postJson("/api/bank-accounts/{$second->id}/primary")->assertOk();

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and($second->fresh()->is_primary)->toBeTrue();
});

test('account number must be numeric 6-30 digits', function () {
    Sanctum::actingAs(employeeUser());

    $this->postJson('/api/bank-accounts', accountPayload(['account_number' => 'abc123']))
        ->assertUnprocessable()->assertJsonValidationErrors(['account_number']);
});

test('a user cannot duplicate the same account on the same bank', function () {
    $user = employeeUser();
    BankAccount::factory()->create([
        'user_id' => $user->id, 'bank_id' => $this->bank->id, 'account_number' => '999888777',
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/bank-accounts', accountPayload(['account_number' => '999888777']))
        ->assertUnprocessable()->assertJsonValidationErrors(['account_number']);
});

test('a user cannot touch another users account', function () {
    $other = employeeUser();
    $account = BankAccount::factory()->create(['user_id' => $other->id, 'bank_id' => $this->bank->id]);
    Sanctum::actingAs(employeeUser());

    $this->getJson("/api/bank-accounts/{$account->id}")->assertForbidden();
    $this->deleteJson("/api/bank-accounts/{$account->id}")->assertForbidden();
});

test('index returns only own accounts, primary first', function () {
    $user = employeeUser();
    BankAccount::factory()->create(['user_id' => $user->id, 'bank_id' => $this->bank->id]);
    BankAccount::factory()->primary()->create(['user_id' => $user->id, 'bank_id' => $this->bank->id]);
    BankAccount::factory()->create(['user_id' => employeeUser()->id, 'bank_id' => $this->bank->id]);
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/bank-accounts')->assertOk()->json('data');

    expect($data)->toHaveCount(2)->and($data[0]['is_primary'])->toBeTrue();
});
