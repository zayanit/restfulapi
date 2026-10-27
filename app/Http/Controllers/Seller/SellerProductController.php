<?php

namespace App\Http\Controllers\Seller;

use App\Seller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Product;
use App\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SellerProductController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Seller $seller)
    {
        $products = $seller->products;

        return $this->showAll($products);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, User $seller)
    {
        $this->authorize('update', $seller);

        $rules = [
            'name' => 'required|string',
            'description' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'image' => 'required|image'
        ];

        $this->validate($request, $rules);

        $data = $request->only(['name', 'description', 'quantity']);

        $data['status'] = Product::UNAVAILABLE_PRODUCT;
        $data['image'] = $this->storeImage($request);
        $data['seller_id'] = $seller->id;

        try {
            $product = Product::create($data);
        } catch (Throwable $exception) {
            Storage::disk('images')->delete($data['image']);
            throw $exception;
        }

        return $this->showOne($product);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Seller  $seller
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Seller $seller, Product $product)
    {
        $this->authorize('update', $product);
        $this->checkSeller($seller, $product);

        $rules = [
            'quantity' => 'integer|min:1',
            'name' => 'sometimes|required|string',
            'description' => 'sometimes|required|string',
            'status' => 'in:' . Product::AVAILABLE_PRODUCT . ',' . Product::UNAVAILABLE_PRODUCT,
            'image' => 'image'
        ];

        $this->validate($request, $rules);

        $product->fill($request->only([
            'name',
            'description',
            'quantity'
        ]));

        if ($request->has('status')) {
            $product->status = $request->status;

            if ($product->isAvailable() && $product->categories()->count() == 0) {
                return $this->errorResponse('An active product must have at least one category', 409);
            }
        }

        if ($product->isClean() && ! $request->hasFile('image')) {
            return $this->errorResponse('You need to specify a different value to update', 422);
        }

        $originalImage = $product->image;
        $replacement = $request->hasFile('image') ? $this->storeImage($request) : null;
        if ($replacement !== null) {
            $product->image = $replacement;
        }

        try {
            DB::transaction(fn () => $product->save());
        } catch (Throwable $exception) {
            if ($replacement !== null) {
                Storage::disk('images')->delete($replacement);
            }
            throw $exception;
        }

        if ($replacement !== null) {
            $this->deleteImageIfUnused($originalImage);
        }

        return $this->showOne($product);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Seller  $seller
     * @return \Illuminate\Http\Response
     */
    public function destroy(Seller $seller, Product $product)
    {
        $this->authorize('delete', $product);

        $this->checkSeller($seller, $product);

        // soft delete only, so keep the image for a possible restore
        $product->delete();

        return $this->showOne($product);
    }

    public function checkSeller(Seller $seller, Product $product)
    {
        if ($seller->id != $product->seller_id) {
            throw new HttpException(422, 'The specified seller is not the actual seller of the product');
        }
    }

    private function deleteImageIfUnused(string $image): void
    {
        $inUse = in_array($image, Product::SAMPLE_IMAGES)
            || Product::withTrashed()->where('image', $image)->exists();

        if (! $inUse) {
            Storage::disk('images')->delete($image);
        }
    }

    private function storeImage(Request $request): string
    {
        $path = $request->file('image')->store('', 'images');
        if ($path === false) {
            throw new RuntimeException('Unable to store product image');
        }

        return $path;
    }
}
