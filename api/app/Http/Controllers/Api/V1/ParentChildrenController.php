<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\ParentChildResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\GuardianProfile;
use App\Models\StudentGuardian;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/parents/me/children (contract v0.7 §9.5). Scoped in the query to the caller's own
 * active links, re-read on every request, so a revoked link disappears at once. Students of a
 * suspended institute are included (E2-3: read-only, the guardian's own child).
 */
class ParentChildrenController extends Controller
{
    public function __invoke(PaginatedRequest $request): JsonResponse
    {
        $page = StudentGuardian::query()
            ->active()
            ->whereIn('guardian_id', GuardianProfile::query()->select('id')->where('user_id', $request->user()->getKey()))
            ->with('student.grade')
            ->orderBy('linked_at')
            ->orderBy('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make($page, fn (StudentGuardian $link) => ParentChildResource::make($link)->resolve());
    }
}
