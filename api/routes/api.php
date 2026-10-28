<?php

use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RequireStatefulFrontend;
use Illuminate\Support\Facades\Route;

/*
| /api/v1 — contract v0.4. Session (cookie) auth only: bearer tokens are disabled (D-019).
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
});
