<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Receipt> */
class ReceiptFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'reference' => (string) Str::ulid(), 'name' => 'Payment receipt', 'invoice_id' => Invoice::factory(), 'payment_id' => Payment::factory(), 'amount_minor' => 10000, 'received_on' => today()];
    }
}
