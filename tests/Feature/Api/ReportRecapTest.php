<?php

use App\Models\CompanyBankAccount;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Reimbursement;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('project recap totals requested and paid amounts per project', function () {
    $project = Project::factory()->create(['budget' => 10_000_000]);
    Reimbursement::factory()->create(['project_id' => $project->id, 'amount' => 1_000_000]); // draft
    Reimbursement::factory()->paid()->create(['project_id' => $project->id, 'amount' => 2_000_000]);
    Reimbursement::factory()->create(['amount' => 5_000_000]); // tanpa project → tak masuk rekap

    Sanctum::actingAs(userWithRole('admin'));
    $data = collect($this->getJson('/api/reports/projects')->assertOk()->json('data'));

    $row = $data->firstWhere('project_id', $project->id);
    expect($row)->not->toBeNull()
        ->and($row['count'])->toBe(2)
        ->and($row['total_amount'])->toBe(3_000_000)
        ->and($row['paid_amount'])->toBe(2_000_000)
        ->and($row['budget'])->toBe(10_000_000);
});

test('company account recap totals paid payments per source account', function () {
    $source = CompanyBankAccount::factory()->create(['label' => 'Kas Operasional']);
    Payment::factory()->paid()->create(['source_account_id' => $source->id, 'amount' => 4_000_000]);
    Payment::factory()->paid()->create(['source_account_id' => $source->id, 'amount' => 1_500_000]);

    Sanctum::actingAs(userWithRole('finance'));
    $data = collect($this->getJson('/api/reports/company-accounts')->assertOk()->json('data'));

    $row = $data->firstWhere('source_account_id', $source->id);
    expect($row)->not->toBeNull()
        ->and($row['label'])->toBe('Kas Operasional')
        ->and($row['count'])->toBe(2)
        ->and($row['total_amount'])->toBe(5_500_000);
});
