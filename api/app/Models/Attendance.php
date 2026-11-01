<?php

namespace App\Models;

use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/SPRINT_SCHEMA.md G6.1. One mark per session and enrolment; the session and the enrolment
 * always share class_id (composite FKs). marked_by_user_id comes from the session, never the
 * request. No public UUID: attendance is addressed by session and enrolment (contract §10).
 */
#[Hidden(['id', 'session_id', 'enrollment_id', 'class_id', 'marked_by_user_id'])]
class Attendance extends Model
{
    use SerializesUtcDates;

    public const STATUSES = ['present', 'absent', 'late', 'excused'];

    public const METHOD_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'marked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<ClassSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<ClassEnrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class, 'enrollment_id');
    }

    /**
     * @return BelongsTo<TuitionClass, $this>
     */
    public function tuitionClass(): BelongsTo
    {
        return $this->belongsTo(TuitionClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_user_id');
    }
}
