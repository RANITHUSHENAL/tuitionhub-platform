<?php

namespace App\Http\Requests\Guardians;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Http\Requests\Concerns\ValidatesPagination;
use App\Models\GuardianInvitation;
use App\Models\StudentProfile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/students/{uuid}/guardian-invitations?status=&page=&per_page= (contract v0.10 §9.7,
 * E2b; D-028). `status` filters on the **effective** status (lazy expiry, §9.1).
 * Authorization first; then only these query fields are accepted (§1.3, §1.6).
 */
class ListStudentGuardianInvitationsRequest extends FormRequest
{
    use RejectsUnknownFields;
    use ValidatesPagination;

    public const STATUSES = [
        GuardianInvitation::STATUS_PENDING,
        GuardianInvitation::STATUS_ACCEPTED,
        GuardianInvitation::STATUS_REVOKED,
        GuardianInvitation::STATUS_EXPIRED,
    ];

    public function authorize(): Response
    {
        /** @var StudentProfile $student */
        $student = $this->route('student');

        return Gate::inspect('viewGuardianInvitations', $student);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(self::STATUSES)],
            ...$this->paginationRules(),
        ];
    }
}
