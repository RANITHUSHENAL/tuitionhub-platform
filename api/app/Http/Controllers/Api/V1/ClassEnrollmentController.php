<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollments\ListClassEnrollmentsRequest;
use App\Http\Requests\Enrollments\StoreEnrollmentRequest;
use App\Http\Requests\Enrollments\WithdrawEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\ClassEnrollment;
use App\Models\StudentProfile;
use App\Models\TuitionClass;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;

/**
 * Contract v0.8 §8.3 (enrol), §8.4 (roster) and §8.5 (withdraw). Rosters are scoped to the class
 * in the route before anything is returned; guardians never see a roster.
 */
class ClassEnrollmentController extends Controller
{
    public function store(StoreEnrollmentRequest $request, TuitionClass $class, EnrollmentService $enrollments): JsonResponse
    {
        $enrollment = $enrollments->enroll(
            $request->user(),
            $class,
            (string) $request->validated('student_uuid'),
            $request->validated('enrolled_on'),
        );

        return EnrollmentResource::make($enrollment)->response()->setStatusCode(201);
    }

    public function index(ListClassEnrollmentsRequest $request, TuitionClass $class): JsonResponse
    {
        $query = ClassEnrollment::query()
            ->with(['student', 'tuitionClass'])
            ->where('class_id', $class->getKey());

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $page = $query
            ->orderBy(StudentProfile::query()->select('full_name')->whereColumn('student_profiles.id', 'class_enrollments.student_id'))
            ->orderBy('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make($page, fn (ClassEnrollment $enrollment) => EnrollmentResource::make($enrollment)->resolve());
    }

    public function withdraw(WithdrawEnrollmentRequest $request, ClassEnrollment $enrollment, EnrollmentService $enrollments): JsonResponse
    {
        $enrollment = $enrollments->withdraw($request->user(), $enrollment, (string) $request->validated('withdrawn_on'));

        return EnrollmentResource::make($enrollment)->response();
    }
}
