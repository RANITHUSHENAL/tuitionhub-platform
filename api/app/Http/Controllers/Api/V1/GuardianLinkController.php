<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guardians\RevokeGuardianLinkRequest;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\GuardianLinkService;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * DELETE /api/v1/students/{uuid}/guardian-links/{guardianUuid} (contract v0.7 §9.4). guardianUuid
 * is the guardian's user UUID. It is resolved only after authorization, so a caller who may not
 * revoke cannot probe which users exist; malformed, unknown, and "no active link" are the same 404.
 */
class GuardianLinkController extends Controller
{
    public function destroy(RevokeGuardianLinkRequest $request, StudentProfile $student, string $guardianUuid, GuardianLinkService $links): Response
    {
        $guardian = Str::isUuid($guardianUuid)
            ? User::query()->where('uuid', $guardianUuid)->first()
            : null;

        abort_if($guardian === null, 404);

        $links->revoke($request->user(), $student, $guardian);

        return response()->noContent();
    }
}
