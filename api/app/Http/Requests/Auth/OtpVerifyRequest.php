<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/otp/verify (contract §4.2).
 */
class OtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'uuid'],
            'phone' => ['required', 'string', 'regex:/^[+]947[0-9]{8}$/'],
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ];
    }
}
