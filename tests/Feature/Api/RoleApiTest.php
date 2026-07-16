<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('only role.manage holders can manage roles', function () {
    Sanctum::actingAs(userWithRole('admin')); // admin tidak punya role.manage
    $this->getJson('/api/roles')->assertForbidden();
});

test('super admin can create a role with permissions', function () {
    $permIds = Permission::whereIn('name', ['report.view', 'report.export'])->pluck('id')->all();
    Sanctum::actingAs(userWithRole('super_admin'));

    $this->postJson('/api/roles', [
        'name' => 'viewer',
        'display_name' => 'Viewer',
        'permission_ids' => $permIds,
    ])->assertCreated()->assertJsonPath('data.name', 'viewer');

    expect(Role::where('name', 'viewer')->first()->permissions)->toHaveCount(2);
});

test('slug must be lowercase snake', function () {
    Sanctum::actingAs(userWithRole('super_admin'));

    $this->postJson('/api/roles', ['name' => 'Bad Name', 'display_name' => 'Bad'])
        ->assertUnprocessable()->assertJsonValidationErrors(['name']);
});

test('core roles cannot be deleted', function () {
    $finance = Role::where('name', 'finance')->first();
    Sanctum::actingAs(userWithRole('super_admin'));

    $this->deleteJson("/api/roles/{$finance->id}")->assertStatus(422);
    $this->assertDatabaseHas('roles', ['id' => $finance->id]);
});

test('a role still assigned to users cannot be deleted', function () {
    $role = Role::create(['name' => 'temp', 'display_name' => 'Temp', 'guard_name' => 'web']);
    User::factory()->create()->roles()->attach($role);
    Sanctum::actingAs(userWithRole('super_admin'));

    $this->deleteJson("/api/roles/{$role->id}")->assertStatus(422);
});

test('an unused custom role can be deleted', function () {
    $role = Role::create(['name' => 'temp2', 'display_name' => 'Temp2', 'guard_name' => 'web']);
    Sanctum::actingAs(userWithRole('super_admin'));

    $this->deleteJson("/api/roles/{$role->id}")->assertNoContent();
    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});
