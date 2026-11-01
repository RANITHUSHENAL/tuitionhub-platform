<?php

namespace App\Http\Requests\Classes;

use App\Models\ClassFeeVersion;
use App\Models\TuitionClass;
use App\Rules\LookupCode;
use App\Rules\PlainText;
use Illuminate\Validation\Rule;

/**
 * Field rules shared by class create (§7.1) and update (§7.4), contract v0.8. Lookup codes match
 * exactly (D-021); money is a string with exactly two decimals (§1.5), never a JSON number.
 */
final class ClassFieldRules
{
    public const MAX_STUDENTS = 10000;

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $creating): array
    {
        $presence = $creating ? 'required' : 'sometimes';
        $statuses = $creating
            ? [TuitionClass::STATUS_DRAFT, TuitionClass::STATUS_ACTIVE]
            : [TuitionClass::STATUS_DRAFT, TuitionClass::STATUS_ACTIVE, TuitionClass::STATUS_ARCHIVED];

        return [
            'title' => [$presence, 'string', 'max:255', new PlainText],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000', new PlainText(multiline: true)],
            // The active assignment at this institute is checked by ClassService under the lock.
            'teacher_uuid' => ['bail', $presence, 'string', 'uuid', Rule::exists('teacher_profiles', 'uuid')],
            'subject_code' => [$presence, 'string', new LookupCode('subjects')],
            'grade_code' => [$presence, 'string', new LookupCode('grades')],
            'stream_code' => ['sometimes', 'nullable', 'string', new LookupCode('streams')],
            'exam_target_code' => [$presence, 'string', new LookupCode('exam_targets')],
            'medium_code' => [$presence, 'string', new LookupCode('mediums')],
            'syllabus_code' => [$presence, 'string', new LookupCode('syllabi')],
            'mode' => [$presence, 'string', Rule::in(TuitionClass::MODES)],
            'max_students' => [$presence, 'integer', 'min:1', 'max:'.self::MAX_STUDENTS],
            'starts_on' => [$presence, 'string', 'date_format:Y-m-d'],
            'ends_on' => ['sometimes', 'nullable', 'string', 'date_format:Y-m-d'],
            'public_listing' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in($statuses)],
            'fee' => [$presence, 'array:amount,currency,billing_cycle,effective_from'],
            'fee.amount' => ['required_with:fee', 'string', 'regex:/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/', 'not_in:0.00'],
            'fee.currency' => ['required_with:fee', 'string', 'in:LKR'],
            'fee.billing_cycle' => ['required_with:fee', 'string', Rule::in(ClassFeeVersion::BILLING_CYCLES)],
            'fee.effective_from' => ['required_with:fee', 'string', 'date_format:Y-m-d'],
        ];
    }
}
