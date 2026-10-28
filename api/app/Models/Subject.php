<?php

namespace App\Models;

use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/SPRINT_SCHEMA.md G1. Seeded taxonomy, read-only, referenced by code in the API.
 */
#[Table('subjects')]
#[Hidden(['id'])]
class Subject extends Model
{
    use SerializesUtcDates;
}
