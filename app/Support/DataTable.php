<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Server-side list engine shared by every list page:
 * global search, multi-filters, whitelisted sorting, pagination, export rows.
 *
 * Query string contract (kept in the browser URL so views are shareable):
 *   q=term  sort=key  dir=asc|desc  per_page=10|25|50|100  page=N  + module filters
 */
class DataTable
{
    protected array $searchable = [];

    protected array $sortable = [];

    protected ?Closure $filters = null;

    protected ?Closure $transform = null;

    protected string $defaultSort = 'id';

    protected string $defaultDir = 'desc';

    public function __construct(protected Builder $query, protected Request $request) {}

    public static function make(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /** Columns for LIKE search; "relation.column" searches a relation. */
    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    /** key => column name or Closure(Builder, dir). Only whitelisted keys can be sorted. */
    public function sortable(array $map, string $default = 'id', string $dir = 'desc'): self
    {
        $this->sortable = $map;
        $this->defaultSort = $default;
        $this->defaultDir = $dir;

        return $this;
    }

    public function filters(Closure $callback): self
    {
        $this->filters = $callback;

        return $this;
    }

    public function transform(Closure $callback): self
    {
        $this->transform = $callback;

        return $this;
    }

    public function query(): Builder
    {
        $q = clone $this->query;
        $term = trim((string) $this->request->input('q', ''));

        if ($term !== '' && $this->searchable) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
            $q->where(function (Builder $w) use ($like) {
                foreach ($this->searchable as $col) {
                    if (str_contains($col, '.')) {
                        [$rel, $c] = explode('.', $col, 2);
                        $w->orWhereHas($rel, fn ($r) => $r->where($c, 'like', $like));
                    } else {
                        $w->orWhere($w->getModel()->qualifyColumn($col), 'like', $like);
                    }
                }
            });
        }

        if ($this->filters) {
            ($this->filters)($q, $this->request);
        }

        $sort = (string) $this->request->input('sort', $this->defaultSort);
        $dir = strtolower((string) $this->request->input('dir', $this->defaultDir)) === 'asc' ? 'asc' : 'desc';
        $sorter = $this->sortable[$sort] ?? $this->sortable[$this->defaultSort] ?? $this->defaultSort;

        if ($sorter instanceof Closure) {
            $sorter($q, $dir);
        } else {
            $q->orderBy($q->getModel()->qualifyColumn($sorter), $dir);
        }
        $q->orderBy($q->getModel()->qualifyColumn('id'), $dir);

        return $q;
    }

    public function perPage(): int
    {
        $pp = (int) $this->request->input('per_page', settings('general.pagination', 25));

        return in_array($pp, [10, 25, 50, 100], true) ? $pp : 25;
    }

    public function response(array $extra = []): JsonResponse
    {
        $page = $this->query()->paginate($this->perPage())->withQueryString();
        $rows = collect($page->items())->map(fn ($r) => $this->transform ? ($this->transform)($r) : $r->toArray());

        return response()->json([
            'data' => $rows->values(),
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ] + $extra);
    }

    /** All filtered rows for export (capped for safety). */
    public function all(int $limit = 20000): Collection
    {
        return $this->query()->limit($limit)->get();
    }
}
