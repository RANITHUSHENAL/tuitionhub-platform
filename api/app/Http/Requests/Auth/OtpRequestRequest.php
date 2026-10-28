<?php

namespace App\Http\Requests\Auth;

use App\Otp\OtpService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/otp/request (contract §4.1).
 */
class OtpRequestRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^[+]947[0-9]{8}$/'],
            'purpose' => ['required', 'string', 'in:'.OtpService::PURPOSE_LOGIN],
        ];
    }
}
