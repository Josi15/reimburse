<?php

namespace App\Services;

use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;

/**
 * Logika bisnis rekening bank milik user. Menjamin satu rekening utama
 * (single-primary) secara atomik; kepemilikan divalidasi di controller.
 */
class BankAccountService
{
    /** Buat rekening. Rekening pertama otomatis menjadi utama. */
    public function create(int $userId, array $data): BankAccount
    {
        $isFirst = ! BankAccount::where('user_id', $userId)->exists();
        $makePrimary = ($data['is_primary'] ?? false) || $isFirst;

        return DB::transaction(function () use ($data, $userId, $makePrimary) {
            if ($makePrimary) {
                $this->clearPrimary($userId);
            }

            return BankAccount::create([
                'user_id' => $userId,
                'bank_id' => $data['bank_id'],
                'account_number' => $data['account_number'],
                'account_holder_name' => $data['account_holder_name'],
                'is_primary' => $makePrimary,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    /** Perbarui rekening; jika dijadikan utama, rekening lain di-nonutama-kan. */
    public function update(BankAccount $account, array $data): BankAccount
    {
        return DB::transaction(function () use ($account, $data) {
            if (($data['is_primary'] ?? false) === true) {
                $this->clearPrimary($account->user_id);
            }
            $account->update($data);

            return $account;
        });
    }

    /** Tetapkan rekening sebagai utama (menonaktifkan primary lainnya). */
    public function setPrimary(BankAccount $account): BankAccount
    {
        return DB::transaction(function () use ($account) {
            $this->clearPrimary($account->user_id);
            $account->update(['is_primary' => true]);

            return $account;
        });
    }

    private function clearPrimary(int $userId): void
    {
        BankAccount::where('user_id', $userId)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
