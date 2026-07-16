<?php

namespace App\Http\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

/**
 * Pola query REST lintas-modul: search (q), filter eksak, sorting whitelist,
 * penanganan soft-delete (with_trashed/only_trashed), dan pagination.
 * Dipakai controller Master Data (Phase 8) dan modul lain (pola Phase 14).
 *
 * $config = [
 *   'searchable'   => ['name', 'code'],          // kolom untuk ?q=
 *   'filters'      => ['is_active' => 'is_active'], // ?param => kolom (eksak)
 *   'sortable'     => ['name', 'created_at'],     // whitelist ?sort
 *   'default_sort' => ['created_at', 'desc'],
 * ]
 */
trait HandlesResourceQuery
{
    protected function paginateResource(Builder $query, Request $request, array $config): LengthAwarePaginator
    {
        $searchable = $config['searchable'] ?? [];
        $filters = $config['filters'] ?? [];
        $sortable = $config['sortable'] ?? [];
        [$defaultSort, $defaultDir] = $config['default_sort'] ?? ['created_at', 'desc'];

        // Pencarian bebas (case-insensitive, PostgreSQL ILIKE).
        if (filled($q = $request->query('q')) && $searchable) {
            $query->where(function (Builder $sub) use ($searchable, $q) {
                foreach ($searchable as $column) {
                    $sub->orWhere($column, 'ilike', '%'.$q.'%');
                }
            });
        }

        // Filter eksak berdasarkan whitelist.
        foreach ($filters as $param => $column) {
            if ($request->has($param) && $request->query($param) !== '') {
                $value = $request->query($param);
                if (in_array($value, ['true', 'false'], true)) {
                    $value = $value === 'true';
                }
                $query->where($column, $value);
            }
        }

        // Soft delete (hanya untuk model yang mendukung).
        if ($this->modelUsesSoftDeletes($query)) {
            if ($request->boolean('only_trashed')) {
                $query->onlyTrashed();
            } elseif ($request->boolean('with_trashed')) {
                $query->withTrashed();
            }
        }

        // Sorting dengan whitelist (cegah SQL injection kolom).
        $sort = $request->query('sort', $defaultSort);
        if (! in_array($sort, $sortable, true)) {
            $sort = $defaultSort;
        }
        $direction = strtolower((string) $request->query('direction', $defaultDir)) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        // Pagination (per_page dibatasi 1..100).
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));

        return $query->paginate($perPage)->withQueryString();
    }

    private function modelUsesSoftDeletes(Builder $query): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($query->getModel()), true);
    }
}
