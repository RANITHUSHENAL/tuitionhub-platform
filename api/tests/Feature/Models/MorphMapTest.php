<?php

namespace Tests\Feature\Models;

use App\Models\GuardianInvitation;
use App\Models\Institute;
use App\Models\InstituteStaff;
use App\Models\StudentGuardian;
use App\Models\StudentProfile;
use App\Models\TeacherAssignment;
use App\Models\UserRole;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * VP-56: audit targets use stable short aliases, never class names (D-015 Q35).
 */
class MorphMapTest extends TestCase
{
    public function test_audit_targets_use_the_approved_aliases(): void
    {
        $expected = [
            'institute' => Institute::class,
            'user_role' => UserRole::class,
            'institute_staff' => InstituteStaff::class,
            'teacher_assignment' => TeacherAssignment::class,
            'student_profile' => StudentProfile::class,
            'guardian_invitation' => GuardianInvitation::class,
            'student_guardian' => StudentGuardian::class,
        ];

        foreach ($expected as $alias => $class) {
            $this->assertSame($alias, (new $class)->getMorphClass());
        }
    }

    public function test_an_unmapped_model_cannot_be_used_as_a_morph_target(): void
    {
        $unmapped = new class extends Model
        {
            protected $table = 'unmapped_things';
        };

        $this->expectException(ClassMorphViolationException::class);

        $unmapped->getMorphClass();
    }
}
