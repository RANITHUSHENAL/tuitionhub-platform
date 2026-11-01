<?php

namespace Tests\Feature\Http\Classes;

use App\Models\ClassEnrollment;
use App\Models\Institute;
use App\Models\TuitionClass;
use App\Services\EnrollmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * Visibility comes first (D-026): a caller who cannot see a class gets 404 on every class endpoint,
 * reads and writes alike; one who can see it but may not act gets 403. Authorization always runs
 * before validation, so a hidden resource never answers 422 (contract §1.4).
 */
class ClassVisibilityAuthorizationTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    public function test_an_unassigned_teacher_at_the_same_institute_gets_404_everywhere(): void
    {
        [$class, $enrollment] = $this->privateClassWithEnrollment();

        $this->loginAs($this->teacherAt($this->instituteOf($class)));

        $this->assertEveryEndpoint($class, $enrollment, expected: 404);
    }

    public function test_the_assigned_teacher_can_view_but_not_change_enrol_or_withdraw(): void
    {
        [$class, $enrollment] = $this->privateClassWithEnrollment();

        $this->loginAs($this->teacherUserOf($class));

        $this->spa('GET', $this->classUrl($class))->assertOk()->assertJsonPath('data.status', 'active');
        $this->spa('GET', $this->enrollmentsUrl($class))->assertOk();
        $this->spa('PATCH', $this->classUrl($class), ['title' => 'Changed'])->assertForbidden()->assertJsonMissingPath('reason');
        $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $this->makeStudent($this->instituteOf($class))->uuid])->assertForbidden();
        $this->spa('POST', $this->withdrawUrl($enrollment), ['withdrawn_on' => self::TODAY])->assertForbidden();

        $this->assertNothingChanged($class, $enrollment);
    }

    public function test_an_unrelated_user_gets_404_everywhere(): void
    {
        [$class, $enrollment] = $this->privateClassWithEnrollment();

        foreach ([$this->makeUser(), $this->adminOf($this->makeInstitute())] as $user) {
            $this->loginAs($user);
            $this->assertEveryEndpoint($class, $enrollment, expected: 404);
        }
    }

    public function test_a_hidden_enrolment_with_a_future_withdrawal_date_is_404_not_422(): void
    {
        [$class, $enrollment] = $this->privateClassWithEnrollment();
        $outsider = $this->teacherAt($this->instituteOf($class));

        $this->loginAs($outsider);
        $this->spa('POST', $this->withdrawUrl($enrollment), ['withdrawn_on' => '2027-01-01'])->assertNotFound();
        $this->spa('POST', $this->withdrawUrl($enrollment), ['withdrawn_on' => 'not-a-date'])->assertNotFound();

        // The service applies the same order when called directly.
        $this->useAuditContext($outsider->getKey());

        try {
            app(EnrollmentService::class)->withdraw($outsider, $enrollment, '2027-01-01');
            $this->fail('Expected the hidden enrolment to be refused.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        } catch (ValidationException) {
            $this->fail('Validation ran before authorization.');
        }

        $this->assertNothingChanged($class, $enrollment);
    }

    public function test_an_allowed_caller_still_gets_the_422_for_a_future_date(): void
    {
        [$class, $enrollment] = $this->privateClassWithEnrollment();

        $this->loginAs($this->staffOf($this->instituteOf($class)));
        $this->spa('POST', $this->withdrawUrl($enrollment), ['withdrawn_on' => '2027-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('withdrawn_on');

        $this->assertNothingChanged($class, $enrollment);
    }

    private function assertEveryEndpoint(TuitionClass $class, ClassEnrollment $enrollment, int $expected): void
    {
        $student = $this->makeStudent($this->instituteOf($class));

        $responses = [
            'show' => $this->spa('GET', $this->classUrl($class)),
            'roster' => $this->spa('GET', $this->enrollmentsUrl($class)),
            'update' => $this->spa('PATCH', $this->classUrl($class), ['title' => 'Changed']),
            'enrol' => $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => $student->uuid]),
            'withdraw' => $this->spa('POST', $this->withdrawUrl($enrollment), ['withdrawn_on' => self::TODAY]),
            // An invalid body is still the 404: authorization runs before validation.
            'update (invalid body)' => $this->spa('PATCH', $this->classUrl($class), ['mode' => 'remote']),
            'enrol (invalid body)' => $this->spa('POST', $this->enrollmentsUrl($class), ['student_uuid' => 'nope']),
        ];

        foreach ($responses as $endpoint => $response) {
            $this->assertSame($expected, $response->status(), $endpoint);
            $response->assertExactJson(['message' => 'Not found.']);
        }

        $this->assertNothingChanged($class, $enrollment);
    }

    /**
     * An active, private class with one active enrolment.
     *
     * @return array{0: TuitionClass, 1: ClassEnrollment}
     */
    private function privateClassWithEnrollment(): array
    {
        $class = $this->makeClass($this->makeInstitute(), ['title' => 'Private Fictional Class', 'public_listing' => 0]);

        return [$class, $this->enrol($class)];
    }

    private function assertNothingChanged(TuitionClass $class, ClassEnrollment $enrollment): void
    {
        $this->assertSame('Private Fictional Class', $class->fresh()->title);
        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertSame(1, ClassEnrollment::query()->where('class_id', $class->getKey())->count());
    }

    private function instituteOf(TuitionClass $class): Institute
    {
        return Institute::query()->findOrFail($class->institute_id);
    }

    private function withdrawUrl(ClassEnrollment $enrollment): string
    {
        return "/api/v1/enrollments/{$enrollment->uuid}/withdraw";
    }
}
