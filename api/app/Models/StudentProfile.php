<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/SPRINT_SCHEMA.md G4.1. Institute-managed; the institute, creator, account and lookups are
 * set by services, never from request input. Fictional data only (C6).
 */
#[Fillable(['full_name', 'date_of_birth'])]
#[Hidden(['id', 'managing_institute_id', 'created_by_user_id', 'user_id', 'grade_id', 'medium_id', 'exam_target_id'])]
class StudentProfile extends Model
{
    use HasPublicUuid, SerializesUtcDates;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Institute, $this>
     */
    public function managingInstitute(): BelongsTo
    {
        return $this->belongsTo(Institute::class, 'managing_institute_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * @return BelongsTo<Medium, $this>
     */
    public function medium(): BelongsTo
    {
        return $this->belongsTo(Medium::class);
    }

    /**
     * @return BelongsTo<ExamTarget, $this>
     */
    public function examTarget(): BelongsTo
    {
        return $this->belongsTo(ExamTarget::class);
    }

    /**
     * @return HasMany<GuardianInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(GuardianInvitation::class, 'student_id');
    }

    /**
     * @return HasMany<StudentGuardian, $this>
     */
    public function guardianLinks(): HasMany
    {
        return $this->hasMany(StudentGuardian::class, 'student_id');
    }
}
