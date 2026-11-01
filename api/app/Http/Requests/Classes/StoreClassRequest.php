<?php

namespace App\Http\Requests\Classes;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\Institute;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * POST /api/v1/institutes/{uuid}/classes (contract v0.8 §7.1). Authorization runs before
 * validation. The institute, IDs and creator are server-owned.
 */
class StoreClassRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var Institute $institute */
        $institute = $this->route('institute');

        return Gate::inspect('createClass', $institute);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ClassFieldRules::rules(creating: true);
    }
}
