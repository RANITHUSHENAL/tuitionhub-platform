<?php

namespace App\Http\Requests\Enrollments;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\TuitionClass;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * POST /api/v1/classes/{uuid}/enrollments (contract v0.8 §8.3). A student of another institute,
 * or an unknown one, is a 404 from EnrollmentService (scoped lookup).
 */
class StoreEnrollmentRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var TuitionClass $class */
        $class = $this->route('class');

        return Gate::inspect('enroll', $class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_uuid' => ['required', 'string', 'uuid'],
            // Today (Asia/Colombo) when omitted; may be in the future, within the class dates (D-024).
            'enrolled_on' => ['sometimes', 'string', 'date_format:Y-m-d'],
        ];
    }
}
