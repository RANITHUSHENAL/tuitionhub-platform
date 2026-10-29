<?php

namespace App\Http\Requests\Teachers;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\Institute;
use App\Support\LocalClock;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/institutes/{uuid}/teacher-assignments (contract v0.6 §6.2). Authorization runs
 * before validation. A malformed teacher_uuid fails before any query; an unknown one is a 422 on
 * teacher_uuid (the teacher is identified by the UUID the teacher shared, not looked up by route).
 */
class StoreTeacherAssignmentRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var Institute $institute */
        $institute = $this->route('institute');

        return Gate::inspect('assignTeacher', $institute);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'teacher_uuid' => ['bail', 'required', 'string', 'uuid', Rule::exists('teacher_profiles', 'uuid')],
            // Today (Asia/Colombo) or earlier: an assignment grants access as soon as it exists, so a
            // future start date would grant access early (D-021). Backdated assignments are valid.
            'joined_on' => ['required', 'string', 'date_format:Y-m-d', 'before_or_equal:'.LocalClock::today()],
        ];
    }
}
