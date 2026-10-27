<?php

namespace Tests\Feature;

use App\Transaction;
use App\User;
use Tests\ApiTestCase;

class PublicProfileTest extends ApiTestCase
{
    public static function publicUserEndpoints(): array
    {
        return [
            ['/api/buyers'], ['/api/sellers'], ['/api/products/{product}/buyers'],
            ['/api/categories/{category}/sellers'], ['/api/transactions/{transaction}/sellers', 'data'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicUserEndpoints')]
    public function testPublicBuyerAndSellerListingsHidePrivateFields(string $uri, string $path = 'data.0'): void
    {
        $seller = $this->user(['admin' => User::ADMIN_USER]);
        $product = $this->product($seller);
        $category = \App\Category::factory()->create();
        $product->categories()->attach($category);
        $transaction = Transaction::create([
            'quantity' => 1, 'buyer_id' => $this->user()->id, 'product_id' => $product->id,
        ]);

        $uri = str_replace(
            ['{product}', '{category}', '{transaction}'],
            [$product->id, $category->id, $transaction->id],
            $uri
        );

        $response = $this->getJson($uri)->assertOk();
        foreach (['email', 'admin', 'verified', 'password', 'verification_token'] as $field) {
            $response->assertJsonMissingPath("{$path}.{$field}");
        }
        $this->assertNotNull($response->json("{$path}.name"));
    }
}
