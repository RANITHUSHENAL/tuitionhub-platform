<?php

namespace App\Http\Requests\Enrollments;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\ClassEnrollment;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * POST /api/v1/enrollments/{uuid}/withdraw (contract v0.8 §8.5).
 */
class WithdrawEnrollmentRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var ClassEnrollment $enrollment */
        $enrollment = $this->route('enrollment');

        return Gate::inspect('withdraw', $enrollment);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Today (Asia/Colombo) or earlier; EnrollmentService clamps it to enrolled_on (D-024).
            'withdrawn_on' => ['required', 'string', 'date_format:Y-m-d'],
        ];
    }
}
