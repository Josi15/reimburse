<?php

use App\Enums\PaymentStatus;
use App\Enums\ReimbursementStatus;
use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\Payment;
use App\Models\Reimbursement;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('reimbursement number is auto-generated and status is cast to enum', function () {
    $r = Reimbursement::factory()->create(['reimbursement_number' => null]);

    expect($r->reimbursement_number)->toStartWith('RMB-'.date('Y').'-')
        ->and($r->status)->toBeInstanceOf(ReimbursementStatus::class)
        ->and($r->status)->toBe(ReimbursementStatus::Draft);
});

test('reimbursement numbers increment sequentially', function () {
    $a = Reimbursement::factory()->create(['reimbursement_number' => null]);
    $b = Reimbursement::factory()->create(['reimbursement_number' => null]);

    $seqA = (int) substr($a->reimbursement_number, -6);
    $seqB = (int) substr($b->reimbursement_number, -6);

    expect($seqB)->toBe($seqA + 1);
});

test('payment number is auto-generated and status cast to enum', function () {
    $p = Payment::factory()->create(['payment_number' => null]);

    expect($p->payment_number)->toStartWith('PAY-'.date('Y').'-')
        ->and($p->status)->toBeInstanceOf(PaymentStatus::class);
});

test('formatted amount accessor renders IDR', function () {
    $r = Reimbursement::factory()->create(['amount' => 1_500_000]);

    expect($r->formatted_amount)->toBe('Rp 1.500.000');
});

test('state machine allows and blocks the right transitions', function () {
    expect(ReimbursementStatus::Draft->canTransitionTo(ReimbursementStatus::Submitted))->toBeTrue()
        ->and(ReimbursementStatus::Draft->canTransitionTo(ReimbursementStatus::Paid))->toBeFalse()
        ->and(ReimbursementStatus::FinanceApproved->canTransitionTo(ReimbursementStatus::Paid))->toBeTrue()
        ->and(ReimbursementStatus::Paid->isFinal())->toBeTrue();
});

test('reimbursement relationships resolve', function () {
    $r = Reimbursement::factory()->create();
    Payment::factory()->create(['reimbursement_id' => $r->id]);
    Attachment::factory()->create([
        'attachable_type' => Reimbursement::class,
        'attachable_id' => $r->id,
    ]);

    expect($r->payments)->toHaveCount(1)
        ->and($r->attachments)->toHaveCount(1)
        ->and($r->user)->toBeInstanceOf(User::class);
});

test('bank account masked number hides all but last four', function () {
    $account = BankAccount::factory()->create(['account_number' => '1234567890']);

    expect($account->masked_number)->toBe('******7890');
});

test('user role helper works via seeded roles', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'finance')->first());

    expect($user->fresh()->hasRole('finance'))->toBeTrue()
        ->and($user->fresh()->hasRole('admin'))->toBeFalse()
        ->and($user->fresh()->hasPermission('payment.process'))->toBeTrue();
});
