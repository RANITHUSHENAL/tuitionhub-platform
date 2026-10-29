<?php

namespace App\Http\Requests\Students;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\Institute;
use App\Rules\LookupCode;
use App\Rules\PersonName;
use App\Support\LocalClock;
use App\Support\PersonName as PersonNameSupport;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * POST /api/v1/institutes/{uuid}/students (contract v0.6 §8.1). Authorization runs before
 * validation. The institute, creator and account come from the server: any ID, institute, creator,
 * NIC or document field is rejected as an unknown field.
 */
class StoreStudentRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var Institute $institute */
        $institute = $this->route('institute');

        return Gate::inspect('createStudent', $institute);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', new PersonName(PersonNameSupport::STUDENT_MAX_LENGTH)],
            'grade_code' => ['required', 'string', new LookupCode('grades')],
            'medium_code' => ['required', 'string', new LookupCode('mediums')],
            'exam_target_code' => ['sometimes', 'nullable', 'string', new LookupCode('exam_targets')],
            // A calendar date in Asia/Colombo (§1.5); never in the future.
            'date_of_birth' => ['sometimes', 'nullable', 'string', 'date_format:Y-m-d', 'before_or_equal:'.LocalClock::today()],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('full_name'))) {
            $this->merge(['full_name' => PersonNameSupport::normalize($this->input('full_name'))]);
        }
    }
}
