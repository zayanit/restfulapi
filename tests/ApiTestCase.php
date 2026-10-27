<?php

namespace Tests;

use App\ApiToken;
use App\Product;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'verified' => User::VERIFIED_USER,
            'verification_token' => null,
            'admin' => User::REGULAR_USER,
        ], $attributes));
    }

    protected function issueToken(User $user, string $plainToken): ApiToken
    {
        return $user->apiTokens()->create(['token' => ApiToken::hash($plainToken)]);
    }

    protected function product(User $seller, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'seller_id' => $seller->id,
            'status' => Product::AVAILABLE_PRODUCT,
            'quantity' => 3,
        ], $attributes));
    }
}
