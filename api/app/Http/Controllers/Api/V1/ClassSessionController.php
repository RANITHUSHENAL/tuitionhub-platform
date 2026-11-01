<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sessions\CancelSessionRequest;
use App\Http\Requests\Sessions\ListSessionsRequest;
use App\Http\Requests\Sessions\StoreSessionRequest;
use App\Http\Resources\SessionResource;
use App\Http\Responses\PaginatedResponse;
use App\Models\ClassSession;
use App\Models\TuitionClass;
use App\Services\SessionService;
use Illuminate\Http\JsonResponse;

/**
 * Contract v0.9 §10.1 (create), §10.2 (list) and §10.6 (cancel). Lists are scoped to the class in
 * the route before anything is returned.
 */
class ClassSessionController extends Controller
{
    public function store(StoreSessionRequest $request, TuitionClass $class, SessionService $sessions): JsonResponse
    {
        $session = $sessions->create($request->user(), $class, $request->validated());

        return SessionResource::make($session)->response()->setStatusCode(201);
    }

    public function index(ListSessionsRequest $request, TuitionClass $class): JsonResponse
    {
        $query = ClassSession::query()->with('tuitionClass')->where('class_id', $class->getKey());

        if ($request->filled('from')) {
            $query->where('session_date', '>=', $request->validated('from'));
        }

        if ($request->filled('to')) {
            $query->where('session_date', '<=', $request->validated('to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $page = $query
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return PaginatedResponse::make($page, fn (ClassSession $session) => SessionResource::make($session)->resolve());
    }

    public function cancel(CancelSessionRequest $request, ClassSession $session, SessionService $sessions): JsonResponse
    {
        return SessionResource::make($sessions->cancel($request->user(), $session))->response();
    }
}
