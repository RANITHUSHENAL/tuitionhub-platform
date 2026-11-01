<?php

namespace App\Http\Requests\Sessions;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Rules\PlainText;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/sessions/{uuid}/attendance (contract v0.9 §10.3): 1–500 records, each enrolment at
 * most once. Whether an enrolment belongs to the class and is active on the session date is
 * checked by AttendanceService under the locks.
 */
class MarkAttendanceRequest extends FormRequest
{
    use RejectsUnknownFields;

    public const MAX_RECORDS = 500;

    public function authorize(): Response
    {
        /** @var ClassSession $session */
        $session = $this->route('session');

        return Gate::inspect('markAttendance', $session);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_RECORDS],
            'records.*' => ['required', 'array:enrollment_uuid,status,method,notes'],
            'records.*.enrollment_uuid' => ['required', 'string', 'uuid', 'distinct'],
            'records.*.status' => ['required', 'string', Rule::in(Attendance::STATUSES)],
            'records.*.method' => ['required', 'string', Rule::in([Attendance::METHOD_MANUAL])],
            'records.*.notes' => ['sometimes', 'nullable', 'string', 'max:500', new PlainText(multiline: true)],
        ];
    }
}
