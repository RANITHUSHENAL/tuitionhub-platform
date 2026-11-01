<?php

namespace Tests\Feature\Http\Classes;

use App\Support\Lookups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Concerns\ClassFixtures;
use Tests\TestCase;

/**
 * GET /api/v1/public/classes (contract v0.8 §7.5; C1; D-026): only publicly listed classes,
 * neutral ordering, no counts, ranks or boosts.
 */
class PublicCatalogueTest extends TestCase
{
    use ClassFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/public/classes';

    public function test_only_active_listed_classes_of_active_institutes_appear_in_neutral_order(): void
    {
        $institute = $this->makeInstitute();
        $suspended = $this->makeInstitute();
        $this->makeClass($institute, ['title' => 'Zeta', 'starts_on' => '2026-02-01', 'public_listing' => 1]);
        $this->makeClass($institute, ['title' => 'Beta', 'starts_on' => '2026-01-15', 'public_listing' => 1]);
        $this->makeClass($institute, ['title' => 'Alpha', 'starts_on' => '2026-01-15', 'public_listing' => 1]);
        $this->makeClass($institute, ['title' => 'Draft', 'status' => 'draft', 'public_listing' => 1]);
        $this->makeClass($institute, ['title' => 'Archived', 'status' => 'archived', 'public_listing' => 1]);
        $this->makeClass($institute, ['title' => 'Private', 'public_listing' => 0]);
        $this->makeClass($suspended, ['title' => 'Suspended', 'public_listing' => 1]);
        $this->suspend($suspended);

        $response = $this->nonStateful('GET', self::URL)->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        // starts_on, then title, then uuid: no rank, rating, boost or enrolment effect.
        $this->assertSame(['Alpha', 'Beta', 'Zeta'], array_column($response->json('data'), 'title'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertNoInternalIds($response);

        foreach (['max_students', 'active_enrollment_count', 'status', 'public_listing', 'syllabus', 'rank', 'rating', 'boost'] as $absent) {
            $this->assertArrayNotHasKey($absent, $response->json('data.0'));
        }
    }

    public function test_filters_by_subject_grade_district_and_text(): void
    {
        $colombo = $this->makeInstitute(overrides: ['district' => 'Colombo']);
        $kandy = $this->makeInstitute(overrides: ['district' => 'Kandy']);
        $this->makeClass($colombo, ['title' => 'Combined Maths Theory', 'public_listing' => 1]);
        $this->makeClass($kandy, ['title' => 'Physics Revision', 'description' => 'Past papers 100%', 'public_listing' => 1, 'subject_id' => Lookups::id('subjects', 'PHY'), 'grade_id' => Lookups::id('grades', 'AL2')]);

        $titles = fn (string $query) => array_column($this->nonStateful('GET', self::URL.'?'.$query)->assertOk()->json('data'), 'title');

        $this->assertSame(['Physics Revision'], $titles('subject_code=PHY'));
        $this->assertSame(['Physics Revision'], $titles('grade_code=AL2'));
        $this->assertSame(['Combined Maths Theory'], $titles('district=colombo'));
        $this->assertSame(['Physics Revision'], $titles('district=Kandy'));
        $this->assertSame(['Combined Maths Theory'], $titles('q=maths'));
        $this->assertSame(['Physics Revision'], $titles('q='.urlencode('100%')));
        $this->assertSame([], $titles('q='.urlencode('_')));

        foreach (['subject_code=phy', 'grade_code=G99', 'district=Atlantis', 'q='.str_repeat('q', 101), 'per_page=0'] as $query) {
            $this->nonStateful('GET', self::URL.'?'.$query)->assertUnprocessable();
        }
    }

    public function test_the_catalogue_allows_60_requests_per_minute_per_ip(): void
    {
        for ($i = 1; $i <= 60; $i++) {
            $this->nonStateful('GET', self::URL)->assertOk();
        }

        $this->nonStateful('GET', self::URL)->assertStatus(429)->assertHeader('Retry-After');
    }
}
