<?php

namespace App\Http\Requests\Guardians;

use App\Authorization\TenantAccess;
use App\Http\Requests\Concerns\ValidatesPagination;
use App\Models\StudentProfile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/parents/me/children/{uuid}/attendance?from=&to=&class_uuid= (contract v0.9 §10.5).
 * Only a guardian with an **active** link to the student; anyone else gets 404, re-checked on
 * every request. The date window is checked by the controller (default last 30 days, at most 366).
 */
class ListChildAttendanceRequest extends FormRequest
{
    use ValidatesPagination;

    public function authorize(TenantAccess $access): Response
    {
        /** @var StudentProfile $student */
        $student = $this->route('student');

        return $access->hasActiveGuardianLink($this->user(), $student) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'string', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'string', 'date_format:Y-m-d'],
            'class_uuid' => ['sometimes', 'string', 'uuid'],
            ...$this->paginationRules(),
        ];
    }
}
