<?php

use App\Models\Reimbursement;
use App\Services\AttachmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

/** Buat reimbursement + satu lampiran tersimpan. */
function claimWithAttachment(string $status = 'draft'): array
{
    $owner = employeeUser();
    $claim = Reimbursement::factory()->create(['user_id' => $owner->id, 'status' => $status]);
    $attachment = app(AttachmentService::class)->store(
        UploadedFile::fake()->create('nota.pdf', 60, 'application/pdf'),
        $claim,
        $owner,
    );

    return [$owner, $claim, $attachment];
}

test('owner can download their attachment', function () {
    [$owner, , $attachment] = claimWithAttachment();
    Sanctum::actingAs($owner);

    $this->get("/api/attachments/{$attachment->id}/download")->assertOk();
});

test('another user cannot download the attachment', function () {
    [, , $attachment] = claimWithAttachment();
    Sanctum::actingAs(employeeUser());

    $this->get("/api/attachments/{$attachment->id}/download")->assertForbidden();
});

test('owner can preview their attachment inline', function () {
    [$owner, , $attachment] = claimWithAttachment();
    Sanctum::actingAs($owner);

    $this->get("/api/attachments/{$attachment->id}/preview")
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('owner can delete an attachment on a draft', function () {
    [$owner, , $attachment] = claimWithAttachment('draft');
    Sanctum::actingAs($owner);

    $this->deleteJson("/api/attachments/{$attachment->id}")->assertNoContent();
    $this->assertSoftDeleted('attachments', ['id' => $attachment->id]);
    Storage::disk('local')->assertMissing($attachment->file_path);
});

test('an attachment on a submitted claim cannot be modified', function () {
    [$owner, , $attachment] = claimWithAttachment('submitted');
    Sanctum::actingAs($owner);

    $this->deleteJson("/api/attachments/{$attachment->id}")->assertForbidden();
});

test('owner can replace the file', function () {
    [$owner, , $attachment] = claimWithAttachment('draft');
    Sanctum::actingAs($owner);

    $this->post("/api/attachments/{$attachment->id}/replace", [
        'file' => UploadedFile::fake()->create('nota-baru.pdf', 40, 'application/pdf'),
    ])->assertOk()->assertJsonPath('data.file_name', 'nota-baru.pdf');

    Storage::disk('local')->assertMissing($attachment->file_path); // file lama terhapus
});

test('replace rejects invalid file types', function () {
    [$owner, , $attachment] = claimWithAttachment('draft');
    Sanctum::actingAs($owner);

    $this->post("/api/attachments/{$attachment->id}/replace", [
        'file' => UploadedFile::fake()->create('bad.exe', 10),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['file']);
});
