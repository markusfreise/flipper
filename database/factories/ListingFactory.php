<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Listing> */
class ListingFactory extends Factory
{
    protected $model = Listing::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'photos',
        ];
    }
}
