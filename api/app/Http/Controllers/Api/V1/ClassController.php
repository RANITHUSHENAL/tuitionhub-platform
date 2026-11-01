<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\ListInstituteClassesRequest;
use App\Http\Requests\Classes\StoreClassRequest;
use App\Http\Requests\Classes\UpdateClassRequest;
use App\Http\Resources\ClassResource;
use App\Http\Resources\PublicClassResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\Institute;
use App\Models\TeacherProfile;
use App\Models\TuitionClass;
use App\Services\ClassService;
use App\Support\Lookups;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Contract v0.8 §7.1 (create), §7.2 (institute list), §7.3 (show) and §7.4 (update).
 * Authorization is in the FormRequests and ClassPolicy; services authorize again under the locks.
 */
class ClassController extends Controller
{
    public function store(StoreClassRequest $request, Institute $institute, ClassService $classes): JsonResponse
    {
        $class = $classes->create($request->user(), $institute, $request->validated());

        return ClassResource::make(ClassResource::query()->findOrFail($class->getKey()))->response()->setStatusCode(201);
    }

    public function index(ListInstituteClassesRequest $request, Institute $institute): JsonResponse
    {
        $query = ClassResource::query()->where('institute_id', $institute->getKey());

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        if ($request->filled('subject_code')) {
            $query->where('subject_id', Lookups::id('subjects', $request->validated('subject_code')));
        }

        if ($request->filled('grade_code')) {
            $query->where('grade_id', Lookups::id('grades', $request->validated('grade_code')));
        }

        if ($request->filled('teacher_uuid')) {
            $query->whereIn('teacher_id', TeacherProfile::query()->select('id')->where('uuid', $request->validated('teacher_uuid')));
        }

        $page = $query
            ->orderBy('title')
            ->orderBy('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make($page, fn (TuitionClass $class) => ClassResource::make($class)->resolve());
    }

    /**
     * Public route (§7.3). A caller who may see the class's private data gets `Class`; anyone else
     * gets `PublicClass` for a publicly listed class, and the generic 404 otherwise. The optional
     * session is read through the sanctum guard; a suspended account counts as anonymous.
     */
    public function show(Request $request, TuitionClass $class): JsonResponse
    {
        $user = $request->user('sanctum');

        if ($user !== null && Gate::forUser($user)->allows('view', $class)) {
            return ClassResource::make(ClassResource::query()->findOrFail($class->getKey()))->response();
        }

        $public = PublicClassResource::query()->whereKey($class->getKey())->first();

        abort_if($public === null, 404);

        return PublicClassResource::make($public)->response();
    }

    public function update(UpdateClassRequest $request, TuitionClass $class, ClassService $classes): JsonResponse
    {
        $classes->update($request->user(), $class, $request->validated());

        return ClassResource::make(ClassResource::query()->findOrFail($class->getKey()))->response();
    }
}
