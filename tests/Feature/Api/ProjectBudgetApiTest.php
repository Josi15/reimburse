<?php

use App\Models\Project;
use App\Models\Reimbursement;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** Buat klaim dengan status & nominal tertentu pada sebuah proyek. */
function claimOn(Project $project, string $status, int $amount): Reimbursement
{
    return Reimbursement::factory()->create([
        'project_id' => $project->id,
        'status' => $status,
        'amount' => $amount,
    ]);
}

test('project manager only sees the projects they hold', function () {
    $pm = userWithRole('project_manager');
    $other = userWithRole('project_manager');

    $mine = Project::factory()->managedBy($pm)->create(['name' => 'Proyek Saya']);
    Project::factory()->managedBy($other)->create(['name' => 'Proyek Orang Lain']);
    Project::factory()->create(['name' => 'Proyek Tanpa Pemegang']);

    Sanctum::actingAs($pm);
    $data = $this->getJson('/api/project-budgets')->assertOk()->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($mine->id);
});

test('remaining budget counts paid plus in-progress claims only', function () {
    $pm = userWithRole('project_manager');
    $project = Project::factory()->managedBy($pm)->create(['budget' => 100_000_000]);

    claimOn($project, 'paid', 20_000_000);
    claimOn($project, 'submitted', 5_000_000);
    claimOn($project, 'finance_approved', 5_000_000);
    claimOn($project, 'draft', 9_000_000);            // tidak membebani anggaran
    claimOn($project, 'manager_rejected', 8_000_000); // tidak membebani anggaran

    Sanctum::actingAs($pm);
    $row = $this->getJson('/api/project-budgets')->assertOk()->json('data.0');

    expect($row['paid_amount'])->toBe(20_000_000)
        ->and($row['pending_amount'])->toBe(10_000_000)
        ->and($row['used_amount'])->toBe(30_000_000)
        ->and($row['remaining_amount'])->toBe(70_000_000)
        ->and($row['draft_amount'])->toBe(9_000_000)
        ->and($row['rejected_amount'])->toBe(8_000_000)
        ->and($row['usage_percent'])->toEqual(30)
        ->and($row['is_over_budget'])->toBeFalse();
});

test('a project spending more than its budget is flagged as over budget', function () {
    $pm = userWithRole('project_manager');
    $project = Project::factory()->managedBy($pm)->create(['budget' => 10_000_000]);
    claimOn($project, 'paid', 12_000_000);

    Sanctum::actingAs($pm);
    $row = $this->getJson('/api/project-budgets')->assertOk()->json('data.0');

    expect($row['remaining_amount'])->toBe(-2_000_000)
        ->and($row['is_over_budget'])->toBeTrue();
});

test('an unbudgeted project reports a null remaining amount', function () {
    $pm = userWithRole('project_manager');
    $project = Project::factory()->managedBy($pm)->create(['budget' => null]);
    claimOn($project, 'paid', 3_000_000);

    Sanctum::actingAs($pm);
    $row = $this->getJson('/api/project-budgets')->assertOk()->json('data.0');

    expect($row['budget'])->toBeNull()
        ->and($row['remaining_amount'])->toBeNull()
        ->and($row['usage_percent'])->toBeNull()
        ->and($row['paid_amount'])->toBe(3_000_000);
});

test('the detail endpoint breaks the spending down per status', function () {
    $pm = userWithRole('project_manager');
    $project = Project::factory()->managedBy($pm)->create(['budget' => 50_000_000]);
    claimOn($project, 'paid', 5_000_000);
    claimOn($project, 'submitted', 1_000_000);

    Sanctum::actingAs($pm);
    $data = $this->getJson("/api/project-budgets/{$project->id}")->assertOk()->json('data');

    expect($data['remaining_amount'])->toBe(44_000_000)
        ->and($data['by_status'])->toHaveCount(2)
        ->and($data['recent_reimbursements'])->toHaveCount(2);
});

test('a project manager cannot open a project held by someone else', function () {
    $pm = userWithRole('project_manager');
    $foreign = Project::factory()->managedBy(userWithRole('project_manager'))->create();

    Sanctum::actingAs($pm);
    $this->getJson("/api/project-budgets/{$foreign->id}")->assertForbidden();
});

test('finance sees every project budget', function () {
    Project::factory()->managedBy(userWithRole('project_manager'))->create();
    Project::factory()->create();

    Sanctum::actingAs(userWithRole('finance'));
    $this->getJson('/api/project-budgets')->assertOk()->assertJsonCount(2, 'data');
});

test('employees have no access to project budgets', function () {
    $project = Project::factory()->create();

    Sanctum::actingAs(userWithRole('employee'));
    $this->getJson('/api/project-budgets')->assertForbidden();
    $this->getJson("/api/project-budgets/{$project->id}")->assertForbidden();
});

test('admin can assign a project manager when creating a project', function () {
    $pm = userWithRole('project_manager');
    Sanctum::actingAs(userWithRole('admin'));

    $this->postJson('/api/projects', [
        'code' => 'PRJ-PM-001',
        'name' => 'Proyek Berpemegang',
        'manager_id' => $pm->id,
        'budget' => 75_000_000,
    ])->assertCreated()
        ->assertJsonPath('data.manager_id', $pm->id)
        ->assertJsonPath('data.manager.name', $pm->name);
});

test('the projects page is reachable for a project manager but not an employee', function () {
    $pm = userWithRole('project_manager');
    $project = Project::factory()->managedBy($pm)->create();

    $this->actingAs($pm)->get('/projects')->assertOk();
    $this->actingAs($pm)->get("/projects/{$project->id}")->assertOk();
    $this->actingAs(userWithRole('employee'))->get('/projects')->assertForbidden();
});

test('project manager options only list active project managers', function () {
    $pm = userWithRole('project_manager');
    userWithRole('employee');

    Sanctum::actingAs(userWithRole('admin'));
    $data = $this->getJson('/api/options/project-managers')->assertOk()->json('data');

    expect(collect($data)->pluck('id')->all())->toBe([$pm->id]);
});
