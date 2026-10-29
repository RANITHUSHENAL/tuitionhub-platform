<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\ListStudentsRequest;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Resources\StudentResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\Institute;
use App\Models\StudentProfile;
use App\Services\StudentService;
use App\Support\LikePattern;
use App\Support\Lookups;
use Illuminate\Http\JsonResponse;

/**
 * Contract v0.6 §8.1 (create) and §8.2 (list) for institute-managed students. Every query is scoped
 * to the institute in the route before anything is returned (contract §1.1).
 */
class StudentController extends Controller
{
    public function store(StoreStudentRequest $request, Institute $institute, StudentService $students): JsonResponse
    {
        $student = $students->create($request->user(), $institute, $request->validated());

        return StudentResource::make(StudentResource::query()->findOrFail($student->getKey()))
            ->response()
            ->setStatusCode(201);
    }

    public function index(ListStudentsRequest $request, Institute $institute): JsonResponse
    {
        $query = StudentResource::query()->where('managing_institute_id', $institute->getKey());

        if ($request->filled('q')) {
            $query->where('full_name', 'like', LikePattern::contains($request->validated('q')));
        }

        if ($request->filled('grade_code')) {
            $query->where('grade_id', Lookups::id('grades', $request->validated('grade_code')));
        }

        $page = $query
            ->orderBy('full_name')
            ->orderBy('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make($page, fn (StudentProfile $student) => StudentResource::make($student)->resolve());
    }
}
