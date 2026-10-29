<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Presenters\AuthSessionPresenter;
use App\Http\Requests\Me\UpdateMeRequest;
use App\Services\UserProfileService;
use Illuminate\Http\JsonResponse;

/**
 * PATCH /api/v1/me (contract v0.5 §4.5). Returns the GET /auth/session shape, so the SPA sees
 * onboarding_required change in the same response.
 */
class MeController extends Controller
{
    public function update(UpdateMeRequest $request, UserProfileService $profiles, AuthSessionPresenter $presenter): JsonResponse
    {
        $user = $profiles->updateName($request->user(), $request->validated('name'));

        return response()->json(['data' => $presenter->session($user)]);
    }
}
