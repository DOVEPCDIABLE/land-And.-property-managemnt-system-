<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'reference' => (string) Str::ulid(), 'name' => 'Rent invoice', 'contact_id' => Contact::factory(), 'items' => [['description' => 'Rent', 'quantity' => 1, 'unit_minor' => 10000, 'line_minor' => 10000]], 'subtotal_minor' => 10000, 'total_minor' => 10000, 'issued_on' => today(), 'due_on' => today()->addMonth(), 'status' => 'draft'];
    }
}
