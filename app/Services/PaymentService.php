<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReimbursementStatus;
use App\Models\BankAccount;
use App\Models\Payment;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proses pembayaran reimbursement oleh Finance.
 *
 * Keamanan konkurensi: baris reimbursement dikunci dengan lockForUpdate()
 * (SELECT ... FOR UPDATE) di dalam transaksi. Jika dua staff Finance memproses
 * reimbursement yang sama bersamaan, yang kedua akan menunggu, lalu melihat
 * status sudah "paid" dan ditolak. Partial unique index pada payments (status
 * bukan failed/cancelled) menjadi lapisan pertahanan kedua di level DB.
 */
class PaymentService
{
    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly ReimbursementNotifier $notifier,
    ) {}

    public function process(Reimbursement $reimbursement, User $finance, array $data, ?UploadedFile $proof = null): Payment
    {
        $payment = DB::transaction(function () use ($reimbursement, $finance, $data, $proof) {
            // Kunci baris reimbursement untuk mencegah race condition.
            $locked = Reimbursement::whereKey($reimbursement->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReimbursementStatus::FinanceApproved) {
                $this->fail('Reimbursement tidak berstatus Finance Approved (kemungkinan sudah dibayar).');
            }

            if ($locked->payments()->active()->exists()) {
                $this->fail('Reimbursement ini sudah memiliki pembayaran aktif.');
            }

            $account = $this->resolveAccount($locked, $data['bank_account_id'] ?? $locked->bank_account_id);

            $amount = (int) ($data['amount'] ?? $locked->amount);
            if ($amount > $locked->amount) {
                $this->fail('Nominal pembayaran tidak boleh melebihi nominal yang disetujui.');
            }

            $payment = Payment::create([
                'reimbursement_id' => $locked->id,
                'bank_account_id' => $account->id,
                'processed_by' => $finance->id,
                'amount' => $amount,
                'currency' => 'IDR',
                'method' => $data['method'],
                'status' => PaymentStatus::Paid,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
            ]);

            if ($proof) {
                $this->attachments->store($proof, $payment, $finance);
            }

            // Setelah pembayaran berhasil, status reimbursement menjadi Paid.
            $locked->update([
                'status' => ReimbursementStatus::Paid,
                'completed_at' => now(),
            ]);

            return $payment;
        });

        // Notifikasi "Paid" ke pemilik setelah transaksi commit.
        $this->notifier->paid($reimbursement->refresh(), $payment);

        return $payment;
    }

    /** Pastikan rekening tujuan valid: milik pengaju & aktif. */
    private function resolveAccount(Reimbursement $reimbursement, ?int $bankAccountId): BankAccount
    {
        if (! $bankAccountId) {
            $this->fail('Rekening tujuan belum ditentukan pada reimbursement ini.');
        }

        $account = BankAccount::where('id', $bankAccountId)
            ->where('user_id', $reimbursement->user_id)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            $this->fail('Rekening tujuan tidak valid, tidak aktif, atau bukan milik pengaju.');
        }

        return $account;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
