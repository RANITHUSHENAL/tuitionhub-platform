<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teachers\UpsertTeacherProfileRequest;
use App\Http\Resources\TeacherResource;
use App\Services\TeacherProfileService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/me/teacher-profile (contract v0.6 §6.1): 201 when created, 200 when updated.
 */
class TeacherProfileController extends Controller
{
    public function upsert(UpsertTeacherProfileRequest $request, TeacherProfileService $profiles): JsonResponse
    {
        [$profile, $created] = $profiles->upsert($request->user(), $request->validated());

        return TeacherResource::make($profile->refresh())->response()->setStatusCode($created ? 201 : 200);
    }
}
