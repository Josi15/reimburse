<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesResourceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\PaymentStoreRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Reimbursement;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    use HandlesResourceQuery;

    public function __construct(private readonly PaymentService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        $payments = $this->paginateResource(
            Payment::query()->with(['processor', 'bankAccount.bank']),
            $request,
            [
                'searchable' => ['payment_number', 'reference_number'],
                'filters' => ['status' => 'status', 'method' => 'method', 'reimbursement_id' => 'reimbursement_id'],
                'sortable' => ['paid_at', 'amount', 'created_at'],
                'default_sort' => ['created_at', 'desc'],
            ],
        );

        return PaymentResource::collection($payments);
    }

    public function show(Payment $payment): PaymentResource
    {
        $this->authorize('view', $payment);

        return new PaymentResource($payment->load(['processor', 'bankAccount.bank', 'attachments']));
    }

    /** Proses pembayaran atas reimbursement Finance-Approved. */
    public function store(PaymentStoreRequest $request, Reimbursement $reimbursement): JsonResponse
    {
        $this->authorize('create', [Payment::class, $reimbursement]);

        $payment = $this->service->process(
            $reimbursement,
            $request->user(),
            $request->validated(),
            $request->file('proof'),
        );

        return (new PaymentResource($payment->load(['processor', 'bankAccount.bank', 'attachments'])))
            ->response()->setStatusCode(201);
    }
}
