<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccount\BankAccountStoreRequest;
use App\Http\Requests\BankAccount\BankAccountUpdateRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Rekening bank milik user yang login. Semua aksi dibatasi ke pemilik.
 * Rekening utama dijamin tunggal (partial unique index + logika di sini).
 */
class BankAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = BankAccount::query()
            ->where('user_id', $request->user()->id)
            ->with('bank')
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        return BankAccountResource::collection($accounts);
    }

    public function store(BankAccountStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        // Rekening pertama otomatis jadi utama.
        $isFirst = ! BankAccount::where('user_id', $userId)->exists();
        $makePrimary = ($data['is_primary'] ?? false) || $isFirst;

        $account = DB::transaction(function () use ($data, $userId, $makePrimary) {
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

        return (new BankAccountResource($account->load('bank')))->response()->setStatusCode(201);
    }

    public function show(Request $request, BankAccount $bankAccount): BankAccountResource
    {
        $this->ensureOwner($request, $bankAccount);

        return new BankAccountResource($bankAccount->load('bank'));
    }

    public function update(BankAccountUpdateRequest $request, BankAccount $bankAccount): BankAccountResource
    {
        $this->ensureOwner($request, $bankAccount);
        $data = $request->validated();

        DB::transaction(function () use ($bankAccount, $data) {
            if (($data['is_primary'] ?? false) === true) {
                $this->clearPrimary($bankAccount->user_id);
            }
            $bankAccount->update($data);
        });

        return new BankAccountResource($bankAccount->load('bank'));
    }

    public function destroy(Request $request, BankAccount $bankAccount): Response
    {
        $this->ensureOwner($request, $bankAccount);

        $bankAccount->delete();

        return response()->noContent();
    }

    /** Tetapkan sebagai rekening utama. */
    public function setPrimary(Request $request, BankAccount $bankAccount): BankAccountResource
    {
        $this->ensureOwner($request, $bankAccount);

        DB::transaction(function () use ($bankAccount) {
            $this->clearPrimary($bankAccount->user_id);
            $bankAccount->update(['is_primary' => true]);
        });

        return new BankAccountResource($bankAccount->load('bank'));
    }

    private function ensureOwner(Request $request, BankAccount $bankAccount): void
    {
        abort_unless($bankAccount->user_id === $request->user()->id, 403, 'Bukan rekening milik Anda.');
    }

    private function clearPrimary(int $userId): void
    {
        BankAccount::where('user_id', $userId)->where('is_primary', true)->update(['is_primary' => false]);
    }
}
