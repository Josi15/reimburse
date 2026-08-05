<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search lintas entitas. Hasil reimbursement mengikuti cakupan role
 * (pribadi / departemen / lintas departemen, lihat Reimbursement::visibleTo);
 * hasil user hanya untuk pemegang user.view dan ikut disaring per departemen.
 */
class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(['data' => ['reimbursements' => [], 'users' => []]]);
        }

        $user = $request->user();

        $reimbursements = Reimbursement::query()
            ->visibleTo($user)
            ->where(function (Builder $s) use ($q) {
                $s->where('reimbursement_number', 'ilike', "%{$q}%")
                    ->orWhere('title', 'ilike', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'reimbursement_number', 'title', 'status', 'amount'])
            ->map(fn (Reimbursement $r) => [
                'id' => $r->id,
                'reimbursement_number' => $r->reimbursement_number,
                'title' => $r->title,
                'status' => $r->status->value,
                'amount' => $r->amount,
            ]);

        $users = collect();
        if ($user->hasPermission('user.view') || $user->hasRole('super_admin')) {
            $users = User::query()
                ->unless($user->seesAllDepartments(), fn (Builder $x) => $x
                    ->where('department_id', $user->department_id))
                ->where(function (Builder $s) use ($q) {
                    $s->where('name', 'ilike', "%{$q}%")->orWhere('email', 'ilike', "%{$q}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'email']);
        }

        return response()->json(['data' => [
            'reimbursements' => $reimbursements,
            'users' => $users,
        ]]);
    }
}
