<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Presenters\AuthSessionPresenter;
use App\Http\Requests\Auth\OtpRequestRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Otp\OtpDriver;
use App\Otp\OtpService;
use App\Support\Instant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Contract v0.4 §4.1–§4.2. The OTP code never appears in any response.
 */
class OtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly OtpDriver $driver,
    ) {}

    public function request(OtpRequestRequest $request): JsonResponse
    {
        $challenge = $this->otp->request(
            $request->validated('phone'),
            $request->validated('purpose'),
            $request->ip(),
        );

        return response()->json([
            'data' => [
                'challenge_id' => $challenge->uuid,
                'expires_at' => Instant::format($challenge->expires_at),
                'resend_available_at' => Instant::format(
                    $challenge->last_sent_at->addSeconds((int) config('otp.resend_cooldown_seconds')),
                ),
            ],
            'meta' => [
                'otp_driver' => $this->driver->name(),
                // Only the local log driver is a dev mode; it is not real phone verification.
                'dev_mode' => $this->driver->name() === 'log',
            ],
        ], 201);
    }

    public function verify(OtpVerifyRequest $request, AuthSessionPresenter $presenter): JsonResponse
    {
        $user = $this->otp->verify(
            $request->validated('challenge_id'),
            $request->validated('phone'),
            $request->validated('code'),
        );

        Auth::guard('web')->login($user);
        // Prevent session fixation: the pre-login session ID never becomes authenticated.
        $request->session()->regenerate();

        return response()->json(['data' => $presenter->verified($user)]);
    }
}
