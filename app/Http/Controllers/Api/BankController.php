<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesResourceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\BankStoreRequest;
use App\Http\Requests\MasterData\BankUpdateRequest;
use App\Http\Resources\BankResource;
use App\Models\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BankController extends Controller
{
    use HandlesResourceQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $banks = $this->paginateResource(
            Bank::query()->withCount('bankAccounts'),
            $request,
            [
                'searchable' => ['name', 'code', 'swift_code'],
                'filters' => ['is_active' => 'is_active'],
                'sortable' => ['name', 'code', 'is_active', 'created_at'],
                'default_sort' => ['name', 'asc'],
            ],
        );

        return BankResource::collection($banks);
    }

    public function store(BankStoreRequest $request): JsonResponse
    {
        $bank = Bank::create($request->validated());

        return (new BankResource($bank))->response()->setStatusCode(201);
    }

    public function show(Bank $bank): BankResource
    {
        return new BankResource($bank->loadCount('bankAccounts'));
    }

    public function update(BankUpdateRequest $request, Bank $bank): BankResource
    {
        $bank->update($request->validated());

        return new BankResource($bank);
    }

    public function destroy(Bank $bank): Response
    {
        abort_if($bank->bankAccounts()->exists(), 422, 'Bank masih dipakai rekening dan tidak dapat dihapus.');

        $bank->delete();

        return response()->noContent();
    }

    public function restore(int $id): BankResource
    {
        $bank = Bank::onlyTrashed()->findOrFail($id);
        $bank->restore();

        return new BankResource($bank);
    }
}
