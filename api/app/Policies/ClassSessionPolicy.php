<?php

namespace App\Policies;

use App\Exceptions\DomainRuleException;
use App\Models\ClassSession;
use App\Models\Institute;
use App\Models\TuitionClass;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Session endpoints (contract v0.9 §10.3, §10.4, §10.6; D-027): the ClassPolicy rules applied to
 * the session's class, so visibility comes first (404, then 403).
 */
class ClassSessionPolicy
{
    public function __construct(private readonly ClassPolicy $classes) {}

    /**
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    public function cancel(User $user, ClassSession $session, ?Institute $institute = null): Response
    {
        return $this->classes->manageSessions($user, $this->classOf($session), $institute);
    }

    /**
     * @throws DomainRuleException INSTITUTE_SUSPENDED
     */
    public function markAttendance(User $user, ClassSession $session, ?Institute $institute = null): Response
    {
        return $this->classes->markAttendance($user, $this->classOf($session), $institute);
    }

    public function viewAttendance(User $user, ClassSession $session): Response
    {
        return $this->classes->viewAttendance($user, $this->classOf($session));
    }

    private function classOf(ClassSession $session): TuitionClass
    {
        return TuitionClass::query()->findOrFail($session->class_id);
    }
}
