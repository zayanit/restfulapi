<?php

namespace Tests\Feature;

use App\Category;
use App\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ApiTestCase;

class AuthorizationTest extends ApiTestCase
{
    public static function otherOwnersEndpoints(): array
    {
        return [
            ['GET', '/api/users/{seller}'],
            ['DELETE', '/api/users/{seller}'],
            ['POST', '/api/sellers/{seller}/products'],
            ['DELETE', '/api/sellers/{seller}/products/{product}'],
            ['PUT', '/api/products/{product}/categories/{category}'],
            ['DELETE', '/api/products/{product}/categories/{category}'],
        ];
    }

    #[DataProvider('otherOwnersEndpoints')]
    public function testUserCannotActOnAnotherOwnersResources(string $method, string $uri): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $category = Category::factory()->create();
        $product->categories()->attach($category);
        $uri = strtr($uri, [
            '{seller}' => $seller->id, '{product}' => $product->id, '{category}' => $category->id,
        ]);

        $this->actingAs($this->user(), 'api')->json($method, $uri)->assertForbidden();
        $this->assertFalse($seller->fresh()->trashed());
        $this->assertFalse($product->fresh()->trashed());
        $this->assertSame(1, $product->categories()->count());
    }

    public function testOwnerCanAttachProductCategory(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $category = Category::factory()->create();

        $this->actingAs($seller, 'api')->putJson("/api/products/{$product->id}/categories/{$category->id}")
            ->assertOk()->assertJsonPath('data.0.id', $category->id);
    }

    public function testOwnerCanDetachProductCategory(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $category = Category::factory()->create();
        $product->categories()->attach($category);

        $this->actingAs($seller, 'api')->deleteJson("/api/products/{$product->id}/categories/{$category->id}")
            ->assertOk()->assertExactJson(['data' => []]);
    }

    public function testAdminCanListAccountsAndUpdateAnotherOwnersProduct(): void
    {
        $admin = $this->user(['admin' => User::ADMIN_USER]);
        $seller = $this->user();
        $product = $this->product($seller);

        $this->actingAs($admin, 'api')->getJson('/api/users')->assertOk();
        $this->patchJson("/api/sellers/{$seller->id}/products/{$product->id}", ['name' => 'Updated by admin'])
            ->assertOk()->assertJsonPath('data.name', 'Updated by admin');
    }

    public function testRegularUserCannotDeleteCategory(): void
    {
        $category = Category::factory()->create();
        $this->actingAs($this->user(), 'api')->deleteJson("/api/categories/{$category->id}")->assertForbidden();
        $this->assertFalse($category->fresh()->trashed());
    }
}
