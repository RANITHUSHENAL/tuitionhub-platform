<?php

namespace App\Providers;

use App\Audit\AuditContext;
use App\Models\AuditLog;
use App\Models\GuardianInvitation;
use App\Models\GuardianProfile;
use App\Models\Institute;
use App\Models\InstituteStaff;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\StudentGuardian;
use App\Models\StudentProfile;
use App\Models\TeacherAssignment;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Models\UserRole;
use App\Otp\FakeOtpDriver;
use App\Otp\LogOtpDriver;
use App\Otp\OtpCodeHasher;
use App\Otp\OtpDriver;
use App\Otp\UnavailableOtpDriver;
use App\Policies\InstitutePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One audit context per request; console, seeders and tests default to a system context.
        $this->app->scoped(AuditContext::class, fn () => AuditContext::system());

        // OTP driver from OTP_DRIVER. Unknown or missing: every OTP operation fails closed (503).
        // There is no real SMS driver in the sprint and never a fallback to another driver.
        $this->app->singleton(OtpDriver::class, fn ($app) => match (config('otp.driver')) {
            'log' => new LogOtpDriver($app),
            'fake' => new FakeOtpDriver($app),
            default => new UnavailableOtpDriver((string) config('otp.driver')),
        });

        // Read per resolution, so a missing OTP_HMAC_KEY fails OTP operations only (503).
        $this->app->bind(OtpCodeHasher::class, fn () => new OtpCodeHasher(config('otp.hmac_key')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Session (cookie) authentication only: Sanctum never reads a bearer token (D-019).
        // Without this, a bearer header would make Sanctum query personal_access_tokens, which
        // deliberately does not exist.
        Sanctum::getAccessTokenFromRequestUsing(fn () => null);

        // Contract v0.4 §4.1–§4.2. Keys hash the phone, so no raw phone is stored in the cache, and
        // limits never depend on whether the phone is registered.
        RateLimiter::for('otp-request', function (Request $request) {
            $phone = hash('sha256', (string) $request->input('phone'));

            return [
                Limit::perMinutes(15, (int) config('otp.limits.request_per_phone_per_15_minutes'))->by('otp-phone-15m|'.$phone),
                Limit::perDay((int) config('otp.limits.request_per_phone_per_day'))->by('otp-phone-24h|'.$phone),
                Limit::perHour((int) config('otp.limits.request_per_ip_per_hour'))->by('otp-ip-1h|'.$request->ip()),
            ];
        });

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinutes(
            10,
            (int) config('otp.limits.verify_per_ip_per_10_minutes'),
        )->by('otp-verify-ip-10m|'.$request->ip()));

        // General API limits (D-020), applied per route group; the OTP routes keep only their own
        // limiters above. Authenticated routes are keyed by user (the throttle runs after
        // auth:sanctum, Laravel's middleware priority), so users behind one IP do not share a limit.
        RateLimiter::for('api-authenticated', fn (Request $request) => Limit::perMinute(60)->by(
            $request->user() !== null ? 'api-user|'.$request->user()->getKey() : 'api-ip|'.$request->ip(),
        ));

        RateLimiter::for('api-public', fn (Request $request) => Limit::perMinute(60)->by('api-public-ip|'.$request->ip()));

        // Explicit rather than discovered, so a renamed class can never silently drop the policy.
        Gate::policy(Institute::class, InstitutePolicy::class);

        // Outside production, mass-assigning a guarded attribute, lazy loading and reading a
        // missing attribute throw instead of failing silently.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Stable audit target aliases (D-015 Q35). Never rename an alias once written.
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'user_role' => UserRole::class,
            'otp_challenge' => OtpChallenge::class,
            'teacher_profile' => TeacherProfile::class,
            'guardian_profile' => GuardianProfile::class,
            'institute' => Institute::class,
            'institute_staff' => InstituteStaff::class,
            'teacher_assignment' => TeacherAssignment::class,
            'student_profile' => StudentProfile::class,
            'guardian_invitation' => GuardianInvitation::class,
            'student_guardian' => StudentGuardian::class,
            'audit_log' => AuditLog::class,
        ]);
    }
}
