<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guardians\ListStudentGuardianInvitationsRequest;
use App\Http\Requests\Guardians\ListStudentGuardiansRequest;
use App\Http\Resources\GuardianInvitationHistoryResource;
use App\Http\Resources\StudentGuardianResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\GuardianInvitation;
use App\Models\StudentGuardian;
use App\Models\StudentProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Read-only guardian views of one student for its institute (contract v0.10 §9.6, §9.7, proposed
 * E2b; D-028). Authorization is in the FormRequests. Every query is scoped to the route's student
 * before anything is returned, and nothing here writes: lazy expiry is evaluated in memory and the
 * stored invitation rows are left unchanged.
 */
class StudentGuardianController extends Controller
{
    /**
     * Active links only (revoked links are history and never listed). Primary first, then oldest
     * link, then internal order as the unique tiebreaker.
     */
    public function guardians(ListStudentGuardiansRequest $request, StudentProfile $student): JsonResponse
    {
        $page = StudentGuardian::query()
            ->active()
            ->where('student_id', $student->getKey())
            ->with('guardian.user')
            ->orderByDesc('is_primary')
            ->orderBy('linked_at')
            ->orderBy('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make($page, fn (StudentGuardian $link) => StudentGuardianResource::make($link)->resolve());
    }

    /**
     * Every invitation for the student, newest first. The `status` filter and the status shown both
     * use the effective status (§9.1 lazy expiry) at one instant, `$asOf`:
     *
     * - pending:  stored `pending` and `expires_at` > asOf
     * - expired:  stored `expired`, or stored `pending` with `expires_at` <= asOf
     * - accepted / revoked: the stored status (expiry does not change a finished invitation)
     */
    public function invitations(ListStudentGuardianInvitationsRequest $request, StudentProfile $student): JsonResponse
    {
        // Whole seconds, like the invitation service, so the SQL filter and effectiveStatus() agree.
        $asOf = CarbonImmutable::now('UTC')->startOfSecond();

        $query = GuardianInvitation::query()
            ->where('student_id', $student->getKey())
            ->with('invitedBy');

        $status = $request->validated('status');

        if ($status === GuardianInvitation::STATUS_PENDING) {
            $query->where('status', GuardianInvitation::STATUS_PENDING)->where('expires_at', '>', $asOf);
        } elseif ($status === GuardianInvitation::STATUS_EXPIRED) {
            $query->where(fn (Builder $expired) => $expired
                ->where('status', GuardianInvitation::STATUS_EXPIRED)
                ->orWhere(fn (Builder $lapsed) => $lapsed
                    ->where('status', GuardianInvitation::STATUS_PENDING)
                    ->where('expires_at', '<=', $asOf)));
        } elseif ($status !== null) {
            $query->where('status', $status);
        }

        $page = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make(
            $page,
            fn (GuardianInvitation $invitation) => GuardianInvitationHistoryResource::make($invitation)->asOf($asOf)->resolve(),
        );
    }
}
