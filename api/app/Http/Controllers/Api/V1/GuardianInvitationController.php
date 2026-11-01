<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guardians\AcceptGuardianInvitationRequest;
use App\Http\Requests\Guardians\RevokeGuardianInvitationRequest;
use App\Http\Requests\Guardians\StoreGuardianInvitationRequest;
use App\Http\Resources\GuardianInvitationResource;
use App\Models\GuardianInvitation;
use App\Models\StudentProfile;
use App\Services\GuardianInvitationService;
use App\Support\Instant;
use Illuminate\Http\JsonResponse;

/**
 * Contract v0.7 §9.1 (create), §9.2 (accept) and §9.3 (revoke). Authorization is in the
 * FormRequests; the service authorizes again under the institute row lock. Every /api/v1 response
 * is `no-store`, which covers the one response that carries a code.
 */
class GuardianInvitationController extends Controller
{
    public function store(StoreGuardianInvitationRequest $request, StudentProfile $student, GuardianInvitationService $invitations): JsonResponse
    {
        [$invitation, $code] = $invitations->create(
            $request->user(),
            $student,
            (string) $request->validated('guardian_phone'),
            (string) $request->validated('relationship'),
        );

        return response()->json([
            'data' => [...GuardianInvitationResource::make($invitation)->resolve(), 'code' => $code],
            'meta' => ['code_shown_once' => true],
        ], 201);
    }

    public function accept(AcceptGuardianInvitationRequest $request, GuardianInvitationService $invitations): JsonResponse
    {
        $link = $invitations->accept($request->user(), (string) $request->validated('code'));

        return response()->json([
            'data' => [
                'student' => ['uuid' => $link->student->uuid, 'full_name' => $link->student->full_name],
                'relationship' => $link->relationship,
                'linked_at' => Instant::format($link->linked_at),
            ],
        ], 201);
    }

    public function revoke(RevokeGuardianInvitationRequest $request, GuardianInvitation $invitation, GuardianInvitationService $invitations): JsonResponse
    {
        $invitation = $invitations->revoke($request->user(), $invitation);

        return response()->json([
            'data' => [
                'uuid' => $invitation->uuid,
                'status' => $invitation->status,
                'revoked_at' => Instant::format($invitation->revoked_at),
            ],
        ]);
    }
}
