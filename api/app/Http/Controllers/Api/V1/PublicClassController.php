<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\ListPublicClassesRequest;
use App\Http\Resources\PublicClassResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\TuitionClass;
use App\Support\District;
use App\Support\LikePattern;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/public/classes (contract v0.8 §7.5): the minimal public catalogue. Only publicly
 * listed classes (active, opted in, active institute). **Neutral ordering** — starts_on, title,
 * uuid — with no ranking, rating, featured placement or boost effect (C1, §12).
 */
class PublicClassController extends Controller
{
    public function __invoke(ListPublicClassesRequest $request): JsonResponse
    {
        $query = PublicClassResource::query();

        if ($request->filled('subject_code')) {
            $query->where('subject_id', Lookups::id('subjects', $request->validated('subject_code')));
        }

        if ($request->filled('grade_code')) {
            $query->where('grade_id', Lookups::id('grades', $request->validated('grade_code')));
        }

        if ($request->filled('district')) {
            $district = District::canonical($request->validated('district'));
            $query->whereHas('institute', fn (Builder $institute) => $institute->where('district', $district));
        }

        if ($request->filled('q')) {
            $pattern = LikePattern::contains($request->validated('q'));
            $query->where(fn (Builder $text) => $text->where('title', 'like', $pattern)->orWhere('description', 'like', $pattern));
        }

        $page = $query
            ->orderBy('starts_on')
            ->orderBy('title')
            ->orderBy('uuid')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make($page, fn (TuitionClass $class) => PublicClassResource::make($class)->resolve());
    }
}
