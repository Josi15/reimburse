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
    Role::query()->update(['reimbursement_limit' => null]);

    $this->employee = employeeUser();
    $this->category = Category::factory()->create(['max_amount' => null]);

    // Pengaju yang berhak mengajukan pengadaan: punya reimbursement.create
    // (dari role employee) sekaligus reimbursement.procurement (dari manager).
    $this->buyer = employeeUser();
    $this->buyer->roles()->attach(Role::where('name', 'manager')->firstOrFail());
    $this->buyer = $this->buyer->fresh();

    Sanctum::actingAs($this->employee);
});

/** Bertindak sebagai pengaju yang boleh memakai jenis pengadaan. */
function actAsBuyer(): void
{
    Sanctum::actingAs(test()->buyer);
}

test('the claim type catalogue exposes every type with its input fields', function () {
    Sanctum::actingAs(userWithRole('super_admin'));

    $response = $this->getJson('/api/options/claim-types')->assertOk();

    expect($response->json('data.*.value'))
        ->toBe(['expense', 'goods', 'service', 'overtime']);

    $goods = collect($response->json('data'))->firstWhere('value', 'goods');

    expect($goods['amount_formula'])->toBe(['quantity', 'unit_price'])
        ->and(array_column($goods['fields'], 'key'))->toContain('item_name', 'quantity', 'unit_price');
});

// ---- Pembatasan jenis per role -------------------------------------------

test('an employee is only offered expense and overtime', function () {
    $response = $this->getJson('/api/options/claim-types')->assertOk();

    expect($response->json('data.*.value'))->toBe(['expense', 'overtime']);
});

test('roles holding the procurement permission are offered all four', function () {
    foreach (['manager', 'finance', 'admin'] as $role) {
        Sanctum::actingAs(userWithRole($role));

        expect($this->getJson('/api/options/claim-types')->json('data.*.value'))
            ->toBe(['expense', 'goods', 'service', 'overtime'], "role {$role}");
    }
});

test('an employee submitting a goods claim through the API is rejected', function () {
    $response = $this->postJson('/api/reimbursements', [
        'claim_type' => 'goods',
        'category_id' => $this->category->id,
        'title' => 'Beli server',
        'reason' => 'Kapasitas penuh',
        'details' => ['item_name' => 'Server', 'quantity' => 1, 'unit_price' => 1_000_000, 'urgency' => 'normal'],
    ])->assertStatus(422)->assertJsonValidationErrors('claim_type');

    // Pesannya menyebut jenis yang memang boleh dipakai.
    expect($response->json('errors.claim_type.0'))
        ->toContain('Reimbursement Biaya')
        ->toContain('Lembur');
});

test('an employee submitting a service claim through the API is rejected', function () {
    $this->postJson('/api/reimbursements', [
        'claim_type' => 'service',
        'category_id' => $this->category->id,
        'title' => 'Perpanjangan VPS',
        'reason' => 'Masa aktif habis',
        'details' => ['service_name' => 'VPS', 'billing_cycle' => 'monthly', 'quantity' => 1, 'unit_price' => 500_000],
    ])->assertStatus(422)->assertJsonValidationErrors('claim_type');
});

test('an employee can still submit expense and overtime claims', function () {
    $this->postJson('/api/reimbursements', [
        'category_id' => $this->category->id,
        'title' => 'Taksi ke klien',
        'reason' => 'Meeting',
        'amount' => 150_000,
    ])->assertCreated();

    $this->postJson('/api/reimbursements', [
        'claim_type' => 'overtime',
        'category_id' => $this->category->id,
        'title' => 'Lembur rilis',
        'reason' => 'Deploy malam',
        'details' => [
            'overtime_date' => now()->subDay()->toDateString(),
            'start_time' => '19:00', 'end_time' => '21:00',
            'hours' => 2, 'hourly_rate' => 50_000,
            'work_description' => 'Deploy',
        ],
    ])->assertCreated();
});

test('a goods request stores its details and derives the amount', function () {
    actAsBuyer();

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
            'work_description' => 'Deploy & monitoring rilis',
        ],
    ])->assertCreated();

    // Tarifnya dari jabatan Employee (Rp 30.000/jam), bukan isian pengaju.
    expect($response->json('data.details.hourly_rate'))->toBe(30_000)
        ->and($response->json('data.amount'))->toBe(135_000);   // 4,5 jam x 30rb
});

test('required detail fields of the chosen type are enforced', function () {
    actAsBuyer();

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
    actAsBuyer();

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
            'work_description' => 'Perbaikan bug produksi',
        ],
    ])->assertOk();

    $claim->refresh();

    expect($claim->claim_type)->toBe(ClaimType::Overtime)
        ->and($claim->details)->not->toHaveKey('item_name')
        ->and($claim->amount)->toBe(60_000);   // 2 jam x 30rb (tarif Employee)
});
