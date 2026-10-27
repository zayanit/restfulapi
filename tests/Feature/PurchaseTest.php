<?php

namespace Tests\Feature;

use App\Product;
use App\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ApiTestCase;

class PurchaseTest extends ApiTestCase
{
    public function testPurchaseDecrementsStockAndRecordsBuyer(): void
    {
        $product = $this->product($this->user());
        $buyer = $this->user();

        $this->actingAs($buyer, 'api')->postJson("/api/products/{$product->id}/buyers/{$buyer->id}/transactions", [
            'quantity' => 2,
        ])->assertCreated()->assertJsonPath('data.buyer_id', $buyer->id);

        $this->assertSame(1, $product->fresh()->quantity);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function testFinalPurchaseMakesProductUnavailable(): void
    {
        $product = $this->product($this->user(), ['quantity' => 1]);
        $buyer = $this->user();
        $this->actingAs($buyer, 'api')->postJson("/api/products/{$product->id}/buyers/{$buyer->id}/transactions", [
            'quantity' => 1,
        ])->assertCreated();
        $this->assertSame(0, $product->fresh()->quantity);
        $this->assertSame(Product::UNAVAILABLE_PRODUCT, $product->fresh()->status);
    }

    public function testInsufficientStockDoesNotCreateTransactionOrChangeQuantity(): void
    {
        $product = $this->product($this->user(), ['quantity' => 1]);
        $buyer = $this->user();
        $this->actingAs($buyer, 'api')->postJson("/api/products/{$product->id}/buyers/{$buyer->id}/transactions", [
            'quantity' => 2,
        ])->assertStatus(409);
        $this->assertSame(1, $product->fresh()->quantity);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function testCannotPurchaseForAnotherBuyer(): void
    {
        $product = $this->product($this->user());
        $buyer = $this->user();
        $this->actingAs($this->user(), 'api')->postJson("/api/products/{$product->id}/buyers/{$buyer->id}/transactions", [
            'quantity' => 1,
        ])->assertForbidden();
        $this->assertSame(3, $product->fresh()->quantity);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function testUnverifiedBuyerCannotPurchase(): void
    {
        $product = $this->product($this->user());
        $buyer = $this->user(['verified' => User::UNVERIFIED_USER]);
        $this->actingAs($buyer, 'api')->postJson("/api/products/{$product->id}/buyers/{$buyer->id}/transactions", [
            'quantity' => 1,
        ])->assertStatus(409);
        $this->assertSame(3, $product->fresh()->quantity);
    }

    public function testSellerCannotPurchaseOwnProduct(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $this->actingAs($seller, 'api')->postJson("/api/products/{$product->id}/buyers/{$seller->id}/transactions", [
            'quantity' => 1,
        ])->assertStatus(409);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function testUnverifiedSellerCannotReceivePurchase(): void
    {
        $product = $this->product($this->user(['verified' => User::UNVERIFIED_USER]));
        $buyer = $this->user();
        $this->actingAs($buyer, 'api')->postJson("/api/products/{$product->id}/buyers/{$buyer->id}/transactions", [
            'quantity' => 1,
        ])->assertStatus(409);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function testUnavailableProductCannotBePurchased(): void
    {
        $product = $this->product($this->user(), ['status' => Product::UNAVAILABLE_PRODUCT]);
        $buyer = $this->user();
        $this->actingAs($buyer, 'api')->postJson("/api/products/{$product->id}/buyers/{$buyer->id}/transactions", [
            'quantity' => 1,
        ])->assertStatus(409);
        $this->assertDatabaseCount('transactions', 0);
    }

    public static function invalidQuantities(): array
    {
        return [[0], [-1], [1.5], ['invalid'], [null]];
    }

    #[DataProvider('invalidQuantities')]
    public function testInvalidQuantityIsRejected($quantity): void
    {
        $product = $this->product($this->user());
        $buyer = $this->user();
        $this->actingAs($buyer, 'api')->postJson("/api/products/{$product->id}/buyers/{$buyer->id}/transactions", [
            'quantity' => $quantity,
        ])->assertUnprocessable();
        $this->assertSame(3, $product->fresh()->quantity);
        $this->assertDatabaseCount('transactions', 0);
    }
}
