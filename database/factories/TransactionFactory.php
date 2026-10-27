<?php

namespace Database\Factories;

use App\Seller;
use App\Transaction;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        // picks existing sellers/products, so seed products before transactions
        $seller = Seller::inRandomOrder()->first();

        return [
            'quantity' => $this->faker->numberBetween(1, 3),
            'buyer_id' => User::where('id', '!=', $seller->id)->inRandomOrder()->value('id'),
            'product_id' => $seller->products()->inRandomOrder()->value('id')
        ];
    }
}
