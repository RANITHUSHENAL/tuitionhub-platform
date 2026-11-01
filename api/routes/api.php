<?php

use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\ChildAttendanceController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\ClassEnrollmentController;
use App\Http\Controllers\Api\V1\ClassSessionController;
use App\Http\Controllers\Api\V1\GuardianInvitationController;
use App\Http\Controllers\Api\V1\GuardianLinkController;
use App\Http\Controllers\Api\V1\InstituteController;
use App\Http\Controllers\Api\V1\InstituteTeacherController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MyInstitutesController;
use App\Http\Controllers\Api\V1\MyTeacherClassesController;
use App\Http\Controllers\Api\V1\ParentChildrenController;
use App\Http\Controllers\Api\V1\PublicClassController;
use App\Http\Controllers\Api\V1\SessionAttendanceController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentGuardianController;
use App\Http\Controllers\Api\V1\TeacherProfileController;
use App\Http\Middleware\BindAuditContext;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RequireStatefulFrontend;
use Illuminate\Support\Facades\Route;

/*
| /api/v1 — contract v0.10 (draft). Session (cookie) auth only: bearer tokens are disabled (D-019).
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

    // E3a public catalogue (§7.5) and class page (§7.3), 60 per minute per IP. The class page reads
    // an optional SPA session itself: authorised callers get the full Class, others PublicClass or 404.
    Route::get('public/classes', PublicClassController::class)->middleware('throttle:api-public');
    Route::get('classes/{class}', [ClassController::class, 'show'])->middleware('throttle:api-public');

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

            // E2 (§9.1–§9.5). Accepting is also limited to 5 attempts per 15 minutes per user (E2-7).
            Route::post('students/{student}/guardian-invitations', [GuardianInvitationController::class, 'store']);
            Route::post('guardian-invitations/accept', [GuardianInvitationController::class, 'accept'])
                ->middleware('throttle:guardian-invite-accept');
            Route::post('guardian-invitations/{invitation}/revoke', [GuardianInvitationController::class, 'revoke']);
            Route::delete('students/{student}/guardian-links/{guardianUuid}', [GuardianLinkController::class, 'destroy']);
            Route::get('parents/me/children', ParentChildrenController::class);

            // E3a (§6.4, §7.1, §7.2, §7.4, §8.3–§8.5).
            Route::get('me/teacher/classes', MyTeacherClassesController::class);
            Route::post('institutes/{institute}/classes', [ClassController::class, 'store']);
            Route::get('institutes/{institute}/classes', [ClassController::class, 'index']);
            Route::patch('classes/{class}', [ClassController::class, 'update']);
            Route::post('classes/{class}/enrollments', [ClassEnrollmentController::class, 'store']);
            Route::get('classes/{class}/enrollments', [ClassEnrollmentController::class, 'index']);
            Route::post('enrollments/{enrollment}/withdraw', [ClassEnrollmentController::class, 'withdraw']);

            // E3b (§10.1–§10.6).
            Route::post('classes/{class}/sessions', [ClassSessionController::class, 'store']);
            Route::get('classes/{class}/sessions', [ClassSessionController::class, 'index']);
            Route::post('sessions/{session}/cancel', [ClassSessionController::class, 'cancel']);
            Route::post('sessions/{session}/attendance', [SessionAttendanceController::class, 'store']);
            Route::get('sessions/{session}/attendance', [SessionAttendanceController::class, 'show']);
            Route::get('parents/me/children/{student}/attendance', ChildAttendanceController::class);

            // E2b (§6.5, §8.6, §9.6, §9.7; D-028). Read-only: no writes, no audit rows.
            Route::get('me/teacher-profile', [TeacherProfileController::class, 'show']);
            Route::get('students/{student}', [StudentController::class, 'show']);
            Route::get('students/{student}/guardians', [StudentGuardianController::class, 'guardians']);
            Route::get('students/{student}/guardian-invitations', [StudentGuardianController::class, 'invitations']);
        });
});
