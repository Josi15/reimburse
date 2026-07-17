<?php

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Reimbursement;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

// ---- Security headers (Phase 19) ------------------------------------------

test('security headers are present on every response', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'same-origin');
});

// ---- Deep MIME check --------------------------------------------------------

test('a file named .pdf but with executable content is rejected', function () {
    Sanctum::actingAs(employeeUser());
    $category = Category::factory()->create();

    // Nama mengaku PDF, konten sebenarnya executable → deep MIME menolak.
    $this->postJson('/api/reimbursements', [
        'category_id' => $category->id,
        'title' => 'Uji MIME',
        'reason' => 'Tes keamanan',
        'amount' => 100_000,
        'attachments' => [
            UploadedFile::fake()->create('malware.pdf', 10, 'application/x-msdownload'),
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['attachments.0']);
});

test('a genuine pdf still passes deep MIME validation', function () {
    Sanctum::actingAs(employeeUser());
    $category = Category::factory()->create();

    $this->postJson('/api/reimbursements', [
        'category_id' => $category->id,
        'title' => 'Uji MIME OK',
        'reason' => 'Tes keamanan',
        'amount' => 100_000,
        'attachments' => [
            UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf'),
        ],
    ])->assertCreated();
});

// ---- Payment endpoint rate limiting ----------------------------------------

test('the pay endpoint is rate limited to 10 attempts per minute', function () {
    $employee = employeeUser();
    $bank = Bank::factory()->create();
    $account = BankAccount::factory()->primary()->create(['user_id' => $employee->id, 'bank_id' => $bank->id]);
    $claim = Reimbursement::factory()->financeApproved()->create([
        'user_id' => $employee->id, 'bank_account_id' => $account->id,
    ]);

    Sanctum::actingAs(userWithRole('finance'));

    // 10 percobaan pertama melewati limiter (hasil boleh 201/403), ke-11 harus 429.
    foreach (range(1, 10) as $i) {
        $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer']);
    }

    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])
        ->assertStatus(429);
});
