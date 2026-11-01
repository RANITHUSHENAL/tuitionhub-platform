<?php

use App\Exceptions\DomainRuleException;
use App\Exceptions\ServiceBusyException;
use App\Guardians\Exceptions\GuardianInviteUnavailableException;
use App\Guardians\Exceptions\InvalidInvitationCodeException;
use App\Http\Middleware\NoStoreResponses;
use App\Otp\Exceptions\AccountSuspendedException;
use App\Otp\Exceptions\InvalidOtpException;
use App\Otp\Exceptions\OtpThrottledException;
use App\Otp\Exceptions\OtpUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        // Expected outcomes, not faults (D-022): never written to the log. Wrapped database errors
        // carry SQL and MySQL's duplicate-key values (which can include a full phone number), so
        // business-rule exceptions must not reach the log either.
        $exceptions->dontReport([
            DomainRuleException::class,
            InvalidOtpException::class,
            OtpThrottledException::class,
            AccountSuspendedException::class,
            ServiceBusyException::class,
            InvalidInvitationCodeException::class,
        ]);

        // Contract v0.4 §1.3–§1.4. CSRF mismatches keep Laravel's 419 ("CSRF token mismatch.").
        $exceptions->render(fn (DomainRuleException $exception) => $exception->toResponse());

        // Concurrency retries exhausted or lock wait timed out; nothing was committed (D-022).
        $exceptions->render(fn (ServiceBusyException $exception) => response()->json(
            ['message' => $exception->getMessage()],
            503,
            ['Retry-After' => '1'],
        ));

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

        // Contract v0.7 §9.2: one generic 422 for every unusable invitation code (D-023).
        $exceptions->render(fn (InvalidInvitationCodeException $exception) => response()->json([
            'message' => $exception->getMessage(),
            'errors' => ['code' => [$exception->getMessage()]],
        ], 422));

        // §9.1: GUARDIAN_INVITE_HMAC_KEY missing; fails closed for invitation create and accept only.
        $exceptions->render(fn (GuardianInviteUnavailableException $exception) => response()->json(
            ['message' => $exception->getMessage()],
            503,
        ));

        // Every API 404 has the same body and never names a model class or identifier (contract
        // §1.4). This covers unmatched routes, missing or malformed route-model UUIDs, and policy
        // denyAsNotFound() (Laravel turns that into a plain HttpException, not NotFoundHttpException).
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() === 404 && $request->is('api/*')) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        });
    })->create();
