<?php

namespace App\Http\Requests\Guardians;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Http\Requests\Concerns\ValidatesPagination;
use App\Models\StudentProfile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * GET /api/v1/students/{uuid}/guardians?page=&per_page= (contract v0.10 §9.6, E2b;
 * D-028). Authorization first; then only page and per_page are accepted (§1.3, §1.6).
 */
class ListStudentGuardiansRequest extends FormRequest
{
    use RejectsUnknownFields;
    use ValidatesPagination;

    public function authorize(): Response
    {
        /** @var StudentProfile $student */
        $student = $this->route('student');

        return Gate::inspect('viewGuardians', $student);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->paginationRules();
    }
}
