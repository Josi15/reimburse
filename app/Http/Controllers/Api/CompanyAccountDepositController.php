<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesResourceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\DepositStoreRequest;
use App\Http\Resources\CompanyAccountDepositResource;
use App\Models\CompanyAccountDeposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Pemasukan (top-up) rekening perusahaan. Dikelola Finance/Admin
 * (permission:company_account.manage).
 */
class CompanyAccountDepositController extends Controller
{
    use HandlesResourceQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CompanyAccountDeposit::query()->with(['companyBankAccount.bank', 'creator']);

        if ($request->filled('company_bank_account_id')) {
            $query->where('company_bank_account_id', $request->integer('company_bank_account_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('deposited_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('deposited_at', '<=', $request->date('date_to'));
        }

        $deposits = $query->orderByDesc('deposited_at')->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 15), 100))
            ->withQueryString();

        return CompanyAccountDepositResource::collection($deposits);
    }

    public function store(DepositStoreRequest $request): JsonResponse
    {
        $deposit = CompanyAccountDeposit::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return (new CompanyAccountDepositResource($deposit->load(['companyBankAccount.bank', 'creator'])))
            ->response()->setStatusCode(201);
    }

    public function destroy(CompanyAccountDeposit $deposit): Response
    {
        $deposit->delete();

        return response()->noContent();
    }
}
