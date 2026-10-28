<?php

use App\Exceptions\DomainRuleException;
use App\Http\Middleware\NoStoreResponses;
use App\Otp\Exceptions\AccountSuspendedException;
use App\Otp\Exceptions\InvalidOtpException;
use App\Otp\Exceptions\OtpThrottledException;
use App\Otp\Exceptions\OtpUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA cookie/session auth for requests from the stateful SPA origin (D-019).
        // Bearer tokens are disabled in AppServiceProvider.
        $middleware->statefulApi();

        // Global, so it also covers framework-rendered errors and unmatched /api routes.
        $middleware->append(NoStoreResponses::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Contract v0.4 §1.3–§1.4. CSRF mismatches keep Laravel's 419 ("CSRF token mismatch.").
        $exceptions->render(fn (DomainRuleException $exception) => $exception->toResponse());

        $exceptions->render(fn (InvalidOtpException $exception) => response()->json([
            'message' => $exception->getMessage(),
            'errors' => ['code' => [$exception->getMessage()]],
        ], 422));

        $exceptions->render(fn (OtpThrottledException $exception) => response()->json(
            ['message' => $exception->getMessage()],
            429,
            ['Retry-After' => (string) $exception->retryAfterSeconds],
        ));

        $exceptions->render(fn (AccountSuspendedException $exception) => response()->json(
            ['message' => $exception->getMessage()],
            403,
        ));

        $exceptions->render(fn (OtpUnavailableException $exception) => response()->json(
            ['message' => $exception->getMessage()],
            503,
        ));

        // Never reveal model class names or identifiers in a 404 (contract §1.4).
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        });
    })->create();
