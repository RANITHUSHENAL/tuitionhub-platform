<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teachers\ShowMyTeacherProfileRequest;
use App\Http\Requests\Teachers\UpsertTeacherProfileRequest;
use App\Http\Resources\TeacherResource;
use App\Models\TeacherProfile;
use App\Services\TeacherProfileService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/me/teacher-profile (contract v0.6 §6.1): 201 when created, 200 when updated.
 * GET /api/v1/me/teacher-profile (contract v0.10 §6.5, E2b; D-028): read only.
 */
class TeacherProfileController extends Controller
{
    /**
     * The caller's own profile, or 404 when there is none. Never creates, updates or audits.
     */
    public function show(ShowMyTeacherProfileRequest $request): JsonResponse
    {
        $profile = TeacherProfile::query()->where('user_id', $request->user()->getKey())->first();

        abort_if($profile === null, 404);

        return TeacherResource::make($profile)->response();
    }

    public function upsert(UpsertTeacherProfileRequest $request, TeacherProfileService $profiles): JsonResponse
    {
        [$profile, $created] = $profiles->upsert($request->user(), $request->validated());

        return TeacherResource::make($profile->refresh())->response()->setStatusCode($created ? 201 : 200);
    }
}
