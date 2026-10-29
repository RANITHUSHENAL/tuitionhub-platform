<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\TenantAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\InstituteResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\Institute;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/me/institutes (contract v0.5 §5.3): stored institute roles merged with active
 * teaching assignments. my_access is a display hint; every endpoint re-derives authorization.
 */
class MyInstitutesController extends Controller
{
    public function index(PaginatedRequest $request, TenantAccess $access): JsonResponse
    {
        $user = $request->user();
        $page = $access->myInstitutes($user)->paginate($request->perPage(), ['*'], 'page', $request->page());
        $labels = $access->accessLabels($user, $page->items());

        return PaginatedResponse::make($page, fn (Institute $institute) => [
            'institute' => InstituteResource::make($institute)->resolve(),
            'my_access' => $labels[$institute->getKey()] ?? [],
        ]);
    }
}
