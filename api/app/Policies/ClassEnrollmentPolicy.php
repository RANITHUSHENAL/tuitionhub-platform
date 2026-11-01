<?php

namespace App\Policies;

use App\Exceptions\DomainRuleException;
use App\Models\ClassEnrollment;
use App\Models\Institute;
use App\Models\TuitionClass;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * POST /enrollments/{uuid}/withdraw (contract v0.8 §8.5; D-026): the same rule as enrolling (§8.3),
 * applied to the enrolment's class and institute, so visibility comes first: a caller who cannot
 * see the class gets 404, one who can see it but may not act gets 403.
 */
class ClassEnrollmentPolicy
{
    public function __construct(private readonly ClassPolicy $classes) {}

    /**
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    public function withdraw(User $user, ClassEnrollment $enrollment, ?Institute $institute = null): Response
    {
        $class = TuitionClass::query()->findOrFail($enrollment->class_id);

        return $this->classes->enroll($user, $class, $institute);
    }
}
