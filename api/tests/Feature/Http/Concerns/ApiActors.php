<?php

namespace Tests\Feature\Http\Concerns;

use App\Models\Institute;
use App\Models\Role;
use App\Models\TeacherProfile;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Auth\Concerns\InteractsWithSpa;
use Tests\Feature\Concerns\AppFixtures;

/**
 * Fictional actors for HTTP tests, logged in through the real OTP flow and SPA session (so every
 * request is authenticated only by its cookies, as in InteractsWithSpa).
 */
trait ApiActors
{
    use AppFixtures;
    use InteractsWithSpa;

    /**
     * Called automatically by Laravel's test lifecycle. Tests switch between several actors, so the
     * per-IP OTP request limit (exercised by OtpRequestTest) is raised for these tests only.
     */
    protected function setUpApiActors(): void
    {
        config(['otp.limits.request_per_ip_per_hour' => 1000]);
    }

    /**
     * Moves the clock past the 60-second resend cooldown first, so a test can log the same actor
     * in again after switching to another one.
     */
    protected function loginAs(User $user): void
    {
        $this->travel(61)->seconds();
        $this->loginWithOtp($user->phone)->assertOk();
    }

    protected function superAdmin(): User
    {
        $user = $this->makeUser(['name' => 'Fictional Platform Operator']);
        $this->insertUserRole($user->getKey(), Role::SUPER_ADMIN, null);

        return $user;
    }

    protected function adminOf(Institute $institute): User
    {
        $user = $this->makeUser(['name' => 'Fictional Institute Admin']);
        $this->insertUserRole($user->getKey(), Role::INSTITUTE_ADMIN, $institute->getKey());

        return $user;
    }

    protected function staffOf(Institute $institute, string $position = 'clerk'): User
    {
        $user = $this->makeUser(['name' => 'Fictional Staff Member']);
        $this->insertUserRole($user->getKey(), Role::INSTITUTE_STAFF, $institute->getKey());
        $this->insertStaff($institute->getKey(), $user->getKey(), ['position' => $position]);

        return $user;
    }

    protected function teacherAt(Institute $institute, array $assignment = []): User
    {
        $user = $this->makeUser(['name' => 'Fictional Teacher']);
        $teacherId = $this->insertTeacherProfile(['user_id' => $user->getKey()]);
        $this->insertAssignment($teacherId, $institute->getKey(), $assignment);

        return $user;
    }

    /**
     * A teacher profile with no assignment anywhere.
     */
    protected function teacherProfileFor(?User $user = null): TeacherProfile
    {
        $user ??= $this->makeUser(['name' => 'Fictional Teacher']);

        return TeacherProfile::query()->findOrFail($this->insertTeacherProfile([
            'user_id' => $user->getKey(),
            'display_name' => 'Mr. Fictional Teacher',
        ]));
    }

    protected function seedLookups(): void
    {
        $this->seed(LookupSeeder::class);
    }

    protected function suspend(Institute $institute): void
    {
        DB::table('institutes')->where('id', $institute->getKey())->update(['status' => 'suspended']);
    }

    /**
     * Fails on any `id` or `*_id` key anywhere in a JSON payload (contract §1.1).
     *
     * @param  array<mixed>|TestResponse  $payload
     */
    protected function assertNoInternalIds(array|TestResponse $payload): void
    {
        $payload = $payload instanceof TestResponse ? (array) $payload->json() : $payload;

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $this->assertFalse($key === 'id' || str_ends_with($key, '_id'), "Internal id key [{$key}] exposed.");
            }

            if (is_array($value)) {
                $this->assertNoInternalIds($value);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function auditRows(string $action): array
    {
        return DB::table('audit_logs')->where('action', $action)->orderBy('id')->get()
            ->map(fn (object $row) => [
                'user_id' => $row->user_id,
                'institute_id' => $row->institute_id,
                'auditable_type' => $row->auditable_type,
                'auditable_id' => $row->auditable_id,
                'old_values' => $row->old_values === null ? null : json_decode($row->old_values, true),
                'new_values' => $row->new_values === null ? null : json_decode($row->new_values, true),
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
            ])->all();
    }
}
