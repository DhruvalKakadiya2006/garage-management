<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesSearchFilterSort
{
    /**
     * Apply search to the query builder.
     *
     * Performs case-insensitive partial matching across multiple fields using
     * LIKE with wildcard escaping to prevent SQL injection.
     *
     * @param  array  $searchableFields  Array of column names to search (supports nested relations like 'customer.display_name')
     */
    protected function applySearch(Builder $query, string $searchTerm, array $searchableFields): Builder
    {
        if (empty($searchTerm)) {
            return $query;
        }

        // Escape LIKE wildcards to prevent SQL injection
        $escaped = $this->escapeLikeWildcards($searchTerm);

        $query->where(function ($q) use ($escaped, $searchableFields) {
            foreach ($searchableFields as $field) {
                // Handle nested relationship fields (e.g., 'customer.display_name')
                if (str_contains($field, '.')) {
                    [$relation, $column] = explode('.', $field, 2);
                    $q->orWhereHas($relation, function ($subQuery) use ($column, $escaped) {
                        $subQuery->whereRaw("$column LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
                    });
                } else {
                    $q->orWhereRaw("$field LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
                }
            }
        });

        return $query;
    }

    /**
     * Escape LIKE wildcards in user input.
     *
     * Prevents SQL injection by escaping special LIKE characters (%, _) and
     * the escape character (\) itself.
     */
    protected function escapeLikeWildcards(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Apply sorting to the query builder.
     *
     * Validates sort column against allowed columns and direction (asc/desc),
     * falling back to default sort if invalid.
     *
     * @param  string  $sortDirection  ('asc' or 'desc')
     * @param  array  $allowedColumns  Array of allowed sort columns
     * @param  array  $defaultSort  ['column' => 'created_at', 'direction' => 'desc']
     */
    protected function applySort(
        Builder $query,
        ?string $sortColumn,
        string $sortDirection,
        array $allowedColumns,
        array $defaultSort = ['column' => 'created_at', 'direction' => 'desc']
    ): Builder {
        // Validate sort column
        if (! $sortColumn || ! in_array($sortColumn, $allowedColumns, true)) {
            return $query->orderBy($defaultSort['column'], $defaultSort['direction']);
        }

        // Validate sort direction
        $direction = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        // Apply sorting
        return $query->orderBy($sortColumn, $direction);
    }

    /**
     * Get pagination parameters.
     *
     * Extracts and validates per_page parameter from request, with default
     * and bounds checking.
     */
    protected function getPerPage(Request $request, int $defaultPerPage = 15): int
    {
        return (int) ($request->query('per_page', $defaultPerPage));
    }
}
