<?php

namespace Tests\Feature\Http\Concerns;

use App\Audit\AuditContext;
use App\Models\GuardianInvitation;
use App\Models\Institute;
use App\Models\StudentGuardian;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\GuardianInvitationService;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

/**
 * Fictional students, guardians, invitations and links for the E2 tests (contract v0.7 §9).
 * Every test gets a throwaway GUARDIAN_INVITE_HMAC_KEY; no real key is ever needed.
 */
trait GuardianFixtures
{
    use ApiActors;

    /** @var list<array{level: string, message: string, context: array<mixed>}> */
    protected array $logged = [];

    /**
     * Called automatically by Laravel's test lifecycle.
     */
    protected function setUpGuardianFixtures(): void
    {
        config(['guardian_invites.hmac_key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->seedLookups();
    }

    protected function makeStudent(Institute $institute): StudentProfile
    {
        return StudentProfile::query()->findOrFail($this->insertStudentProfile($institute->getKey()));
    }

    /**
     * A parent account with a verified phone, as after an OTP login.
     */
    protected function makeParent(array $overrides = []): User
    {
        return $this->makeUser(['name' => 'Fictional Parent', ...$overrides]);
    }

    /**
     * Creates a pending invitation through the service, as the inviter.
     *
     * @return array{0: GuardianInvitation, 1: string} the invitation and its XXXX-XXXX code
     */
    protected function issueInvitation(User $inviter, StudentProfile $student, string $phone, string $relationship = 'mother'): array
    {
        return $this->asActor($inviter, fn () => app(GuardianInvitationService::class)->create($inviter, $student, $phone, $relationship));
    }

    /**
     * An active link made through the real invite and accept flow.
     */
    protected function linkParent(User $inviter, StudentProfile $student, User $parent, string $relationship = 'mother'): StudentGuardian
    {
        [, $code] = $this->issueInvitation($inviter, $student, $parent->phone, $relationship);

        return $this->asActor($parent, fn () => app(GuardianInvitationService::class)->accept($parent, $code));
    }

    protected function invitationsUrl(StudentProfile $student): string
    {
        return "/api/v1/students/{$student->uuid}/guardian-invitations";
    }

    protected function linkUrl(StudentProfile $student, User|string $guardian): string
    {
        $uuid = $guardian instanceof User ? $guardian->uuid : $guardian;

        return "/api/v1/students/{$student->uuid}/guardian-links/{$uuid}";
    }

    protected function acceptCode(mixed $code): TestResponse
    {
        return $this->spa('POST', '/api/v1/guardian-invitations/accept', ['code' => $code]);
    }

    protected function assertGenericInvalidCode(TestResponse $response): void
    {
        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The invitation code is invalid or has expired.',
            'errors' => ['code' => ['The invitation code is invalid or has expired.']],
        ]);
    }

    /**
     * Records every log message from here on, so a test can prove codes and phones never reach it.
     */
    protected function captureLogs(): void
    {
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = ['level' => $event->level, 'message' => $event->message, 'context' => $event->context];
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function asActor(User $actor, callable $callback): mixed
    {
        $this->useAuditContext($actor->getKey());

        try {
            return $callback();
        } finally {
            $this->app->forgetInstance(AuditContext::class);
        }
    }
}
