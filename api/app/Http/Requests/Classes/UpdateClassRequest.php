<?php

namespace App\Http\Requests\Classes;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\TuitionClass;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * PATCH /api/v1/classes/{uuid} (contract v0.8 §7.4). Every field is optional; authorization runs
 * before validation.
 */
class UpdateClassRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): Response
    {
        /** @var TuitionClass $class */
        $class = $this->route('class');

        return Gate::inspect('update', $class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ClassFieldRules::rules(creating: false);
    }
}
