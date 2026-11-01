<?php

namespace App\Http\Requests\Sessions;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\ClassSession;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * POST /api/v1/sessions/{uuid}/cancel (contract v0.9 §10.6). No body: any field is a 422, checked
 * only after authorization.
 */
class CancelSessionRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var ClassSession $session */
        $session = $this->route('session');

        return Gate::inspect('cancel', $session);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
