<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/SPRINT_SCHEMA.md G5.1 (table classes; `class` is a PHP reserved word, D-024 Q53). The
 * institute, teacher and lookups are set by services, never from request input.
 */
#[Table('classes')]
#[Hidden(['id', 'institute_id', 'teacher_id', 'subject_id', 'grade_id', 'stream_id', 'exam_target_id', 'medium_id', 'syllabus_id'])]
class TuitionClass extends Model
{
    use HasPublicUuid, SerializesUtcDates;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const MODES = ['in_person', 'online', 'hybrid'];

    protected function casts(): array
    {
        return [
            'max_students' => 'integer',
            'starts_on' => 'immutable_date:Y-m-d',
            'ends_on' => 'immutable_date:Y-m-d',
            'public_listing' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Institute, $this>
     */
    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * @return BelongsTo<TeacherProfile, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class, 'teacher_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * @return BelongsTo<Stream, $this>
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * @return BelongsTo<ExamTarget, $this>
     */
    public function examTarget(): BelongsTo
    {
        return $this->belongsTo(ExamTarget::class);
    }

    /**
     * @return BelongsTo<Medium, $this>
     */
    public function medium(): BelongsTo
    {
        return $this->belongsTo(Medium::class);
    }

    /**
     * @return BelongsTo<Syllabus, $this>
     */
    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(Syllabus::class);
    }

    /**
     * @return HasMany<ClassFeeVersion, $this>
     */
    public function feeVersions(): HasMany
    {
        return $this->hasMany(ClassFeeVersion::class, 'class_id');
    }

    /**
     * @return HasMany<ClassSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'class_id');
    }

    /**
     * @return HasMany<ClassEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class, 'class_id');
    }
}
