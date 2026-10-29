<?php

namespace Tests\Feature\Http;

use App\Support\District;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\ApiActors;
use Tests\TestCase;

/**
 * GET /api/v1/lookups (contract v0.5 §4.6; FD-8): public, codes only.
 */
class LookupsTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    public function test_it_is_public_and_returns_the_seeded_codes_and_the_25_districts(): void
    {
        $this->seed(LookupSeeder::class);

        $response = $this->nonStateful('GET', '/api/v1/lookups')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(
            ['subjects', 'grades', 'streams', 'exam_targets', 'mediums', 'syllabi', 'districts'],
            array_keys($response->json('data')),
        );
        $this->assertSame(District::ALL, $response->json('data.districts'));
        $this->assertCount(25, $response->json('data.districts'));

        $this->assertCount(15, $response->json('data.subjects'));
        $this->assertEqualsCanonicalizing(array_column(LookupSeeder::SUBJECTS, 'code'), array_column($response->json('data.subjects'), 'code'));
        $this->assertSame(array_column(LookupSeeder::GRADES, 'code'), array_column($response->json('data.grades'), 'code'));
        $this->assertEqualsCanonicalizing(array_column(LookupSeeder::STREAMS, 'code'), array_column($response->json('data.streams'), 'code'));
        // Ordered by year, then exam (al before ol), then code.
        $this->assertSame(['AL_2026', 'OL_2026', 'AL_2027', 'OL_2027', 'AL_2028', 'OL_2028'], array_column($response->json('data.exam_targets'), 'code'));
        $this->assertEqualsCanonicalizing(['sin', 'tam', 'eng'], array_column($response->json('data.mediums'), 'code'));
        $this->assertEqualsCanonicalizing(['local', 'edexcel', 'cambridge'], array_column($response->json('data.syllabi'), 'code'));

        $this->assertSame(
            ['code' => 'AL2', 'name' => 'A/L Year 2 (Grade 13)', 'level' => 'al', 'sort_order' => 13],
            $response->json('data.grades.7'),
        );
        $this->assertSame(
            ['code' => 'AL_2027', 'name' => 'A/L 2027', 'exam' => 'al', 'year' => 2027, 'approximate_exam_date' => null],
            $response->json('data.exam_targets.2'),
        );
        $this->assertSame(['code', 'name', 'name_si', 'name_ta', 'category'], array_keys($response->json('data.subjects.0')));
        $this->assertNoInternalIds($response);
    }

    public function test_it_also_works_for_the_spa_without_a_session(): void
    {
        $this->spa('GET', '/api/v1/lookups')->assertOk()
            ->assertJsonPath('data.subjects', [])
            ->assertJsonPath('data.districts.0', 'Ampara');
    }
}
