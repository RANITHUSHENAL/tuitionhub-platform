<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\TenantAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\ListMyTeacherClassesRequest;
use App\Http\Resources\ClassResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\TeacherAssignment;
use App\Models\TuitionClass;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/me/teacher/classes (contract v0.8 §6.4): the caller's classes, scoped in the query to
 * classes they are assigned to **and** institutes where their assignment is still active. An
 * ended assignment hides that institute's classes at once.
 */
class MyTeacherClassesController extends Controller
{
    public function __invoke(ListMyTeacherClassesRequest $request, TenantAccess $access): JsonResponse
    {
        $teacherId = (int) $access->teacherProfileId($request->user());

        $query = ClassResource::query()
            ->where('teacher_id', $teacherId)
            ->whereIn('institute_id', TeacherAssignment::query()->active()->select('institute_id')->where('teacher_id', $teacherId));

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $page = $query
            ->orderBy('starts_on')
            ->orderBy('title')
            ->orderBy('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make($page, fn (TuitionClass $class) => ClassResource::make($class)->resolve());
    }
}
