<?php

use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\InstituteController;
use App\Http\Controllers\Api\V1\InstituteTeacherController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MyInstitutesController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\TeacherProfileController;
use App\Http\Middleware\BindAuditContext;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RequireStatefulFrontend;
use Illuminate\Support\Facades\Route;

/*
| /api/v1 — contract v0.6. Session (cookie) auth only: bearer tokens are disabled (D-019).
*/

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('otp/request', [OtpController::class, 'request'])
            ->middleware('throttle:otp-request');

        Route::post('otp/verify', [OtpController::class, 'verify'])
            ->middleware([RequireStatefulFrontend::class, 'throttle:otp-verify']);

        // auth:sanctum runs first, so an unauthenticated request (including bearer-only) gets 401.
        Route::post('logout', [SessionController::class, 'logout'])
            ->middleware(['auth:sanctum', RequireStatefulFrontend::class]);

        Route::get('session', [SessionController::class, 'show'])
            ->middleware(['auth:sanctum', RequireStatefulFrontend::class, EnsureActiveUser::class]);
    });

    // Public reference data (§4.6). No auth and no internal IDs. 60 requests per minute per IP.
    Route::get('lookups', LookupController::class)->middleware('throttle:api-public');

    // Authenticated endpoints (D-020). Order matters: 401 (unauthenticated or bearer-only), then
    // 403 for a suspended account, and only then is the audit actor bound for this request.
    // Route-model binding runs after these (Laravel's middleware priority), so a malformed UUID is
    // a 404 without a query, and only for an authenticated, active caller.
    // 60 requests per minute per user (the throttle runs after auth:sanctum by middleware priority).
    Route::middleware(['auth:sanctum', RequireStatefulFrontend::class, EnsureActiveUser::class, BindAuditContext::class, 'throttle:api-authenticated'])
        ->group(function () {
            Route::patch('me', [MeController::class, 'update']);
            Route::get('me/institutes', [MyInstitutesController::class, 'index']);

            Route::post('institutes', [InstituteController::class, 'store']);
            Route::get('institutes', [InstituteController::class, 'index']);
            Route::get('institutes/{institute}', [InstituteController::class, 'show']);
            Route::patch('institutes/{institute}', [InstituteController::class, 'update']);

            // E1b (§6.1–§6.3, §8.1–§8.2). §6.4 (my classes) needs the classes tables and is deferred.
            Route::post('me/teacher-profile', [TeacherProfileController::class, 'upsert']);
            Route::post('institutes/{institute}/teacher-assignments', [InstituteTeacherController::class, 'store']);
            Route::get('institutes/{institute}/teachers', [InstituteTeacherController::class, 'index']);
            Route::post('institutes/{institute}/students', [StudentController::class, 'store']);
            Route::get('institutes/{institute}/students', [StudentController::class, 'index']);
        });
});
