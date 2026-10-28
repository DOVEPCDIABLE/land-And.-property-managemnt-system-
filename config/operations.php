<?php

use App\Models;

$text = fn (string $label, bool $required = true): array => ['label' => $label, 'type' => 'text', 'required' => $required];
$area = fn (string $label, bool $required = false): array => ['label' => $label, 'type' => 'textarea', 'required' => $required];
$date = fn (string $label, bool $required = true): array => ['label' => $label, 'type' => 'date', 'required' => $required];
$money = fn (string $label, string $column): array => ['label' => $label, 'type' => 'money', 'column' => $column, 'required' => true];
$choice = fn (string $label, array $options): array => ['label' => $label, 'type' => 'select', 'options' => array_combine($options, $options), 'required' => true];
$ref = fn (string $label, string $entity, bool $required = true): array => ['label' => $label, 'type' => 'reference', 'entity' => $entity, 'required' => $required];
$currency = ['label' => 'Currency', 'type' => 'currency', 'required' => true];

return [
    'properties' => ['model' => Models\Property::class, 'module' => 'properties', 'title' => 'Properties', 'initial' => 'available', 'states' => ['available', 'reserved', 'sold', 'unavailable'], 'fields' => [
        'name' => $text('Property name'), 'slug' => ['label' => 'Public URL name', 'type' => 'slug', 'required' => true], 'location' => $text('Location'),
        'type' => $choice('Property type', ['house', 'apartment', 'villa', 'land', 'office', 'shop', 'warehouse', 'commercial']), 'purpose' => $choice('Purpose', ['buy', 'rent', 'development']),
        'description' => $area('Description', true), 'price' => $money('Price or annual rent', 'price_minor'), 'currency' => $currency,
        'beds' => ['label' => 'Bedrooms', 'type' => 'integer', 'required' => true], 'baths' => ['label' => 'Bathrooms', 'type' => 'integer', 'required' => true],
        'area' => ['label' => 'Area in square metres', 'type' => 'decimal', 'required' => true], 'furnished' => ['label' => 'Furnished', 'type' => 'checkbox'],
        'amenities' => ['label' => 'Amenities', 'type' => 'tags'], 'is_concept' => ['label' => 'Illustrative concept', 'type' => 'checkbox'],
        'publication_status' => $choice('Publication', ['draft', 'published', 'archived']), 'project_id' => $ref('Development project', 'projects', false),
        'uploads' => ['label' => 'Photos or MP4 videos', 'type' => 'media'],
    ]],
    'units' => ['model' => Models\PropertyUnit::class, 'module' => 'management', 'title' => 'Property units', 'initial' => 'available', 'states' => ['available', 'reserved', 'sold', 'unavailable'], 'fields' => [
        'name' => $text('Unit name / number'), 'property_id' => $ref('Property', 'properties'), 'description' => $area('Specifications'), 'buyer_name' => $text('Buyer name (sold units)', false), 'sold_on' => $date('Sale date', false), 'sale_price' => [...$money('Agreed sale price', 'sale_price_minor'), 'required' => false], 'price' => $money('Unit price', 'price_minor'), 'currency' => $currency]],
    'contacts' => ['model' => Models\Contact::class, 'module' => 'management', 'title' => 'Tenants and property contacts', 'fields' => [
        'linked_user_id' => ['label' => 'Linked workspace account (optional)', 'type' => 'member'], 'name' => $text('Full name'), 'type' => $choice('Contact type', ['tenant', 'landlord', 'buyer', 'seller', 'investor', 'vendor']),
        'email' => ['label' => 'Email', 'type' => 'email'], 'phone' => $text('Phone', false), 'notes' => $area('Notes')]],
    'leases' => ['model' => Models\Lease::class, 'module' => 'leases', 'title' => 'Leases and tenancies', 'initial' => 'draft', 'states' => ['draft', 'active', 'ended', 'cancelled'], 'fields' => [
        'name' => $text('Lease reference / name'), 'property_id' => $ref('Property', 'properties'), 'unit_id' => $ref('Unit (required for multi-unit properties)', 'units', false),
        'contact_id' => $ref('Tenant', 'contacts'), 'starts_on' => $date('Start date'), 'ends_on' => $date('End date'),
        'period' => $choice('Rent frequency', ['monthly', 'quarterly', 'annually']), 'rent' => $money('Rent per period', 'rent_minor'), 'deposit' => $money('Deposit', 'deposit_minor'), 'currency' => $currency, 'notes' => $area('Terms / notes')]],
    'maintenance' => ['model' => Models\MaintenanceTicket::class, 'module' => 'maintenance', 'title' => 'Maintenance tickets', 'initial' => 'open', 'states' => ['open', 'in_progress', 'completed', 'cancelled'], 'fields' => [
        'name' => $text('Issue'), 'property_id' => $ref('Property', 'properties'), 'unit_id' => $ref('Unit', 'units', false), 'description' => $area('Work required', true),
        'priority' => $choice('Priority', ['low', 'normal', 'urgent']), 'due_on' => $date('Due date', false), 'cost' => $money('Recorded cost', 'cost_minor'), 'currency' => $currency]],
    'inspections' => ['model' => Models\Inspection::class, 'module' => 'inspections', 'title' => 'Property inspections', 'initial' => 'scheduled', 'states' => ['scheduled', 'completed', 'cancelled'], 'fields' => [
        'name' => $text('Inspection name'), 'property_id' => $ref('Property', 'properties'), 'unit_id' => $ref('Unit', 'units', false), 'inspected_on' => $date('Inspection date'), 'findings' => $area('Findings and required action')]],
    'documents' => ['model' => Models\BusinessDocument::class, 'module' => 'documents', 'title' => 'Private documents', 'fields' => [
        'name' => $text('Document title'), 'category' => $choice('Category', ['contract', 'lease', 'ownership', 'inspection', 'project', 'other']),
        'property_id' => $ref('Property', 'properties', false), 'lease_id' => $ref('Lease', 'leases', false), 'project_id' => $ref('Project', 'projects', false),
        'expires_on' => $date('Expiry date', false), 'notes' => $area('Notes'), 'file' => ['label' => 'Document file', 'type' => 'file', 'required' => true]]],
    'invoices' => ['model' => Models\Invoice::class, 'module' => 'payments', 'title' => 'Invoices', 'initial' => 'draft', 'states' => ['draft', 'issued', 'void'], 'fields' => [
        'name' => $text('Invoice title'), 'contact_id' => $ref('Customer / tenant', 'contacts'), 'property_id' => $ref('Property', 'properties', false), 'lease_id' => $ref('Lease', 'leases', false),
        'currency' => $currency, 'issued_on' => $date('Invoice date'), 'due_on' => $date('Due date'), 'items' => ['label' => 'Invoice items', 'type' => 'items', 'required' => true],
        'tax_percent' => ['label' => 'Tax percentage on items', 'type' => 'percent', 'required' => true], 'notes' => $area('Notes')]],
    'receipts' => ['model' => Models\Receipt::class, 'module' => 'payments', 'title' => 'Receipts', 'readonly' => true, 'fields' => ['name' => $text('Receipt title'), 'reference' => $text('Reference'), 'invoice_id' => $ref('Invoice', 'invoices'), 'received_on' => $date('Received date'), 'amount' => $money('Amount', 'amount_minor'), 'currency' => $currency, 'notes' => $area('Notes')]],
    'expenses' => ['model' => Models\Expense::class, 'module' => 'payments', 'title' => 'Expenses', 'initial' => 'recorded', 'states' => ['recorded', 'void'], 'fields' => [
        'name' => $text('Expense description'), 'category' => $choice('Category', ['maintenance', 'utilities', 'marketing', 'salary', 'construction', 'administration', 'other']),
        'property_id' => $ref('Property', 'properties', false), 'amount' => $money('Amount paid', 'amount_minor'), 'currency' => $currency, 'incurred_on' => $date('Date paid'), 'notes' => $area('Notes')]],
    'staff' => ['model' => Models\StaffMember::class, 'module' => 'payroll', 'title' => 'Staff members', 'initial' => 'active', 'states' => ['active', 'inactive'], 'fields' => [
        'name' => $text('Staff name'), 'email' => ['label' => 'Email', 'type' => 'email'], 'position' => $text('Position'), 'started_on' => $date('Start date'), 'salary' => $money('Monthly salary', 'salary_minor'), 'currency' => $currency]],
    'payroll' => ['model' => Models\PayrollEntry::class, 'module' => 'payroll', 'title' => 'Payroll', 'initial' => 'draft', 'states' => ['draft', 'approved', 'paid', 'cancelled'], 'fields' => [
        'name' => $text('Payroll description'), 'staff_member_id' => $ref('Staff member', 'staff'), 'period' => ['label' => 'Pay month', 'type' => 'month', 'required' => true],
        'gross' => $money('Gross pay', 'gross_minor'), 'allowances' => $money('Allowances', 'allowances_minor'), 'deductions' => $money('Deductions', 'deductions_minor'), 'currency' => $currency, 'paid_on' => $date('Payment date', false)]],
    'appointments' => ['model' => Models\Appointment::class, 'module' => 'calendar', 'title' => 'Appointments and viewings', 'initial' => 'scheduled', 'states' => ['scheduled', 'completed', 'cancelled'], 'fields' => [
        'name' => $text('Appointment title'), 'property_id' => $ref('Property', 'properties', false), 'contact_id' => $ref('Client', 'contacts', false),
        'starts_at' => ['label' => 'Starts (configured local time)', 'type' => 'datetime-local', 'required' => true], 'ends_at' => ['label' => 'Ends', 'type' => 'datetime-local', 'required' => true],
        'reminder_at' => ['label' => 'Staff reminder time', 'type' => 'datetime-local'], 'notes' => $area('Meeting details')]],
    'referrals' => ['model' => Models\Referral::class, 'module' => 'referrals', 'title' => 'Referral network', 'initial' => 'submitted', 'states' => ['submitted', 'accepted', 'declined', 'completed', 'cancelled'], 'fields' => [
        'name' => $text('Opportunity'), 'target_workspace_id' => ['label' => 'Receiving organisation', 'type' => 'organisation', 'required' => true],
        'client_name' => $text('Client name'), 'client_email' => ['label' => 'Client email', 'type' => 'email'], 'terms' => $area('Proposed referral terms', true),
        'commission_percent' => ['label' => 'Agreed / proposed commission percentage', 'type' => 'percent', 'required' => true],
        'sharing_consent' => ['label' => 'I have permission to share this client’s details with the selected organisation', 'type' => 'consent', 'required' => true], 'notes' => $area('Notes')]],
    'services' => ['model' => Models\ServiceListing::class, 'module' => 'provider', 'title' => 'Professional services', 'initial' => 'draft', 'states' => ['draft', 'published', 'archived'], 'fields' => [
        'name' => $text('Professional / business name'), 'profession' => $choice('Profession', ['lawyer', 'surveyor', 'architect', 'interior_designer', 'photographer', 'drone_operator', 'valuer', 'construction_consultant', 'other']),
        'location' => $text('Location served'), 'description' => $area('Services and experience', true), 'fee' => $money('Indicative starting fee', 'fee_minor'), 'currency' => $currency]],
    'bookings' => ['model' => Models\ServiceBooking::class, 'module' => 'services', 'title' => 'Service requests', 'initial' => 'requested', 'states' => ['requested', 'quoted', 'accepted', 'completed', 'declined', 'cancelled'], 'fields' => [
        'name' => $text('Request title'), 'service_listing_id' => ['label' => 'Professional', 'type' => 'public_service', 'required' => true], 'description' => $area('Work required', true), 'requested_on' => $date('Preferred date'), 'sharing_consent' => ['label' => 'Share my account name and email with this professional to discuss this request', 'type' => 'consent', 'required' => true]]],
    'projects' => ['model' => Models\DevelopmentProject::class, 'module' => 'construction', 'title' => 'Development projects', 'initial' => 'planning', 'states' => ['planning', 'in_progress', 'completed', 'on_hold'], 'fields' => [
        'name' => $text('Project name'), 'location' => $text('Location'), 'description' => $area('Project brief'), 'starts_on' => $date('Start date', false), 'target_date' => $date('Target completion', false),
        'budget' => $money('Project budget', 'budget_minor'), 'currency' => $currency]],
    'progress' => ['model' => Models\ConstructionUpdate::class, 'module' => 'construction', 'title' => 'Construction progress', 'fields' => [
        'name' => $text('Milestone / update'), 'project_id' => $ref('Project', 'projects'), 'recorded_on' => $date('Record date'),
        'progress' => ['label' => 'Completion percentage', 'type' => 'progress', 'required' => true], 'description' => $area('Work completed', true), 'cost' => $money('Recorded milestone cost', 'cost_minor'), 'currency' => $currency]],
];
