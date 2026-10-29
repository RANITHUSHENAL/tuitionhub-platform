<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The contract §1.3 collection envelope: data plus exactly these meta keys, and no links.
 */
final class PaginatedResponse
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  callable(mixed): array<string, mixed>  $present
     */
    public static function make(LengthAwarePaginator $paginator, callable $present): JsonResponse
    {
        return response()->json([
            'data' => array_map($present, $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
