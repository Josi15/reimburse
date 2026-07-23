<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccount\BankAccountStoreRequest;
use App\Http\Requests\BankAccount\BankAccountUpdateRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use App\Services\BankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Rekening bank milik user yang login. Semua aksi dibatasi ke pemilik.
 * Rekening utama dijamin tunggal (partial unique index + BankAccountService).
 */
class BankAccountController extends Controller
{
    public function __construct(private readonly BankAccountService $service) {}

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
        $account = $this->service->create($request->user()->id, $request->validated());

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

        $this->service->update($bankAccount, $request->validated());

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

        $this->service->setPrimary($bankAccount);

        return new BankAccountResource($bankAccount->load('bank'));
    }

    private function ensureOwner(Request $request, BankAccount $bankAccount): void
    {
        abort_unless($bankAccount->user_id === $request->user()->id, 403, 'Bukan rekening milik Anda.');
    }
}
