<?php

namespace App\Http\Requests\Sessions;

use App\Models\ClassSession;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * GET /api/v1/sessions/{uuid}/attendance (contract v0.9 §10.4).
 */
class ShowSessionAttendanceRequest extends FormRequest
{
    public function authorize(): Response
    {
        /** @var ClassSession $session */
        $session = $this->route('session');

        return Gate::inspect('viewAttendance', $session);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
