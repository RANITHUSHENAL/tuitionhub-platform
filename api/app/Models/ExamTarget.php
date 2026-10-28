<?php

namespace App\Models;

use App\Models\Concerns\SerializesUtcDates;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/SPRINT_SCHEMA.md G1. Seeded taxonomy, read-only, referenced by code in the API.
 */
#[Table('exam_targets')]
#[Hidden(['id'])]
class ExamTarget extends Model
{
    use SerializesUtcDates;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'approximate_exam_date' => 'immutable_date:Y-m-d',
        ];
    }
}
