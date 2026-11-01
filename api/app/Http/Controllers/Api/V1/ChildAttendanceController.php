<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guardians\ListChildAttendanceRequest;
use App\Http\Resources\ChildAttendanceResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\Attendance;
use App\Models\ClassEnrollment;
use App\Models\StudentProfile;
use App\Models\TuitionClass;
use App\Support\LocalClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * GET /api/v1/parents/me/children/{uuid}/attendance (contract v0.9 §10.5). The request has already
 * required an active guardian link. Scoped in the query to this student's own enrolments; past
 * attendance stays visible after a withdrawal (D-024 Q51), because access comes from the guardian
 * link, not the enrolment.
 */
class ChildAttendanceController extends Controller
{
    /** Inclusive window: 366 calendar days at most (§10.5). */
    private const MAX_DAYS = 366;

    private const DEFAULT_DAYS = 30;

    public function __invoke(ListChildAttendanceRequest $request, StudentProfile $student): JsonResponse
    {
        [$from, $to] = $this->window($request->validated('from'), $request->validated('to'));

        $query = Attendance::query()
            ->select('attendances.*')
            ->join('class_sessions', 'class_sessions.id', '=', 'attendances.session_id')
            ->whereIn('attendances.enrollment_id', ClassEnrollment::query()->select('id')->where('student_id', $student->getKey()))
            ->whereBetween('class_sessions.session_date', [$from, $to])
            ->with(['session', 'tuitionClass']);

        if ($request->filled('class_uuid')) {
            $query->whereIn('attendances.class_id', TuitionClass::query()->select('id')->where('uuid', $request->validated('class_uuid')));
        }

        $page = $query
            ->orderByDesc('class_sessions.session_date')
            ->orderByDesc('class_sessions.start_time')
            ->orderByDesc('attendances.id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make($page, fn (Attendance $row) => ChildAttendanceResource::make($row)->resolve());
    }

    /**
     * Defaults: `to` = today (Asia/Colombo), `from` = 30 days ending on `to`. At most 366 days.
     *
     * @return array{0: string, 1: string}
     *
     * @throws ValidationException
     */
    private function window(?string $from, ?string $to): array
    {
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $to ?? LocalClock::today());
        $from = $from === null ? $to->subDays(self::DEFAULT_DAYS - 1) : CarbonImmutable::createFromFormat('!Y-m-d', $from);

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages(['from' => 'The from date must be on or before the to date.']);
        }

        if ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'The date range must not exceed '.self::MAX_DAYS.' days.']);
        }

        return [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }
}
