<?php

namespace App\Http\Requests\Sessions;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\TuitionClass;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * POST /api/v1/classes/{uuid}/sessions (contract v0.9 §10.1). Times are HH:MM (24-hour,
 * Asia/Colombo, whole minutes). The class dates and end > start are checked by SessionService.
 */
class StoreSessionRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var TuitionClass $class */
        $class = $this->route('class');

        return Gate::inspect('manageSessions', $class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_date' => ['required', 'string', 'date_format:Y-m-d'],
            'start_time' => ['required', 'string', 'date_format:H:i'],
            'end_time' => ['required', 'string', 'date_format:H:i'],
        ];
    }
}
