<?php

namespace Database\Factories;

use App\Models\PropertyMedia;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyMedia> */
class PropertyMediaFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => 'Property photograph', 'path' => 'media/house.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'image'];
    }
}
