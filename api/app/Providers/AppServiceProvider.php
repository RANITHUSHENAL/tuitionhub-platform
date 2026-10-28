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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One audit context per request; console, seeders and tests default to a system context.
        $this->app->scoped(AuditContext::class, fn () => AuditContext::system());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
