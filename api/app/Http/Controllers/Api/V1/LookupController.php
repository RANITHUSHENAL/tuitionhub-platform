<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExamTarget;
use App\Models\Grade;
use App\Models\Medium;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Syllabus;
use App\Support\District;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/lookups (contract v0.5 §4.6; FD-8): public reference data for the SPA's forms and
 * filters. Codes only, never internal IDs. Read-only; values come from LookupSeeder.
 */
class LookupController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'subjects' => Subject::query()->orderBy('name')->orderBy('code')
                ->get(['code', 'name', 'name_si', 'name_ta', 'category'])
                ->map(fn (Subject $subject) => [
                    'code' => $subject->code,
                    'name' => $subject->name,
                    'name_si' => $subject->name_si,
                    'name_ta' => $subject->name_ta,
                    'category' => $subject->category,
                ])->all(),
            'grades' => Grade::query()->orderBy('sort_order')->orderBy('code')
                ->get(['code', 'name', 'level', 'sort_order'])
                ->map(fn (Grade $grade) => [
                    'code' => $grade->code,
                    'name' => $grade->name,
                    'level' => $grade->level,
                    'sort_order' => $grade->sort_order,
                ])->all(),
            'streams' => Stream::query()->orderBy('name')->orderBy('code')
                ->get(['code', 'name', 'level'])
                ->map(fn (Stream $stream) => [
                    'code' => $stream->code,
                    'name' => $stream->name,
                    'level' => $stream->level,
                ])->all(),
            'exam_targets' => ExamTarget::query()->orderBy('year')->orderBy('exam')->orderBy('code')
                ->get(['code', 'name', 'exam', 'year', 'approximate_exam_date'])
                ->map(fn (ExamTarget $target) => [
                    'code' => $target->code,
                    'name' => $target->name,
                    'exam' => $target->exam,
                    'year' => $target->year,
                    'approximate_exam_date' => $target->approximate_exam_date?->format('Y-m-d'),
                ])->all(),
            'mediums' => Medium::query()->orderBy('name')->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Medium $medium) => ['code' => $medium->code, 'name' => $medium->name])->all(),
            'syllabi' => Syllabus::query()->orderBy('name')->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Syllabus $syllabus) => ['code' => $syllabus->code, 'name' => $syllabus->name])->all(),
            'districts' => District::ALL,
        ]]);
    }
}
