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
 * (Employee = miliknya sendiri); hasil user hanya untuk pemegang user.view.
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
        $seesAll = $user->hasPermission('reimbursement.viewAny') || $user->hasRole('super_admin');

        $reimbursements = Reimbursement::query()
            ->when(! $seesAll, fn (Builder $x) => $x->where('user_id', $user->id))
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
