<?php

use App\Models\Category;
use App\Models\Reimbursement;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    $this->category = Category::factory()->create(['max_amount' => 2_000_000]);
});

// (helper employeeUser() didefinisikan di tests/Pest.php)

function draftPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => test()->category->id,
        'title' => 'Perjalanan Dinas Surabaya',
        'reason' => 'Tiket kereta dan hotel',
        'amount' => 750_000,
    ], $overrides);
}

// ---- Create draft ---------------------------------------------------------

test('employee creates a draft with auto number and draft status', function () {
    Sanctum::actingAs(employeeUser());

    $this->postJson('/api/reimbursements', draftPayload())
        ->assertCreated()
        ->assertJsonPath('data.status.value', 'draft')
        ->assertJsonPath('data.currency', 'IDR')
        ->assertJsonPath('data.formatted_amount', 'Rp 750.000')
        ->assertJsonStructure(['data' => ['reimbursement_number']]);
});

test('role ceiling blocks an amount above the employee reimbursement limit', function () {
    // Set plafon role employee secara eksplisit agar test mandiri (default seeder
    // = tanpa batas). Kategori mengizinkan s/d Rp2jt, plafon jabatan Rp1jt.
    Role::where('name', 'employee')->update(['reimbursement_limit' => 1_000_000]);
    Sanctum::actingAs(employeeUser());

    $this->postJson('/api/reimbursements', draftPayload(['amount' => 1_500_000]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    // Dalam plafon jabatan → sukses.
    $this->postJson('/api/reimbursements', draftPayload(['amount' => 900_000]))
        ->assertCreated();
});

test('monthly role ceiling accumulates across submissions in the same month', function () {
    Role::where('name', 'employee')->update(['reimbursement_limit' => 1_000_000]);
    Sanctum::actingAs(employeeUser());

    // Pengajuan pertama Rp700rb → oke (terpakai 0).
    $this->postJson('/api/reimbursements', draftPayload(['amount' => 700_000]))->assertCreated();

    // Kedua Rp400rb → terpakai 700rb, 700+400 = 1,1jt > 1jt → ditolak.
    $this->postJson('/api/reimbursements', draftPayload(['amount' => 400_000]))
        ->assertUnprocessable()->assertJsonValidationErrors(['amount']);

    // Yang pas sisa (Rp300rb) → 700+300 = 1jt → oke.
    $this->postJson('/api/reimbursements', draftPayload(['amount' => 300_000]))->assertCreated();
});

test('the monthly quota endpoint reports limit, used, and remaining', function () {
    Role::where('name', 'employee')->update(['reimbursement_limit' => 1_000_000]);
    Sanctum::actingAs(employeeUser());
    $this->postJson('/api/reimbursements', draftPayload(['amount' => 400_000]))->assertCreated();

    $this->getJson('/api/reimbursements/quota')->assertOk()
        ->assertJsonPath('limit', 1000000)
        ->assertJsonPath('used', 400000)
        ->assertJsonPath('remaining', 600000);
});

test('reason and amount are required', function () {
    Sanctum::actingAs(employeeUser());

    $this->postJson('/api/reimbursements', draftPayload(['reason' => '', 'amount' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'amount']);
});

test('amount cannot exceed category plafon', function () {
    Sanctum::actingAs(employeeUser());

    $this->postJson('/api/reimbursements', draftPayload(['amount' => 3_000_000]))
        ->assertUnprocessable()->assertJsonValidationErrors(['amount']);
});

test('a claim can be submitted without attachments', function () {
    Sanctum::actingAs(employeeUser());

    $this->postJson('/api/reimbursements', draftPayload())->assertCreated();
});

test('multiple attachments are stored', function () {
    $user = employeeUser();
    Sanctum::actingAs($user);

    $res = $this->postJson('/api/reimbursements', draftPayload([
        'attachments' => [
            UploadedFile::fake()->create('nota1.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->image('nota2.jpg'),
        ],
    ]))->assertCreated();

    $id = $res->json('data.id');
    $reimbursement = Reimbursement::find($id);
    expect($reimbursement->attachments)->toHaveCount(2);
    Storage::disk('local')->assertExists($reimbursement->attachments->first()->file_path);
});

test('invalid file types are rejected', function () {
    Sanctum::actingAs(employeeUser());

    $this->postJson('/api/reimbursements', draftPayload([
        'attachments' => [UploadedFile::fake()->create('virus.exe', 10)],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['attachments.0']);
});

// ---- Edit / delete draft --------------------------------------------------

test('owner can edit their draft but others cannot', function () {
    $owner = employeeUser();
    $r = Reimbursement::factory()->create(['user_id' => $owner->id, 'status' => 'draft']);

    Sanctum::actingAs($owner);
    $this->putJson("/api/reimbursements/{$r->id}", ['title' => 'Judul Baru'])
        ->assertOk()->assertJsonPath('data.title', 'Judul Baru');

    Sanctum::actingAs(employeeUser());
    $this->putJson("/api/reimbursements/{$r->id}", ['title' => 'Hack'])->assertForbidden();
});

test('a draft can be deleted but a submitted claim cannot', function () {
    $owner = employeeUser();
    Sanctum::actingAs($owner);

    $draft = Reimbursement::factory()->create(['user_id' => $owner->id, 'status' => 'draft']);
    $this->deleteJson("/api/reimbursements/{$draft->id}")->assertNoContent();
    $this->assertSoftDeleted('reimbursements', ['id' => $draft->id]);

    $submitted = Reimbursement::factory()->submitted()->create(['user_id' => $owner->id]);
    $this->deleteJson("/api/reimbursements/{$submitted->id}")->assertForbidden();
});

// ---- Submit (state machine) ----------------------------------------------

test('submitting moves draft to submitted and stamps submitted_at', function () {
    $owner = employeeUser();
    $draft = Reimbursement::factory()->create(['user_id' => $owner->id, 'status' => 'draft']);
    Sanctum::actingAs($owner);

    $this->postJson("/api/reimbursements/{$draft->id}/submit")
        ->assertOk()->assertJsonPath('data.status.value', 'submitted');

    expect($draft->fresh()->submitted_at)->not->toBeNull();
});

test('a submitted claim cannot be edited or re-submitted', function () {
    $owner = employeeUser();
    $submitted = Reimbursement::factory()->submitted()->create(['user_id' => $owner->id]);
    Sanctum::actingAs($owner);

    $this->putJson("/api/reimbursements/{$submitted->id}", ['title' => 'X'])->assertForbidden();
    $this->postJson("/api/reimbursements/{$submitted->id}/submit")->assertForbidden();
});

// ---- Detail + timeline ----------------------------------------------------

test('detail includes a status timeline', function () {
    $owner = employeeUser();
    $r = Reimbursement::factory()->submitted()->create(['user_id' => $owner->id]);
    Sanctum::actingAs($owner);

    $this->getJson("/api/reimbursements/{$r->id}")
        ->assertOk()
        ->assertJsonPath('data.reimbursement_number', $r->reimbursement_number)
        ->assertJsonStructure(['timeline' => [['status', 'label', 'at']]]);
});

// ---- Index scoping --------------------------------------------------------

test('employees see only their own claims while viewers see all', function () {
    $a = employeeUser();
    $b = employeeUser();
    Reimbursement::factory()->create(['user_id' => $a->id]);
    Reimbursement::factory()->create(['user_id' => $b->id]);

    Sanctum::actingAs($a);
    expect($this->getJson('/api/reimbursements')->json('data'))->toHaveCount(1);

    Sanctum::actingAs(userWithRole('finance'));
    expect($this->getJson('/api/reimbursements')->json('data'))->toHaveCount(2);
});
