<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teachers\ListInstituteTeachersRequest;
use App\Http\Requests\Teachers\StoreTeacherAssignmentRequest;
use App\Http\Resources\TeacherAssignmentResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\Institute;
use App\Models\TeacherAssignment;
use App\Models\TeacherProfile;
use App\Services\TeacherAssignmentService;
use Illuminate\Http\JsonResponse;

/**
 * Contract v0.6 §6.2 (assign a teacher) and §6.3 (list an institute's teachers). Authorization is in
 * the FormRequests (InstitutePolicy); the service authorizes again under the institute row lock.
 */
class InstituteTeacherController extends Controller
{
    public function store(StoreTeacherAssignmentRequest $request, Institute $institute, TeacherAssignmentService $assignments): JsonResponse
    {
        $teacher = TeacherProfile::query()->where('uuid', $request->validated('teacher_uuid'))->firstOrFail();

        $assignment = $assignments->assign($request->user(), $institute, $teacher, $request->validated('joined_on'));
        $assignment->refresh()->load('teacher');

        return TeacherAssignmentResource::make($assignment)->withInstitute($institute)->response()->setStatusCode(201);
    }

    public function index(ListInstituteTeachersRequest $request, Institute $institute): JsonResponse
    {
        $query = TeacherAssignment::query()
            ->with('teacher')
            ->where('institute_id', $institute->getKey());

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $page = $query
            ->orderByDesc('joined_on')
            ->orderByDesc('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make($page, fn (TeacherAssignment $assignment) => TeacherAssignmentResource::make($assignment)->resolve());
    }
}
