<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institutes\ListInstitutesRequest;
use App\Http\Requests\Institutes\StoreInstituteRequest;
use App\Http\Requests\Institutes\UpdateInstituteRequest;
use App\Http\Resources\InstituteResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\Institute;
use App\Services\InstituteService;
use App\Support\District;
use App\Support\LikePattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Contract v0.5 §5.1, §5.2, §5.4, §5.5. Services are method-injected so they are built after
 * BindAuditContext has bound this request's audit context.
 */
class InstituteController extends Controller
{
    public function store(StoreInstituteRequest $request, InstituteService $institutes): JsonResponse
    {
        $institute = $institutes->create($request->user(), $request->validated());

        return InstituteResource::make($institute->refresh())->response()->setStatusCode(201);
    }

    public function index(ListInstitutesRequest $request): JsonResponse
    {
        $query = Institute::query();

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        if ($request->filled('district')) {
            $query->where('district', District::canonical($request->validated('district')));
        }

        if ($request->filled('q')) {
            $query->where('name', 'like', LikePattern::contains($request->validated('q')));
        }

        $page = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make($page, fn (Institute $institute) => InstituteResource::make($institute)->resolve());
    }

    public function show(Institute $institute): JsonResponse
    {
        Gate::authorize('view', $institute);

        return InstituteResource::make($institute)->response();
    }

    public function update(UpdateInstituteRequest $request, Institute $institute, InstituteService $institutes): JsonResponse
    {
        $institute = $institutes->update($request->user(), $institute, $request->validated());

        return InstituteResource::make($institute->refresh())->response();
    }
}
