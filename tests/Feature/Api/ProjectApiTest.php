<?php

use App\Models\Category;
use App\Models\Project;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('employee cannot manage projects', function () {
    Sanctum::actingAs(userWithRole('employee'));
    $this->getJson('/api/projects')->assertForbidden();
});

test('admin can create a project with budget and formatted value', function () {
    Sanctum::actingAs(userWithRole('admin'));

    $this->postJson('/api/projects', [
        'code' => 'PRJ-2026-999',
        'name' => 'Migrasi Data Center',
        'budget' => 120_000_000,
    ])->assertCreated()
        ->assertJsonPath('data.budget', 120000000)
        ->assertJsonPath('data.formatted_budget', 'Rp 120.000.000');
});

test('project code must be unique and end_date not before start_date', function () {
    Project::factory()->create(['code' => 'PRJ-DUP']);
    Sanctum::actingAs(userWithRole('admin'));

    $this->postJson('/api/projects', ['code' => 'PRJ-DUP', 'name' => 'X'])
        ->assertUnprocessable()->assertJsonValidationErrors(['code']);

    $this->postJson('/api/projects', [
        'code' => 'PRJ-NEW', 'name' => 'Y',
        'start_date' => '2026-06-01', 'end_date' => '2026-05-01',
    ])->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
});

test('the options endpoint only lists active projects the user is assigned to', function () {
    $employee = employeeUser();
    $assigned = Project::factory()->create(['name' => 'Alpha', 'is_active' => true]);
    $assigned->members()->attach($employee);
    Project::factory()->inactive()->create(['name' => 'Beta'])->members()->attach($employee);
    Project::factory()->create(['name' => 'Gamma', 'is_active' => true]);   // tanpa penugasan

    Sanctum::actingAs($employee);

    $names = collect($this->getJson('/api/options/projects')->assertOk()->json('data'))->pluck('name');
    expect($names)->toContain('Alpha')->not->toContain('Beta')->not->toContain('Gamma');
});

test('an admin managing projects still sees every active project', function () {
    Project::factory()->create(['name' => 'Alpha', 'is_active' => true]);
    Sanctum::actingAs(userWithRole('admin'));

    $names = collect($this->getJson('/api/options/projects')->assertOk()->json('data'))->pluck('name');
    expect($names)->toContain('Alpha');
});

test('members can be assigned to a project and are returned with it', function () {
    $employee = employeeUser();
    $intern = userWithRole('intern');
    Sanctum::actingAs(userWithRole('admin'));

    $id = $this->postJson('/api/projects', [
        'code' => 'PRJ-TEAM',
        'name' => 'Tim Gabungan',
        'member_ids' => [$employee->id, $intern->id],
    ])->assertCreated()->json('data.id');

    $this->assertDatabaseHas('project_user', ['project_id' => $id, 'user_id' => $employee->id]);
    $this->assertDatabaseHas('project_user', ['project_id' => $id, 'user_id' => $intern->id]);

    // Kirim ulang dengan satu anggota = daftar diganti, bukan ditambah.
    $this->putJson("/api/projects/{$id}", ['member_ids' => [$intern->id]])
        ->assertOk()->assertJsonPath('data.members_count', 1);

    $this->assertDatabaseMissing('project_user', ['project_id' => $id, 'user_id' => $employee->id]);
});

test('a claim cannot be charged to a project the user is not assigned to', function () {
    $employee = employeeUser();
    $category = Category::factory()->create(['max_amount' => 5_000_000]);
    $project = Project::factory()->create();
    Sanctum::actingAs($employee);

    $this->postJson('/api/reimbursements', [
        'category_id' => $category->id,
        'project_id' => $project->id,
        'title' => 'Tiket proyek',
        'reason' => 'Perjalanan proyek',
        'amount' => 800_000,
    ])->assertUnprocessable()->assertJsonValidationErrors(['project_id']);
});

test('a reimbursement can be linked to a project the user is assigned to', function () {
    $employee = employeeUser();
    $category = Category::factory()->create(['max_amount' => 5_000_000]);
    $project = Project::factory()->create();
    $project->members()->attach($employee);
    Sanctum::actingAs($employee);

    $this->postJson('/api/reimbursements', [
        'category_id' => $category->id,
        'project_id' => $project->id,
        'title' => 'Tiket proyek',
        'reason' => 'Perjalanan proyek',
        'amount' => 800_000,
    ])->assertCreated()->assertJsonPath('data.project_id', $project->id);
});

test('a reimbursement rejects an inactive project', function () {
    $employee = employeeUser();
    $category = Category::factory()->create(['max_amount' => 5_000_000]);
    $project = Project::factory()->inactive()->create();
    $project->members()->attach($employee);
    Sanctum::actingAs($employee);

    $this->postJson('/api/reimbursements', [
        'category_id' => $category->id,
        'project_id' => $project->id,
        'title' => 'X',
        'reason' => 'Y',
        'amount' => 500_000,
    ])->assertUnprocessable()->assertJsonValidationErrors(['project_id']);
});
