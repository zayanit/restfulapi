<?php

namespace Database\Factories;

use App\Product;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'description' => $this->faker->paragraph(1),
            'quantity' => $this->faker->numberBetween(1, 10),
            'status' => $this->faker->randomElement([Product::AVAILABLE_PRODUCT, Product::UNAVAILABLE_PRODUCT]),
            'image' => $this->faker->randomElement(['1.jpg', '2.jpg', '3.jpg', '4.jpg']),
            // picks an existing user, so seed users before products
            'seller_id' => fn () => User::inRandomOrder()->value('id')
        ];
    }
}
