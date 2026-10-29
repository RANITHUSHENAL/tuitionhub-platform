<?php

namespace App\Http\Requests\Teachers;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use App\Models\TeacherProfile;
use App\Rules\PersonName;
use App\Rules\PlainText;
use App\Support\PersonName as PersonNameSupport;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/me/teacher-profile (contract v0.6 §6.1). display_name is required only when the
 * profile is created; on later calls every field is optional (omitted = unchanged). headline and
 * bio may be cleared with an explicit null. user_id, uuid, is_independent and public_visibility are
 * rejected as unknown fields.
 */
class UpsertTeacherProfileRequest extends FormRequest
{
    use RejectsUnknownFields;

    public const HEADLINE_MAX = 150;

    public const BIO_MAX = 2000;

    public const YEARS_MAX = 70;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hasProfile = $this->user() !== null
            && TeacherProfile::query()->where('user_id', $this->user()->getKey())->exists();

        return [
            'display_name' => [$hasProfile ? 'sometimes' : 'required', 'string', new PersonName],
            'headline' => ['sometimes', 'nullable', 'string', 'max:'.self::HEADLINE_MAX, new PlainText],
            'bio' => ['sometimes', 'nullable', 'string', 'max:'.self::BIO_MAX, new PlainText(multiline: true)],
            'years_experience' => ['sometimes', 'integer', 'min:0', 'max:'.self::YEARS_MAX],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('display_name'))) {
            $this->merge(['display_name' => PersonNameSupport::normalize($this->input('display_name'))]);
        }
    }
}
