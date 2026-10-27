<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Services\WorkspaceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_keeps_the_marketplace_and_business_features_with_the_new_company_content(): void
    {
        $this->get('/')->assertOk()->assertSee('Castegroup')->assertDontSee('citiESTATE')
            ->assertSee('CONCEPT COLLECTION')->assertSee('Automated Follow-Ups')
            ->assertSee('Client Relationship Management')->assertSee('WorkSpaces')
            ->assertSee('Olusegun Toluhi')->assertSee('CASTÉ Homes Ltd')
            ->assertSee('+234 802 377 4420')->assertSee('Nbora District')
            ->assertSee(route('company.show', 'founder'), false)
            ->assertSee('/media/architecture.mp4', false);
    }

    public function test_company_pages_publish_the_supplied_story_founder_message_and_portfolio_without_invented_availability(): void
    {
        $this->get('/company/about')->assertOk()->assertSee(config('company.vision'))
            ->assertSee(config('company.mission'))->assertSee('Technical Excellence')
            ->assertSee('Stewardship')->assertSee('Partnership');
        $response = $this->get('/company/founder')->assertOk()->assertSee('Olusegun Toluhi');
        foreach (config('company.founder.message') as $paragraph) {
            $response->assertSee($paragraph);
        }
        $this->get('/company/workspaces')->assertOk()->assertSee('serviced apartment')
            ->assertSee('Project locations, specifications, delivery dates, prices and booking availability have not been provided.')
            ->assertSee('not a WorkSpaces project photograph')->assertSee('Enquire about WorkSpaces');
        $this->get('/company/missing')->assertNotFound();
        $this->get('/company/platform')->assertNotFound();
    }

    public function test_inquiry_feedback_uses_the_configured_name_and_saves_the_portfolio_context(): void
    {
        PlatformSetting::findOrFail(1)->update(['values' => ['brand_name' => 'Custom Brand']]);
        $this->get('/company/about')->assertSee('About Custom Brand')->assertSee('data-brand-name="Custom Brand"', false);
        $this->from('/company/workspaces')->post('/inquiries', [
            'name' => 'Interested Visitor', 'email' => 'visitor@example.test', 'intent' => 'support',
            'consent' => '1', 'preferences' => ['topic' => 'WorkSpaces hospitality portfolio'],
        ])->assertRedirect('/company/workspaces')->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Custom Brand team'));
        $this->assertDatabaseHas('public_inquiries', ['email' => 'visitor@example.test', 'intent' => 'support']);
    }

    public function test_upgrade_changes_only_legacy_branding_and_keeps_other_configuration(): void
    {
        PlatformSetting::findOrFail(1)->update(['values' => ['brand_name' => 'citiESTATE', 'company_name' => 'citiESTATE', 'support_email' => 'team@example.test', 'support_phone' => '', 'logo_dark' => 'media/branding/existing.png', 'default_currency' => 'USD', 'commission_percent' => '5'], 'revision' => 7]);
        $workspace = app(WorkspaceProvisioner::class)->platform();
        DB::table('workspaces')->where('id', $workspace->id)->update(['name' => 'citiESTATE platform']);
        $migration = require database_path('migrations/2026_10_27_192444_rebrand_platform_to_castegroup.php');
        $migration->up();
        $settings = PlatformSetting::findOrFail(1);
        $this->assertSame('Castegroup', $settings->values['brand_name']);
        $this->assertSame('CASTÉ Homes Ltd', $settings->values['company_name']);
        $this->assertSame('+234 802 377 4420', $settings->values['support_phone']);
        $this->assertSame('team@example.test', $settings->values['support_email']);
        $this->assertSame('media/branding/existing.png', $settings->values['logo_dark']);
        $this->assertSame('USD', $settings->values['default_currency']);
        $this->assertSame('5', $settings->values['commission_percent']);
        $this->assertSame(8, $settings->revision);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => 'Castegroup platform']);
        $migration->up();
        $this->assertSame(8, $settings->fresh()->revision);
    }

    public function test_upgrade_and_rollback_preserve_operator_customisations(): void
    {
        $custom = ['brand_name' => 'Other Agency', 'company_name' => 'Other Limited', 'support_phone' => '+1234', 'address' => 'Custom address'];
        PlatformSetting::findOrFail(1)->update(['values' => $custom, 'revision' => 9]);
        $migration = require database_path('migrations/2026_10_27_192444_rebrand_platform_to_castegroup.php');
        $migration->up();
        $migration->down();
        $this->assertSame($custom, PlatformSetting::findOrFail(1)->values);
        $this->assertSame(9, PlatformSetting::findOrFail(1)->revision);
    }

    public function test_company_content_does_not_replace_the_account_workspace_switcher(): void
    {
        $this->get('/workspaces')->assertRedirect('/login');
        $this->get('/company/workspaces')->assertOk();
    }
}
