<?php

use App\Enums\ClaimType;
use App\Models\Category;
use App\Models\Reimbursement;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Fokus tes ini pada jenis pengajuan, bukan plafon bulanan jabatan —
    // (nominal barang/server memang besar). Plafon diuji terpisah.
    Role::where('name', 'employee')->update(['reimbursement_limit' => null]);

    $this->employee = employeeUser();
    $this->category = Category::factory()->create(['max_amount' => null]);
    Sanctum::actingAs($this->employee);
});

test('the claim type catalogue exposes every type with its input fields', function () {
    $response = $this->getJson('/api/options/claim-types')->assertOk();

    expect($response->json('data.*.value'))
        ->toBe(['expense', 'goods', 'service', 'overtime']);

    $goods = collect($response->json('data'))->firstWhere('value', 'goods');

    expect($goods['amount_formula'])->toBe(['quantity', 'unit_price'])
        ->and(array_column($goods['fields'], 'key'))->toContain('item_name', 'quantity', 'unit_price');
});

test('a goods request stores its details and derives the amount', function () {
    $response = $this->postJson('/api/reimbursements', [
        'claim_type' => 'goods',
        'category_id' => $this->category->id,
        'title' => 'Penambahan server produksi',
        'reason' => 'Kapasitas server lama sudah penuh',
        'amount' => 1,   // diabaikan: server menghitung ulang dari detail
        'details' => [
            'item_name' => 'Server Dell PowerEdge R650',
            'specification' => '2x Xeon Silver, 64 GB RAM, 2 TB SSD',
            'quantity' => 2,
            'unit_price' => 45_000_000,
            'vendor' => 'PT Sumber Teknologi',
            'urgency' => 'urgent',
        ],
    ])->assertCreated();

    expect($response->json('data.claim_type.value'))->toBe('goods')
        ->and($response->json('data.amount'))->toBe(90_000_000)
        ->and($response->json('data.details.item_name'))->toBe('Server Dell PowerEdge R650');

    $claim = Reimbursement::find($response->json('data.id'));
    expect($claim->claim_type)->toBe(ClaimType::Goods)
        ->and($claim->details['quantity'])->toBe(2);
});

test('an overtime request derives the amount from hours times rate', function () {
    $response = $this->postJson('/api/reimbursements', [
        'claim_type' => 'overtime',
        'category_id' => $this->category->id,
        'title' => 'Lembur rilis versi 2.0',
        'reason' => 'Deployment di luar jam kerja',
        'amount' => 999,
        'details' => [
            'overtime_date' => now()->subDay()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '22:30',
            'hours' => 4.5,
            'hourly_rate' => 50_000,
            'work_description' => 'Deploy & monitoring rilis',
        ],
    ])->assertCreated();

    expect($response->json('data.amount'))->toBe(225_000);
});

test('required detail fields of the chosen type are enforced', function () {
    $this->postJson('/api/reimbursements', [
        'claim_type' => 'goods',
        'category_id' => $this->category->id,
        'title' => 'Beli laptop',
        'reason' => 'Laptop lama rusak',
        'amount' => 1_000_000,
        'details' => ['vendor' => 'Toko A'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['details.item_name', 'details.quantity', 'details.unit_price', 'details.urgency']);
});

test('unknown detail keys are dropped instead of stored', function () {
    $response = $this->postJson('/api/reimbursements', [
        'claim_type' => 'service',
        'category_id' => $this->category->id,
        'title' => 'Perpanjangan VPS',
        'reason' => 'Masa aktif habis bulan depan',
        'details' => [
            'service_name' => 'VPS Biznet Gio',
            'billing_cycle' => 'monthly',
            'quantity' => 12,
            'unit_price' => 750_000,
            'hacked_field' => 'nilai asing',
        ],
    ])->assertCreated();

    expect($response->json('data.amount'))->toBe(9_000_000)
        ->and($response->json('data.details'))->not->toHaveKey('hacked_field');
});

test('a claim without an explicit type stays a plain expense claim', function () {
    $response = $this->postJson('/api/reimbursements', [
        'category_id' => $this->category->id,
        'title' => 'Taksi ke klien',
        'reason' => 'Meeting di kantor klien',
        'amount' => 150_000,
    ])->assertCreated();

    expect($response->json('data.claim_type.value'))->toBe('expense')
        ->and($response->json('data.amount'))->toBe(150_000);
});

test('the index can be filtered by claim type', function () {
    Reimbursement::factory()->goods()->create(['user_id' => $this->employee->id]);
    Reimbursement::factory()->overtime()->create(['user_id' => $this->employee->id]);

    $this->getJson('/api/reimbursements?claim_type=overtime')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.claim_type.value', 'overtime');
});

test('switching a draft to another type replaces its details', function () {
    $claim = Reimbursement::factory()->goods()->create([
        'user_id' => $this->employee->id, 'status' => 'draft', 'category_id' => $this->category->id,
    ]);

    $this->putJson("/api/reimbursements/{$claim->id}", [
        'claim_type' => 'overtime',
        'details' => [
            'overtime_date' => now()->subDay()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '21:00',
            'hours' => 2,
            'hourly_rate' => 40_000,
            'work_description' => 'Perbaikan bug produksi',
        ],
    ])->assertOk();

    $claim->refresh();

    expect($claim->claim_type)->toBe(ClaimType::Overtime)
        ->and($claim->details)->not->toHaveKey('item_name')
        ->and($claim->amount)->toBe(80_000);
});
