<?php

namespace Tests\Feature;

use App\Jobs\SendAppointmentReminder;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\ServiceListing;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\PropertyCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role('agency_admin')->create();
        $this->actingAs($this->admin)->withSession(['workspace_id' => $this->admin->currentWorkspace()->id]);
        Http::preventStrayRequests();
    }

    private function create(string $entity, array $data): mixed
    {
        $this->post('/operations/'.$entity, $data)->assertRedirect()->assertSessionHasNoErrors();
        $model = config('operations.'.$entity.'.model');

        return $model::where('workspace_id', $this->admin->currentWorkspace()->id)->latest('id')->firstOrFail();
    }

    private function property(array $extra = []): Property
    {
        return $this->create('properties', [...$this->propertyData(), ...$extra]);
    }

    private function propertyData(): array
    {
        return ['name' => 'Abuja rental home', 'slug' => 'abuja-rental-home', 'location' => 'Abuja', 'type' => 'house', 'purpose' => 'rent', 'description' => 'A real managed property.', 'price' => '1000.00', 'currency' => 'USD', 'beds' => 3, 'baths' => 2, 'area' => '120', 'publication_status' => 'draft', 'amenities' => 'parking, garden'];
    }

    private function contact(): Contact
    {
        return $this->create('contacts', ['name' => 'Test tenant', 'type' => 'tenant', 'email' => 'tenant@example.test']);
    }

    private function invoiceData(Contact $contact): array
    {
        return ['name' => 'Rental invoice', 'contact_id' => $contact->id, 'currency' => 'USD', 'issued_on' => '2026-10-01', 'due_on' => '2026-10-31', 'tax_percent' => '10', 'items' => [['description' => 'Rent', 'quantity' => 2, 'unit_price' => '100.00']], 'status' => 'issued'];
    }

    public function test_original_catalogue_prices_and_media_are_imported_once_and_edits_are_preserved(): void
    {
        $this->assertDatabaseCount('properties', count(config('marketplace.properties')));
        $source = Property::where('slug', 'the-palm-residence')->sole();
        $this->assertSame(28500000000, $source->price_minor);
        $this->assertTrue($source->is_concept);
        $this->assertDatabaseHas('property_media', ['property_id' => $source->id, 'path' => 'media/villa.jpg']);
        $source->update(['name' => 'Edited catalogue title', 'price_minor' => 50000]);
        $this->assertSame(0, app(PropertyCatalog::class)->import());
        $this->get('/properties/the-palm-residence')->assertOk()->assertSee('Edited catalogue title');
        $this->assertSame(50000, $source->fresh()->price_minor);
    }

    public function test_database_publication_controls_public_visibility_and_updates_prices(): void
    {
        $property = $this->property();
        $this->get('/properties/abuja-rental-home')->assertNotFound();
        $this->patch('/operations/properties/'.$property->id, [...$this->propertyData(), 'publication_status' => 'published', 'price' => '1234.56'])->assertSessionHasNoErrors();
        $this->get('/properties/abuja-rental-home')->assertOk()->assertSee('Abuja rental home')->assertSee('1,234.56');
        $this->assertSame(123456, $property->fresh()->price_minor);
        $this->get('/')->assertOk()->assertSee('Abuja rental home');
    }

    public function test_all_new_module_screens_and_create_forms_are_usable_by_organisation_admin(): void
    {
        foreach (array_keys(config('operations')) as $entity) {
            $this->get('/operations/'.$entity)->assertOk();
            if (config('operations.'.$entity.'.readonly')) {
                $this->get('/operations/'.$entity.'/create')->assertForbidden();
            } else {
                $this->get('/operations/'.$entity.'/create')->assertOk();
            }
        }
        $this->get('/operations/finance/report')->assertOk();
    }

    public function test_private_records_and_foreign_links_cannot_cross_workspace_boundaries(): void
    {
        $property = $this->property();
        $other = User::factory()->role('agency_admin')->create();
        $this->actingAs($other)->withSession(['workspace_id' => $other->currentWorkspace()->id]);
        $this->get('/operations/properties/'.$property->id)->assertNotFound();
        $this->post('/operations/units', ['name' => 'Injected', 'property_id' => $property->id, 'price' => '1', 'currency' => 'USD'])->assertNotFound();
        $this->assertDatabaseCount('property_units', 0);
        $this->get('/operations/properties')->assertDontSee('Abuja rental home');
    }

    public function test_module_disable_and_read_only_role_are_enforced_on_writes(): void
    {
        $workspace = $this->admin->currentWorkspace();
        $workspace->update(['enabled_modules' => array_values(array_diff($workspace->enabled_modules, ['properties']))]);
        $this->post('/operations/properties', $this->propertyData())->assertForbidden();
        $auditor = User::factory()->role('auditor')->create();
        $this->actingAs($auditor)->withSession(['workspace_id' => $auditor->currentWorkspace()->id]);
        $this->get('/operations/documents')->assertOk();
        $this->post('/operations/documents', [])->assertForbidden();
    }

    public function test_leases_reject_overlap_and_unit_mismatch_and_occupancy_is_date_based(): void
    {
        $property = $this->property();
        $tenant = $this->contact();
        $unit = $this->create('units', ['name' => 'A1', 'property_id' => $property->id, 'price' => '1000', 'currency' => 'USD']);
        $data = ['name' => 'Lease A1', 'property_id' => $property->id, 'unit_id' => $unit->id, 'contact_id' => $tenant->id, 'starts_on' => today()->subDay()->format('Y-m-d'), 'ends_on' => today()->addYear()->format('Y-m-d'), 'period' => 'monthly', 'rent' => '1000', 'deposit' => '500', 'currency' => 'USD', 'status' => 'active'];
        $lease = $this->create('leases', $data);
        $this->post('/operations/leases', $data)->assertSessionHasErrors('starts_on');
        $this->post('/operations/leases', [...$data, 'unit_id' => null])->assertSessionHasErrors('unit_id');
        $this->get('/operations/units')->assertOk()->assertSee('Occupied');
        $this->patch('/operations/leases/'.$lease->id, [...$data, 'status' => 'ended'])->assertSessionHasNoErrors();
        $this->get('/operations/units')->assertSee('No current active tenancy');
        $this->patch('/operations/leases/'.$lease->id, $data)->assertSessionHasErrors('status');
    }

    public function test_maintenance_and_inspection_records_have_working_status_and_cost_updates(): void
    {
        $property = $this->property();
        $data = ['name' => 'Repair tap', 'property_id' => $property->id, 'description' => 'Replace tap', 'priority' => 'urgent', 'cost' => '12.34', 'currency' => 'USD'];
        $ticket = $this->create('maintenance', $data);
        $this->patch('/operations/maintenance/'.$ticket->id, [...$data, 'status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame(1234, $ticket->fresh()->cost_minor);
        $inspection = $this->create('inspections', ['name' => 'Move in check', 'property_id' => $property->id, 'inspected_on' => today()->format('Y-m-d')]);
        $this->patch('/operations/inspections/'.$inspection->id, ['name' => 'Move in check', 'property_id' => $property->id, 'inspected_on' => today()->format('Y-m-d'), 'status' => 'completed'])->assertSessionHasErrors('findings');
    }

    public function test_invoice_totals_receipts_and_printing_use_exact_currency_and_preserve_issued_amounts(): void
    {
        $contact = $this->contact();
        $data = $this->invoiceData($contact);
        $invoice = $this->create('invoices', $data);
        $this->assertSame(22000, $invoice->total_minor);
        $this->post('/billing/invoices/'.$invoice->id.'/receipts', ['amount' => '120.00', 'received_on' => '2026-10-28'])->assertRedirect()->assertSessionHasNoErrors();
        $receipt = Receipt::sole();
        $this->assertSame(10000, $invoice->balanceMinor());
        $this->assertSame('recorded', $receipt->payment->status);
        $this->get('/billing/receipts/'.$receipt->id.'/print')->assertOk()->assertSee($receipt->reference);
        $contact->update(['name' => 'Changed contact']);
        $this->get('/billing/invoices/'.$invoice->id.'/print')->assertOk()->assertSee('Test tenant')->assertDontSee('Changed contact');
        $this->patch('/operations/invoices/'.$invoice->id, [...$data, 'items' => [['description' => 'Changed', 'quantity' => 1, 'unit_price' => '1']]])->assertSessionHasErrors('status');
        $this->post('/billing/invoices/'.$invoice->id.'/receipts', ['amount' => '100.01', 'received_on' => '2026-10-28'])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('receipts', 1);
        Http::assertNothingSent();
    }

    public function test_existing_payment_can_be_receipted_only_once_and_does_not_duplicate_income(): void
    {
        $invoice = $this->create('invoices', $this->invoiceData($this->contact()));
        $payment = Payment::factory()->create(['workspace_id' => $this->admin->currentWorkspace()->id, 'user_id' => $this->admin->id, 'recorded_by' => $this->admin->id, 'amount_minor' => 10000, 'currency' => 'USD', 'minor_unit' => 2, 'status' => 'recorded']);
        $data = ['amount' => '100', 'received_on' => '2026-10-28', 'payment_id' => $payment->id];
        $this->post('/billing/invoices/'.$invoice->id.'/receipts', $data)->assertSessionHasNoErrors();
        $this->post('/billing/invoices/'.$invoice->id.'/receipts', $data)->assertSessionHasErrors('payment_id');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('receipts', 1);
    }

    public function test_zero_decimal_money_and_excess_precision_are_enforced_in_operational_records(): void
    {
        $this->post('/operations/expenses', ['name' => 'Yen expense', 'category' => 'other', 'amount' => '10.5', 'currency' => 'JPY', 'incurred_on' => '2026-10-28'])->assertSessionHasErrors('amount');
        $expense = $this->create('expenses', ['name' => 'Yen expense', 'category' => 'other', 'amount' => '10', 'currency' => 'JPY', 'incurred_on' => '2026-10-28']);
        $this->assertSame(10, $expense->amount_minor);
        $this->assertSame(0, $expense->minor_unit);
    }

    public function test_payroll_requires_approval_has_unique_period_and_posts_one_expense(): void
    {
        $staff = $this->create('staff', ['name' => 'Property administrator', 'position' => 'Manager', 'started_on' => '2026-01-01', 'salary' => '1000', 'currency' => 'USD']);
        $data = ['name' => 'October payroll', 'staff_member_id' => $staff->id, 'period' => '2026-10', 'gross' => '1000', 'allowances' => '100', 'deductions' => '50', 'currency' => 'USD'];
        $payroll = $this->create('payroll', $data);
        $this->assertSame(105000, $payroll->net_minor);
        $this->post('/operations/payroll', $data)->assertSessionHasErrors('period');
        $this->patch('/operations/payroll/'.$payroll->id, [...$data, 'status' => 'paid', 'paid_on' => '2026-10-28'])->assertSessionHasErrors('status');
        $this->patch('/operations/payroll/'.$payroll->id, [...$data, 'status' => 'approved'])->assertSessionHasNoErrors();
        $this->patch('/operations/payroll/'.$payroll->id, [...$data, 'status' => 'paid', 'paid_on' => '2026-10-28'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expenses', ['payroll_entry_id' => $payroll->id, 'amount_minor' => 105000]);
        $this->patch('/operations/payroll/'.$payroll->id, [...$data, 'status' => 'paid', 'paid_on' => '2026-10-28'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_private_document_upload_download_and_tenant_isolation(): void
    {
        Storage::fake('local');
        $doc = $this->create('documents', ['name' => 'Lease contract', 'category' => 'lease', 'file' => UploadedFile::fake()->create('lease.pdf', 20, 'application/pdf')]);
        Storage::disk('local')->assertExists($doc->path);
        $this->get('/private-documents/'.$doc->id)->assertOk()->assertDownload('lease.pdf');
        $this->post('/operations/documents', ['name' => 'Bad executable', 'category' => 'other', 'file' => UploadedFile::fake()->create('bad.php', 1, 'application/x-httpd-php')])->assertSessionHasErrors('file');
        $other = User::factory()->role('agency_admin')->create();
        $this->actingAs($other)->withSession(['workspace_id' => $other->currentWorkspace()->id])->get('/private-documents/'.$doc->id)->assertNotFound();
    }

    public function test_calendar_conflicts_rescheduling_and_reminder_cancellation_are_enforced(): void
    {
        Queue::fake();
        Notification::fake();
        $data = ['name' => 'Property viewing', 'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i'), 'reminder_at' => now()->subMinute()->format('Y-m-d\TH:i')];
        $appointment = $this->create('appointments', $data);
        $this->post('/operations/appointments', $data)->assertSessionHasErrors('starts_at');
        $this->artisan('appointments:remind')->assertSuccessful();
        Queue::assertPushed(SendAppointmentReminder::class);
        $job = new SendAppointmentReminder($appointment->id, $appointment->reminder_version);
        $this->patch('/operations/appointments/'.$appointment->id, [...$data, 'status' => 'cancelled'])->assertSessionHasNoErrors();
        $job->handle();
        Notification::assertNothingSent();
    }

    public function test_due_appointment_reminder_delivers_to_active_assigned_staff(): void
    {
        Notification::fake();
        $appointment = $this->create('appointments', ['name' => 'Follow up viewing', 'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i'), 'reminder_at' => now()->subMinute()->format('Y-m-d\TH:i')]);
        (new SendAppointmentReminder($appointment->id, $appointment->reminder_version))->handle();
        Notification::assertSentTo($this->admin, SystemNotification::class);
        $this->assertNotNull($appointment->fresh()->reminded_at);
    }

    public function test_referrals_require_consent_and_receiving_organisation_acceptance(): void
    {
        $other = User::factory()->role('agency_admin')->create();
        $otherWorkspace = $other->currentWorkspace()->id;
        $data = ['name' => 'Buyer introduction', 'target_workspace_id' => $otherWorkspace, 'client_name' => 'Shared client', 'terms' => '5 percent on agreed completion', 'commission_percent' => '5'];
        $this->post('/operations/referrals', $data)->assertSessionHasErrors('sharing_consent');
        $referral = $this->create('referrals', [...$data, 'sharing_consent' => 1]);
        $this->patch('/operations/referrals/'.$referral->id.'/respond', ['status' => 'accepted'])->assertSessionHasErrors('status');
        $this->actingAs($other)->withSession(['workspace_id' => $otherWorkspace]);
        $this->get('/operations/referrals/incoming')->assertOk()->assertSee('Shared client');
        $this->get('/operations/referrals/'.$referral->id)->assertNotFound();
        $this->patch('/operations/referrals/'.$referral->id.'/respond', ['status' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $referral->fresh()->status);
    }

    public function test_professional_directory_and_two_party_quote_acceptance_work(): void
    {
        $provider = User::factory()->role('service_provider')->create();
        $workspace = $provider->currentWorkspace()->id;
        $this->actingAs($provider)->withSession(['workspace_id' => $workspace]);
        $this->post('/operations/services', ['name' => 'Abuja Photography', 'profession' => 'photographer', 'location' => 'Abuja', 'description' => 'Property photography', 'fee' => '100', 'currency' => 'USD', 'status' => 'published'])->assertSessionHasNoErrors();
        $listing = ServiceListing::sole();
        $this->get('/professionals')->assertOk()->assertSee('Abuja Photography');
        $this->actingAs($this->admin)->withSession(['workspace_id' => $this->admin->currentWorkspace()->id]);
        $booking = $this->create('bookings', ['name' => 'Photograph house', 'service_listing_id' => $listing->id, 'description' => 'Need photos', 'requested_on' => '2026-11-10', 'sharing_consent' => 1]);
        $this->patch('/operations/bookings/'.$booking->id.'/respond', ['status' => 'quoted', 'quote' => '250'])->assertSessionHasErrors('status');
        $this->actingAs($provider)->withSession(['workspace_id' => $workspace]);
        $this->get('/operations/bookings/incoming')->assertOk()->assertSee('Need photos');
        $this->patch('/operations/bookings/'.$booking->id.'/respond', ['status' => 'quoted', 'quote' => '250'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->withSession(['workspace_id' => $this->admin->currentWorkspace()->id]);
        $this->patch('/operations/bookings/'.$booking->id.'/respond', ['status' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertSame(25000, $booking->fresh()->quote_minor);
    }

    public function test_projects_have_unit_inventory_budget_and_append_only_progress(): void
    {
        $project = $this->create('projects', ['name' => 'Abuja development', 'location' => 'Abuja', 'budget' => '100000', 'currency' => 'USD']);
        $property = $this->property(['project_id' => $project->id]);
        $this->create('units', ['name' => 'Unit 1', 'property_id' => $property->id, 'price' => '20000', 'currency' => 'USD', 'status' => 'reserved']);
        $progress = $this->create('progress', ['name' => 'Foundations', 'project_id' => $project->id, 'recorded_on' => '2026-10-28', 'progress' => 25, 'description' => 'Foundations complete', 'cost' => '10000', 'currency' => 'USD']);
        $this->assertSame(25, $project->fresh()->progress);
        $this->patch('/operations/progress/'.$progress->id, ['name' => 'Overwrite', 'project_id' => $project->id, 'recorded_on' => '2026-10-28', 'progress' => 10, 'description' => 'Change', 'cost' => '1', 'currency' => 'USD'])->assertStatus(422);
        $this->get('/operations/projects/'.$project->id)->assertOk();
    }

    public function test_csv_exports_require_explicit_export_grant_and_membership_flag(): void
    {
        $this->property();
        $this->get('/operations/properties/export')->assertForbidden();
        $this->admin->currentMembership()->update(['allow_exports' => true]);
        $this->get('/operations/properties/export')->assertOk()->assertDownload('properties.csv');
    }

    public function test_website_media_library_replacements_are_database_backed_and_super_admin_only(): void
    {
        $asset = PropertyMedia::whereNull('property_id')->where('name', 'hero.jpg')->sole();
        $this->get('/catalog/site-media')->assertForbidden();
        $this->post('/catalog/site-media/'.$asset->id, ['file' => UploadedFile::fake()->image('replacement.jpg')])->assertForbidden();
        $platform = User::factory()->role('platform_admin')->create();
        $this->actingAs($platform)->withSession(['workspace_id' => $platform->currentWorkspace()->id]);
        try {
            $this->get('/catalog/site-media')->assertOk()->assertSee('hero.jpg');
            $this->post('/catalog/site-media/'.$asset->id, ['file' => UploadedFile::fake()->image('replacement.jpg')])->assertSessionHasNoErrors();
            $newPath = $asset->fresh()->path;
            $this->get('/')->assertOk()->assertSee($newPath, false);
            app(PropertyCatalog::class)->import();
            $this->assertSame($newPath, $asset->fresh()->path);
            $this->assertSame(1, PropertyMedia::whereNull('property_id')->where('name', 'hero.jpg')->count());
        } finally {
            if (isset($newPath)) {
                @unlink(public_path($newPath));
            }
        }
    }

    public function test_property_photo_uploads_appear_in_database_gallery_and_can_change_cover(): void
    {
        $property = $this->property();
        $path = null;
        try {
            $this->patch('/operations/properties/'.$property->id, [...$this->propertyData(), 'publication_status' => 'published', 'uploads' => [UploadedFile::fake()->image('front.jpg')]])->assertSessionHasNoErrors();
            $media = $property->media()->sole();
            $path = $media->path;
            $this->assertTrue($media->is_cover);
            $this->get('/properties/'.$property->slug)->assertOk()->assertSee($path, false);
            $this->post('/catalog/'.$property->id.'/media/'.$media->id, ['action' => 'remove'])->assertSessionHasNoErrors();
            $this->assertCount(0, $property->media()->get());
        } finally {
            if ($path) {
                @unlink(public_path($path));
            }
        }
    }

    public function test_large_invoice_calculations_return_validation_errors_instead_of_overflow(): void
    {
        $data = $this->invoiceData($this->contact());
        $data['items'] = [['description' => 'Overflow', 'quantity' => 100000, 'unit_price' => '999999999999999']];
        $this->post('/operations/invoices', $data)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_approved_payroll_cannot_change_its_calculation_at_payment_time(): void
    {
        $staff = $this->create('staff', ['name' => 'Manager', 'position' => 'Manager', 'started_on' => '2026-01-01', 'salary' => '1000', 'currency' => 'USD']);
        $data = ['name' => 'Payroll', 'staff_member_id' => $staff->id, 'period' => '2026-10', 'gross' => '1000', 'allowances' => '0', 'deductions' => '0', 'currency' => 'USD'];
        $record = $this->create('payroll', [...$data, 'status' => 'approved']);
        $this->patch('/operations/payroll/'.$record->id, [...$data, 'gross' => '10000', 'status' => 'paid', 'paid_on' => '2026-10-28'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_unit_sales_require_a_buyer_date_and_agreed_price(): void
    {
        $property = $this->property();
        $data = ['name' => 'Sale unit', 'property_id' => $property->id, 'price' => '20000', 'currency' => 'USD', 'status' => 'sold'];
        $this->post('/operations/units', $data)->assertSessionHasErrors('status');
        $unit = $this->create('units', [...$data, 'buyer_name' => 'Buyer Example', 'sold_on' => '2026-10-28', 'sale_price' => '19000']);
        $this->assertSame(1900000, $unit->sale_price_minor);
        $this->get('/operations/units/'.$unit->id)->assertOk()->assertSee('Buyer Example');
    }

    public function test_received_payments_and_expenses_are_reported_by_currency_without_conversion(): void
    {
        $this->create('expenses', ['name' => 'NGN expense', 'category' => 'other', 'amount' => '100', 'currency' => 'NGN', 'incurred_on' => '2026-10-28']);
        $this->create('expenses', ['name' => 'USD expense', 'category' => 'other', 'amount' => '10', 'currency' => 'USD', 'incurred_on' => '2026-10-28']);
        Payment::factory()->create(['workspace_id' => $this->admin->currentWorkspace()->id, 'user_id' => $this->admin->id, 'status' => 'recorded', 'amount_minor' => 5000, 'currency' => 'USD', 'minor_unit' => 2]);
        $this->get('/operations/finance/report')->assertOk()->assertSee('USD')->assertSee('NGN')->assertSee('40.00');
    }

    public function test_receipts_are_immutable_and_documents_cannot_use_another_workspaces_reference(): void
    {
        $this->post('/operations/receipts', [])->assertForbidden();
        $foreign = Property::where('slug', 'the-palm-residence')->sole();
        Storage::fake('local');
        $this->post('/operations/documents', ['name' => 'Bad link', 'category' => 'other', 'property_id' => $foreign->id, 'file' => UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf')])->assertNotFound();
        $this->assertDatabaseCount('business_documents', 0);
    }

    public function test_linked_tenant_can_read_only_their_lease_invoice_and_receipt_in_the_organisation(): void
    {
        $tenant = User::factory()->role('tenant')->create();
        $workspace = $this->admin->currentWorkspace();
        $member = Membership::create(['workspace_id' => $workspace->id, 'user_id' => $tenant->id, 'scope' => 'own', 'is_active' => true]);
        $member->roles()->sync(Role::where('name', 'tenant')->pluck('id'));
        $property = $this->property();
        $contact = $this->create('contacts', ['name' => 'Linked tenant', 'type' => 'tenant', 'linked_user_id' => $tenant->id]);
        $lease = $this->create('leases', ['name' => 'Tenant agreement', 'property_id' => $property->id, 'contact_id' => $contact->id, 'starts_on' => '2026-10-01', 'ends_on' => '2027-09-30', 'period' => 'annually', 'rent' => '1000', 'deposit' => '0', 'currency' => 'USD', 'status' => 'active']);
        $invoice = $this->create('invoices', $this->invoiceData($contact));
        $private = $this->create('invoices', $this->invoiceData($this->contact()));
        $this->post('/billing/invoices/'.$invoice->id.'/receipts', ['amount' => '100', 'received_on' => '2026-10-28'])->assertSessionHasNoErrors();
        $receipt = Receipt::where('invoice_id', $invoice->id)->sole();
        $tenant->unsetRelation('workspaceMembership');
        $this->actingAs($tenant)->withSession(['workspace_id' => $workspace->id]);
        $this->get('/operations/leases/'.$lease->id)->assertOk()->assertSee('Tenant agreement');
        $this->get(route('billing.invoice', $invoice->id))->assertOk();
        $this->get(route('billing.receipt', $receipt->id))->assertOk();
        $this->get(route('billing.invoice', $private->id))->assertNotFound();
        $this->get('/operations/leases/'.$lease->id.'/edit')->assertNotFound();
        $this->post('/billing/invoices/'.$invoice->id.'/receipts', ['amount' => '1', 'received_on' => '2026-10-28'])->assertForbidden();
    }

    public function test_contacts_cannot_be_linked_to_an_account_outside_the_workspace(): void
    {
        $foreign = User::factory()->role('tenant')->create();
        $this->post('/operations/contacts', ['name' => 'Wrong tenant', 'type' => 'tenant', 'linked_user_id' => $foreign->id])->assertNotFound();
        $this->assertDatabaseMissing('contacts', ['name' => 'Wrong tenant']);
    }

    public function test_draft_invoice_can_be_issued_then_voided_without_rewriting_its_amounts(): void
    {
        $invoice = $this->create('invoices', [...$this->invoiceData($this->contact()), 'status' => 'draft']);
        $this->post('/billing/invoices/'.$invoice->id.'/status', ['action' => 'issue'])->assertSessionHasNoErrors();
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertSame(22000, $invoice->fresh()->total_minor);
        $this->post('/billing/invoices/'.$invoice->id.'/status', ['action' => 'issue'])->assertSessionHasErrors('action');
        $this->post('/billing/invoices/'.$invoice->id.'/status', ['action' => 'void'])->assertSessionHasNoErrors();
        $this->assertSame('void', $invoice->fresh()->status);
        $this->post('/billing/invoices/'.$invoice->id.'/receipts', ['amount' => '100', 'received_on' => '2026-10-28'])->assertSessionHasErrors('amount');
    }
}
