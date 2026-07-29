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

test('active projects are exposed via the options endpoint', function () {
    Project::factory()->create(['name' => 'Alpha', 'is_active' => true]);
    Project::factory()->inactive()->create(['name' => 'Beta']);
    Sanctum::actingAs(userWithRole('employee'));

    $data = $this->getJson('/api/options/projects')->assertOk()->json('data');
    expect(collect($data)->pluck('name'))->toContain('Alpha')->not->toContain('Beta');
});

test('a reimbursement can be linked to a project', function () {
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
    ])->assertCreated()->assertJsonPath('data.project_id', $project->id);
});

test('a reimbursement rejects an inactive project', function () {
    $employee = employeeUser();
    $category = Category::factory()->create(['max_amount' => 5_000_000]);
    $project = Project::factory()->inactive()->create();
    Sanctum::actingAs($employee);

    $this->postJson('/api/reimbursements', [
        'category_id' => $category->id,
        'project_id' => $project->id,
        'title' => 'X',
        'reason' => 'Y',
        'amount' => 500_000,
    ])->assertUnprocessable()->assertJsonValidationErrors(['project_id']);
});
