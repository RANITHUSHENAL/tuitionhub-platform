<?php

namespace App\Http\Requests\Students;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\StudentProfile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * GET /api/v1/students/{uuid} (contract v0.10 §8.6, E2b; D-028). Authorization runs
 * first; any query field is then a 422 (§1.6).
 */
class ShowStudentRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var StudentProfile $student */
        $student = $this->route('student');

        return Gate::inspect('view', $student);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
