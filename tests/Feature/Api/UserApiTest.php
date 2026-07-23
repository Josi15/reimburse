<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('employee cannot access user management', function () {
    Sanctum::actingAs(userWithRole('employee'));
    $this->getJson('/api/users')->assertForbidden();
});

test('admin can create a user with roles and hashed password', function () {
    $roleId = Role::where('name', 'employee')->value('id');
    Sanctum::actingAs(userWithRole('admin'));

    $this->postJson('/api/users', [
        'name' => 'New Staff',
        'email' => 'staff@example.com',
        'password' => 'Str0ng#Pass1',
        'password_confirmation' => 'Str0ng#Pass1',
        'role_ids' => [$roleId],
    ])->assertCreated()->assertJsonPath('data.roles', ['employee']);

    $user = User::where('email', 'staff@example.com')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('Str0ng#Pass1', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('Str0ng#Pass1');
});

test('store validates weak password, duplicate email, and missing roles', function () {
    User::factory()->create(['email' => 'dupe@example.com']);
    Sanctum::actingAs(userWithRole('admin'));

    $this->postJson('/api/users', [
        'name' => 'X',
        'email' => 'dupe@example.com',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'role_ids']);
});

test('index can filter by role', function () {
    Sanctum::actingAs(userWithRole('admin'));
    userWithRole('finance');

    $data = $this->getJson('/api/users?role=finance')->assertOk()->json('data');

    expect(collect($data)->every(fn ($u) => in_array('finance', $u['roles'])))->toBeTrue();
});

test('admin cannot delete their own account', function () {
    $admin = userWithRole('admin');
    Sanctum::actingAs($admin);

    $this->deleteJson("/api/users/{$admin->id}")->assertStatus(422);
    $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
});

test('admin can soft delete another user', function () {
    $victim = User::factory()->create();
    Sanctum::actingAs(userWithRole('admin'));

    $this->deleteJson("/api/users/{$victim->id}")->assertNoContent();
    $this->assertSoftDeleted('users', ['id' => $victim->id]);
});

test('admin cannot assign the super_admin role', function () {
    $superId = Role::where('name', 'super_admin')->value('id');
    Sanctum::actingAs(userWithRole('admin'));

    $this->postJson('/api/users', [
        'name' => 'Escalate',
        'email' => 'escalate@example.com',
        'password' => 'Str0ng#Pass1',
        'password_confirmation' => 'Str0ng#Pass1',
        'role_ids' => [$superId],
    ])->assertUnprocessable()->assertJsonValidationErrors(['role_ids']);

    $this->assertDatabaseMissing('users', ['email' => 'escalate@example.com']);
});

test('super admin can assign the super_admin role', function () {
    $superId = Role::where('name', 'super_admin')->value('id');
    Sanctum::actingAs(userWithRole('super_admin'));

    $this->postJson('/api/users', [
        'name' => 'Another Super',
        'email' => 'super2@example.com',
        'password' => 'Str0ng#Pass1',
        'password_confirmation' => 'Str0ng#Pass1',
        'role_ids' => [$superId],
    ])->assertCreated();
});

test('admin cannot modify or delete a super admin account', function () {
    $super = userWithRole('super_admin');
    Sanctum::actingAs(userWithRole('admin'));

    $this->putJson("/api/users/{$super->id}", ['name' => 'Hijacked'])->assertStatus(422);
    $this->deleteJson("/api/users/{$super->id}")->assertStatus(422);
    $this->assertDatabaseHas('users', ['id' => $super->id, 'name' => $super->name, 'deleted_at' => null]);
});

test('admin cannot deactivate their own account', function () {
    $admin = userWithRole('admin');
    Sanctum::actingAs($admin);

    $this->putJson("/api/users/{$admin->id}", ['is_active' => false])
        ->assertStatus(422)->assertJsonValidationErrors(['is_active']);

    $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
});
