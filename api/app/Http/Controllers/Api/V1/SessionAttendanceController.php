<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sessions\MarkAttendanceRequest;
use App\Http\Requests\Sessions\ShowSessionAttendanceRequest;
use App\Http\Resources\AttendanceMarkResource;
use App\Http\Resources\SessionResource;
use App\Models\Attendance;
use App\Models\ClassEnrollment;
use App\Models\ClassSession;
use App\Models\StudentProfile;
use App\Services\AttendanceService;
use App\Support\Instant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

/**
 * Contract v0.9 §10.3 (mark) and §10.4 (the session's sheet). The sheet lists every enrolment
 * active on the session date, plus any enrolment already marked for it (for example one withdrawn
 * later), so recorded history never disappears from the sheet.
 */
class SessionAttendanceController extends Controller
{
    public function store(MarkAttendanceRequest $request, ClassSession $session, AttendanceService $attendance): JsonResponse
    {
        $rows = new Collection($attendance->mark($request->user(), $session, $request->validated('records')));
        $rows->load('markedBy');

        return response()->json([
            'data' => $rows->map(fn (Attendance $row) => AttendanceMarkResource::make($row)->resolve())->all(),
        ]);
    }

    public function show(ShowSessionAttendanceRequest $request, ClassSession $session): JsonResponse
    {
        $session->load('tuitionClass');
        $date = $session->session_date->format('Y-m-d');

        $enrollments = ClassEnrollment::query()
            ->with(['student', 'attendances' => fn ($marks) => $marks->where('session_id', $session->getKey())])
            ->where('class_id', $session->class_id)
            ->where(fn (Builder $scope) => $scope
                ->where(fn (Builder $active) => $active->activeOn($date))
                ->orWhereHas('attendances', fn (Builder $marks) => $marks->where('session_id', $session->getKey())))
            ->orderBy(StudentProfile::query()->select('full_name')->whereColumn('student_profiles.id', 'class_enrollments.student_id'))
            ->orderBy('id')
            ->get();

        $rows = $enrollments->map(function (ClassEnrollment $enrollment) {
            $mark = $enrollment->attendances->first();

            return [
                'enrollment_uuid' => $enrollment->uuid,
                'student' => ['uuid' => $enrollment->student->uuid, 'full_name' => $enrollment->student->full_name],
                'attendance' => $mark === null ? null : [
                    'status' => $mark->status,
                    'method' => $mark->method,
                    'notes' => $mark->notes,
                    'marked_at' => Instant::format($mark->marked_at),
                ],
            ];
        });

        $marked = $rows->whereNotNull('attendance')->count();

        return response()->json([
            'data' => $rows->values()->all(),
            'meta' => [
                'session' => SessionResource::make($session)->resolve(),
                'marked_count' => $marked,
                'unmarked_count' => $rows->count() - $marked,
            ],
        ]);
    }
}
