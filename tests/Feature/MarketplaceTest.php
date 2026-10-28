<?php

namespace Tests\Feature;

use App\Models\PublicInquiry;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_is_public_and_labels_concept_content(): void
    {
        $this->get('/')->assertOk()->assertSee('CASTÉ')->assertSee('Find a place.')
            ->assertSee('CONCEPT COLLECTION')->assertSee('Not active listings.')
            ->assertSee('/media/architecture.mp4', false);
    }

    public function test_search_combines_location_purpose_type_budget_and_bedrooms(): void
    {
        $this->get('/properties?purpose=buy&location=Lagos&type=villa&max=300000000&beds=4')
            ->assertOk()->assertSee('The Palm Residence')->assertDontSee('The Courtyard House</h3>', false)
            ->assertViewHas('properties', fn ($properties): bool => $properties->count() === 1 && $properties->first()['slug'] === 'the-palm-residence');
    }

    public function test_commercial_search_includes_office_shop_and_warehouse(): void
    {
        $this->get('/properties?type=commercial')->assertOk()
            ->assertViewHas('properties', fn ($properties): bool => $properties->count() === 3 && $properties->every(fn ($p): bool => in_array($p['type'], ['office', 'shop', 'warehouse'], true)));
    }

    public function test_additional_filters_and_empty_results_are_supported(): void
    {
        $this->get('/properties?furnished=1&amenity=pool&size=400&baths=5')->assertOk()
            ->assertViewHas('properties', fn ($properties): bool => $properties->count() === 1);
        $this->get('/properties?location=Nowhere')->assertOk()->assertSee('No concept properties match these filters.');
    }

    public function test_invalid_filters_and_inverted_price_ranges_are_rejected(): void
    {
        $this->get('/properties?type=invalid')->assertSessionHasErrors('type');
        $this->get('/properties?min=900&max=100')->assertSessionHasErrors('max');
    }

    public function test_property_details_are_public_and_unknown_slugs_return_404(): void
    {
        $this->get('/properties/the-palm-residence')->assertOk()->assertSee('The Palm Residence')
            ->assertSee('NOT AN ACTIVE LISTING')->assertSee('Request viewing information');
        $this->get('/properties/missing')->assertNotFound();
    }

    public function test_a_consented_inquiry_is_persisted_without_accepting_an_injected_status(): void
    {
        $this->from('/')->post('/inquiries', ['name' => 'Preview Visitor', 'email' => 'visitor@example.test', 'intent' => 'viewing', 'message' => 'Please explain the preview.', 'consent' => '1', 'preferences' => ['property' => 'the-palm-residence', 'location' => 'Lagos'], 'status' => 'verified'])
            ->assertRedirect('/')->assertSessionHas('status');
        $this->assertDatabaseHas('public_inquiries', ['email' => 'visitor@example.test', 'intent' => 'viewing', 'status' => 'new']);
        $this->assertSame('the-palm-residence', PublicInquiry::sole()->preferences['property']);
    }

    public function test_inquiry_requires_consent_and_rejects_honeypot_submissions(): void
    {
        $data = ['name' => 'Visitor', 'email' => 'visitor@example.test', 'intent' => 'demo'];
        $this->post('/inquiries', $data)->assertSessionHasErrors('consent');
        $this->post('/inquiries', [...$data, 'consent' => '1', 'website' => 'spam'])->assertSessionHasErrors('website');
        $this->assertDatabaseCount('public_inquiries', 0);
    }

    public function test_inquiry_rejects_unknown_property_and_unexpected_preference_keys(): void
    {
        $data = ['name' => 'Visitor', 'email' => 'visitor@example.test', 'intent' => 'viewing', 'consent' => '1'];
        $this->post('/inquiries', [...$data, 'preferences' => ['property' => 'not-a-property']])->assertUnprocessable();
        $this->post('/inquiries', [...$data, 'preferences' => ['private_data' => 'unexpected']])->assertSessionHasErrors('preferences');
        $this->assertDatabaseCount('public_inquiries', 0);
    }

    public function test_only_platform_admins_can_review_public_inquiries(): void
    {
        $this->seed(AccessControlSeeder::class);
        PublicInquiry::create(['name' => 'Preview Visitor', 'email' => 'visitor@example.test', 'intent' => 'demo', 'message' => 'A test request.']);
        $this->get('/public-inquiries')->assertRedirect('/login');
        $this->actingAs(User::factory()->role('agency_admin')->create())->get('/public-inquiries')->assertForbidden();
        $this->actingAs(User::factory()->role('platform_admin')->create())->get('/public-inquiries')->assertOk()->assertSee('visitor@example.test');
    }

    public function test_journal_and_information_pages_have_real_destinations(): void
    {
        foreach (array_keys(config('marketplace.articles')) as $slug) {
            $this->get('/journal/'.$slug)->assertOk();
        }
        foreach (['privacy', 'terms', 'verification', 'safety', 'cookies'] as $page) {
            $this->get('/information/'.$page)->assertOk();
        }
        $this->get('/journal/missing')->assertNotFound();
        $this->get('/information/missing')->assertNotFound();
    }
}
