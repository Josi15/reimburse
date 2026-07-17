<?php

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Reimbursement;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

/**
 * Phase 21 — Integrasi end-to-end: seluruh siklus hidup reimbursement lewat
 * API nyata, termasuk notifikasi (database), audit log, timeline, dan
 * pencegahan pembayaran ganda — dalam SATU alur.
 */
test('full lifecycle: draft -> submit -> manager -> finance -> paid', function () {
    $manager = userWithRole('manager');
    $finance = userWithRole('finance');
    $employee = employeeUser();
    $employee->update(['manager_id' => $manager->id]);

    $bank = Bank::factory()->create();
    $account = BankAccount::factory()->primary()->create(['user_id' => $employee->id, 'bank_id' => $bank->id]);
    $category = Category::factory()->create(['max_amount' => null]);

    // 1) Employee membuat draft dengan bukti
    Sanctum::actingAs($employee);
    $id = $this->postJson('/api/reimbursements', [
        'category_id' => $category->id,
        'title' => 'Perjalanan Dinas E2E',
        'reason' => 'Uji integrasi menyeluruh',
        'amount' => 1_250_000,
        'bank_account_id' => $account->id,
        'attachments' => [UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf')],
    ])->assertCreated()->json('data.id');

    // 2) Submit
    $this->postJson("/api/reimbursements/{$id}/submit")
        ->assertOk()->assertJsonPath('data.status.value', 'submitted');

    // 3) Manager menyetujui
    Sanctum::actingAs($manager);
    $this->postJson("/api/reimbursements/{$id}/approve")
        ->assertOk()->assertJsonPath('data.status.value', 'manager_approved');

    // 4) Finance menyetujui
    Sanctum::actingAs($finance);
    $this->postJson("/api/reimbursements/{$id}/approve")
        ->assertOk()->assertJsonPath('data.status.value', 'finance_approved');

    // 5) Finance membayar dengan bukti transfer
    $this->postJson("/api/reimbursements/{$id}/pay", [
        'method' => 'bank_transfer',
        'reference_number' => 'TRX-E2E-001',
        'proof' => UploadedFile::fake()->create('bukti-transfer.pdf', 15, 'application/pdf'),
    ])->assertCreated()->assertJsonPath('data.status.value', 'paid');

    // ---- Keadaan akhir ---------------------------------------------------
    $claim = Reimbursement::find($id);
    expect($claim->status->value)->toBe('paid')
        ->and($claim->completed_at)->not->toBeNull()
        ->and($claim->approvals()->count())->toBe(2)
        ->and($claim->payments()->count())->toBe(1)
        ->and($claim->attachments()->count())->toBe(1);

    $payment = $claim->payments()->first();
    expect($payment->attachments()->count())->toBe(1)     // bukti pembayaran
        ->and($payment->amount)->toBe(1_250_000)
        ->and($payment->bank_account_id)->toBe($account->id);

    // Timeline mencerminkan seluruh perjalanan
    Sanctum::actingAs($employee);
    $timeline = collect($this->getJson("/api/reimbursements/{$id}")->json('timeline'));
    expect($timeline->pluck('status'))->toContain('draft', 'submitted', 'approved', 'paid');

    // Notifikasi in-app tepat sasaran
    expect($employee->notifications()->count())->toBe(3)  // manager ok, finance ok, paid
        ->and($manager->notifications()->count())->toBe(1)   // pengajuan masuk
        ->and($finance->notifications()->count())->toBe(1);  // diteruskan ke finance

    // Jejak audit semantik lengkap
    expect(AuditLog::where('event', 'approve')->count())->toBe(2)
        ->and(AuditLog::where('event', 'payment')->count())->toBe(1);

    // Pembayaran kedua ditolak (status sudah paid)
    Sanctum::actingAs($finance);
    $this->postJson("/api/reimbursements/{$id}/pay", ['method' => 'bank_transfer'])->assertForbidden();
    expect(Payment::count())->toBe(1);
});

/**
 * Alur revisi: manager minta revisi → employee memperbaiki & submit ulang →
 * manager setuju → finance menolak (final).
 */
test('revision flow: revise, resubmit, then finance rejects', function () {
    $manager = userWithRole('manager');
    $finance = userWithRole('finance');
    $employee = employeeUser();
    $category = Category::factory()->create(['max_amount' => null]);

    // Draft tanpa bukti (diizinkan) lalu submit
    Sanctum::actingAs($employee);
    $id = $this->postJson('/api/reimbursements', [
        'category_id' => $category->id,
        'title' => 'Pengajuan Revisi',
        'reason' => 'Uji alur revisi',
        'amount' => 900_000,
    ])->assertCreated()->json('data.id');
    $this->postJson("/api/reimbursements/{$id}/submit")->assertOk();

    // Manager meminta revisi
    Sanctum::actingAs($manager);
    $this->postJson("/api/reimbursements/{$id}/revision", ['notes' => 'Nominal tidak sesuai nota'])
        ->assertOk()->assertJsonPath('data.status.value', 'revision_requested');

    // Employee memperbaiki (revision_requested masih editable) dan submit ulang
    Sanctum::actingAs($employee);
    $this->putJson("/api/reimbursements/{$id}", ['amount' => 750_000])
        ->assertOk()->assertJsonPath('data.amount', 750000);
    $this->postJson("/api/reimbursements/{$id}/submit")
        ->assertOk()->assertJsonPath('data.status.value', 'submitted');

    // Manager setuju, Finance menolak (alasan wajib)
    Sanctum::actingAs($manager);
    $this->postJson("/api/reimbursements/{$id}/approve")->assertOk();
    Sanctum::actingAs($finance);
    $this->postJson("/api/reimbursements/{$id}/reject", ['notes' => 'Anggaran departemen habis'])
        ->assertOk()->assertJsonPath('data.status.value', 'finance_rejected');

    // Riwayat: revisi + approve + reject = 3 baris approval
    $claim = Reimbursement::find($id);
    expect($claim->approvals()->count())->toBe(3);

    Sanctum::actingAs($employee);
    $timeline = collect($this->getJson("/api/reimbursements/{$id}")->json('timeline'));
    expect($timeline->pluck('status'))->toContain('revision_requested', 'rejected');
});
