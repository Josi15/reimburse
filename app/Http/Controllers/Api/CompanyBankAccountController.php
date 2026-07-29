<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesResourceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\CompanyBankAccountStoreRequest;
use App\Http\Requests\MasterData\CompanyBankAccountUpdateRequest;
use App\Http\Resources\CompanyBankAccountResource;
use App\Models\CompanyBankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Master rekening perusahaan (sumber pembayaran). Dikelola Finance/Admin.
 */
class CompanyBankAccountController extends Controller
{
    use HandlesResourceQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = $this->paginateResource(
            CompanyBankAccount::query()->with('bank'),
            $request,
            [
                'searchable' => ['label', 'account_number', 'account_holder_name'],
                'filters' => ['is_active' => 'is_active', 'bank_id' => 'bank_id'],
                'sortable' => ['label', 'is_active', 'created_at'],
                'default_sort' => ['label', 'asc'],
            ],
        );

        return CompanyBankAccountResource::collection($accounts);
    }

    public function store(CompanyBankAccountStoreRequest $request): JsonResponse
    {
        $account = CompanyBankAccount::create($request->validated());

        return (new CompanyBankAccountResource($account->load('bank')))->response()->setStatusCode(201);
    }

    public function show(CompanyBankAccount $companyAccount): CompanyBankAccountResource
    {
        return new CompanyBankAccountResource($companyAccount->load('bank'));
    }

    public function update(CompanyBankAccountUpdateRequest $request, CompanyBankAccount $companyAccount): CompanyBankAccountResource
    {
        $companyAccount->update($request->validated());

        return new CompanyBankAccountResource($companyAccount->load('bank'));
    }

    public function destroy(CompanyBankAccount $companyAccount): Response
    {
        $companyAccount->delete();

        return response()->noContent();
    }

    public function restore(int $id): CompanyBankAccountResource
    {
        $account = CompanyBankAccount::onlyTrashed()->findOrFail($id);
        $account->restore();

        return new CompanyBankAccountResource($account->load('bank'));
    }
}
