<?php

return [
    'display_timezone' => 'Africa/Lagos',
    'panels' => ['platform' => 'Platform administration', 'agency' => 'Agency workspace', 'agent' => 'Agent workspace', 'client' => 'Client portal'],
    'roles' => [
        'platform_admin' => ['panel.platform', 'panel.agency', 'panel.agent', 'panel.client', 'users.manage', 'notifications.send', 'followups.manage', 'payments.manage', 'finance.record', 'integrations.view'],
        'agency_admin' => ['panel.agency', 'users.manage', 'notifications.send', 'followups.manage', 'payments.manage', 'finance.record', 'integrations.view'],
        'agent' => ['panel.agent', 'followups.manage'],
        'accountant' => ['panel.agency', 'payments.manage', 'finance.record'],
        'client' => ['panel.client'],
    ],
    'modules' => [
        ['name' => 'Property sales', 'description' => 'Listings, media, specifications, agents and inquiries.', 'flagship' => false],
        ['name' => 'Property rentals', 'description' => 'Rental availability, viewing requests and tenant inquiries.', 'flagship' => false],
        ['name' => 'CRM', 'description' => 'Clients, preferences, communication history and lead tracking.', 'flagship' => true],
        ['name' => 'Automated follow-up', 'description' => 'Scheduled notifications are ready; campaigns, SMS and WhatsApp come next.', 'flagship' => true],
        ['name' => 'Lead discovery', 'description' => 'Source connectors, owner discovery and duplicate detection.', 'flagship' => true],
        ['name' => 'Property valuation', 'description' => 'Comparable properties, estimates and valuation reports.', 'flagship' => true],
        ['name' => 'AI assistant', 'description' => 'Descriptions, client responses, matching and pricing suggestions.', 'flagship' => true],
        ['name' => 'Agent management', 'description' => 'Assignments, commissions, activity and performance.', 'flagship' => false],
        ['name' => 'Property management', 'description' => 'Tenants, leases, maintenance, inspections and occupancy.', 'flagship' => false],
        ['name' => 'Receipts & invoices', 'description' => 'Payment records, printable invoices and receipts with partial-payment balances.', 'flagship' => false],
        ['name' => 'Finance & business', 'description' => 'Revenue, expenses, payroll, branches and reporting.', 'flagship' => false],
        ['name' => 'Documents', 'description' => 'Contracts, ownership records, leases and reports.', 'flagship' => false],
        ['name' => 'Appointments & viewings', 'description' => 'Calendars, bookings and reminders.', 'flagship' => false],
        ['name' => 'Referral network', 'description' => 'Agency and agent referrals with attribution and reporting.', 'flagship' => true],
        ['name' => 'Service marketplace', 'description' => 'Lawyers, surveyors, architects and other property professionals.', 'flagship' => false],
        ['name' => 'Construction & development', 'description' => 'Projects, unit inventory, progress and sales.', 'flagship' => false],
    ],
];
