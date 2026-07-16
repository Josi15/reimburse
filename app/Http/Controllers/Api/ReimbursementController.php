<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesResourceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reimbursement\StoreReimbursementRequest;
use App\Http\Requests\Reimbursement\UpdateReimbursementRequest;
use App\Http\Resources\ReimbursementResource;
use App\Models\Attachment;
use App\Models\Reimbursement;
use App\Services\AttachmentService;
use App\Services\ReimbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ReimbursementController extends Controller
{
    use HandlesResourceQuery;

    public function __construct(private readonly ReimbursementService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Reimbursement::class);

        $query = Reimbursement::query()->with(['category', 'user']);

        // Employee tanpa izin viewAny hanya melihat miliknya sendiri.
        if (! $request->user()->hasPermission('reimbursement.viewAny')
            && ! $request->user()->hasRole('super_admin')) {
            $query->forUser($request->user()->id);
        }

        $items = $this->paginateResource($query, $request, [
            'searchable' => ['reimbursement_number', 'title'],
            'filters' => ['status' => 'status', 'category_id' => 'category_id', 'user_id' => 'user_id'],
            'sortable' => ['created_at', 'submitted_at', 'amount', 'status'],
            'default_sort' => ['created_at', 'desc'],
        ]);

        return ReimbursementResource::collection($items);
    }

    public function store(StoreReimbursementRequest $request): JsonResponse
    {
        $this->authorize('create', Reimbursement::class);

        $reimbursement = $this->service->createDraft(
            $request->user(),
            $request->validated(),
            $request->file('attachments') ?? [],
        );

        return (new ReimbursementResource($reimbursement->load(['category', 'attachments'])))
            ->response()->setStatusCode(201);
    }

    public function show(Reimbursement $reimbursement): ReimbursementResource
    {
        $this->authorize('view', $reimbursement);

        $reimbursement->load(['category', 'department', 'user', 'attachments']);

        return (new ReimbursementResource($reimbursement))
            ->additional(['timeline' => $this->service->buildTimeline($reimbursement)]);
    }

    public function update(UpdateReimbursementRequest $request, Reimbursement $reimbursement): ReimbursementResource
    {
        $this->authorize('update', $reimbursement);

        $reimbursement = $this->service->updateDraft(
            $reimbursement,
            $request->user(),
            $request->validated(),
            $request->file('attachments') ?? [],
            $request->input('delete_attachment_ids', []),
        );

        return new ReimbursementResource($reimbursement->load(['category', 'attachments']));
    }

    public function destroy(Reimbursement $reimbursement): Response
    {
        $this->authorize('delete', $reimbursement);

        $reimbursement->delete();

        return response()->noContent();
    }

    /** Ajukan reimbursement (Draft/Revisi → Submitted). */
    public function submit(Reimbursement $reimbursement): ReimbursementResource
    {
        $this->authorize('submit', $reimbursement);

        $reimbursement = $this->service->submit($reimbursement);

        return new ReimbursementResource($reimbursement->load(['category', 'attachments']));
    }

    /** Hapus satu lampiran (hanya saat reimbursement masih editable). */
    public function destroyAttachment(Reimbursement $reimbursement, Attachment $attachment): Response
    {
        $this->authorize('update', $reimbursement);

        abort_if(
            $attachment->attachable_type !== Reimbursement::class || $attachment->attachable_id !== $reimbursement->id,
            404,
            'Lampiran tidak ditemukan pada reimbursement ini.',
        );

        app(AttachmentService::class)->delete($attachment);

        return response()->noContent();
    }
}
