<?php

namespace Database\Factories;

use App\Models\BusinessDocument;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessDocument> */
class BusinessDocumentFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => 'Lease document', 'category' => 'lease', 'path' => 'documents/example.pdf', 'original_name' => 'example.pdf', 'mime_type' => 'application/pdf', 'size' => 100];
    }
}
