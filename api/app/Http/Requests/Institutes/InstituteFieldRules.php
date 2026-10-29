<?php

namespace App\Http\Requests\Institutes;

use App\Rules\PlainText;

/**
 * Field rules shared by institute create (§5.1) and update (§5.5), contract v0.5.
 */
final class InstituteFieldRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'max:255', new PlainText],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000', new PlainText(multiline: true)],
            // Canonicalised by InstituteService (App\Support\District); unknown → 422 errors.district.
            'district' => [$presence, 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100', new PlainText],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'regex:/^[+]947[0-9]{8}$/'],
            'contact_email' => ['sometimes', 'nullable', 'string', 'max:255', 'email:rfc'],
            'business_reg_no' => ['sometimes', 'nullable', 'string', 'max:100', new PlainText],
        ];
    }
}
