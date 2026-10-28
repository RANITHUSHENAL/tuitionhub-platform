<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/SPRINT_SCHEMA.md G2.3. The OTP service is a later task (Q19); every column is server-owned.
 */
#[Hidden(['id', 'code_hash', 'request_ip_hash'])]
class OtpChallenge extends Model
{
    use HasPublicUuid, SerializesUtcDates;

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'last_sent_at' => 'immutable_datetime',
        ];
    }
}
