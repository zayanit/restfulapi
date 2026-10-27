<?php

namespace Tests\Feature;

use App\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\ApiTestCase;

class ProductImageTest extends ApiTestCase
{
    public function testOwnerCanReplaceOnlyTheImage(): void
    {
        Storage::fake('images');
        Storage::disk('images')->put('old.jpg', 'original');
        $seller = $this->user();
        $product = $this->product($seller, ['image' => 'old.jpg']);

        $this->actingAs($seller, 'api')->postJson("/api/sellers/{$seller->id}/products/{$product->id}", [
            '_method' => 'PATCH', 'image' => UploadedFile::fake()->image('new.jpg'),
        ])->assertOk();

        Storage::disk('images')->assertExists($product->fresh()->image);
        Storage::disk('images')->assertMissing('old.jpg');
    }

    public function testDatabaseFailurePreservesOriginalAndCleansReplacement(): void
    {
        Storage::fake('images');
        Storage::disk('images')->put('old.jpg', 'original');
        $seller = $this->user();
        $product = $this->product($seller, ['image' => 'old.jpg']);
        Product::updating(function () {
            throw new RuntimeException('Simulated database failure');
        });

        try {
            $this->actingAs($seller, 'api')->postJson("/api/sellers/{$seller->id}/products/{$product->id}", [
                '_method' => 'PATCH', 'name' => 'Changed', 'image' => UploadedFile::fake()->image('new.jpg'),
            ])->assertStatus(500);
        } finally {
            Product::clearBootedModels();
        }

        $this->assertSame('old.jpg', $product->fresh()->image);
        $this->assertSame(['old.jpg'], Storage::disk('images')->allFiles());
    }

    public function testAnotherUserCannotModifyProductOrImage(): void
    {
        Storage::fake('images');
        $seller = $this->user();
        $product = $this->product($seller);
        $this->actingAs($this->user(), 'api')->postJson("/api/sellers/{$seller->id}/products/{$product->id}", [
            '_method' => 'PATCH', 'image' => UploadedFile::fake()->image('new.jpg'),
        ])->assertForbidden();
        $this->assertEmpty(Storage::disk('images')->allFiles());
    }

    public function testFailedUploadKeepsOriginalImage(): void
    {
        $disk = Storage::fake('images');
        $disk->put('old.jpg', 'original');
        $seller = $this->user();
        $product = $this->product($seller, ['image' => 'old.jpg']);
        $failingDisk = \Mockery::mock($disk)->makePartial();
        $failingDisk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::set('images', $failingDisk);

        $this->actingAs($seller, 'api')->postJson("/api/sellers/{$seller->id}/products/{$product->id}", [
            '_method' => 'PATCH', 'image' => UploadedFile::fake()->image('new.jpg'),
        ])->assertStatus(500);

        $this->assertSame('old.jpg', $product->fresh()->image);
        $this->assertSame(['old.jpg'], $disk->allFiles());
    }

    public function testFirstTimeSellerCanCreateOwnProduct(): void
    {
        Storage::fake('images');
        $seller = $this->user();
        $this->actingAs($seller, 'api')->postJson("/api/sellers/{$seller->id}/products", [
            'name' => 'First product', 'description' => 'Description', 'quantity' => 2,
            'image' => UploadedFile::fake()->image('new.jpg'),
        ])->assertOk()->assertJsonPath('data.seller_id', $seller->id);
        $product = Product::firstOrFail();
        Storage::disk('images')->assertExists($product->image);
    }
}
