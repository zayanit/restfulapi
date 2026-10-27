<?php

namespace App\Http\Controllers\Product;

use App\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Transaction;
use App\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductBuyerTransactionController extends ApiController
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Product $product, User $buyer)
    {
        $this->checkAddingPossibility($request, $product, $buyer);        

        return DB::transaction(function() use ($request, $product, $buyer) {
            $product->quantity -= $request->quantity;
            $product->save();

            $transaction = Transaction::create([
                'quantity' => $request->quantity,
                'buyer_id' => $buyer->id,
                'product_id' => $product->id
            ]);

            return $this->showOne($transaction, 201);
        });
        
    }

    protected function checkAddingPossibility(Request $request, Product $product, User $buyer)
    {
        $rules = [
            'quantity' => 'required|integer|min:1'
        ];

        $this->validate($request, $rules);

        if ($buyer->id == $product->seller->id) {
            throw new HttpException(409, 'The buyer must be different from the seller');
        }

        if (!$buyer->isVerified()) {
            throw new HttpException(409, 'The buyer must be a verified user');
        }

        if (!$product->seller->isVerified()) {
            throw new HttpException(409, 'The seller must be a verified user');
        }

        if (!$product->isAvailable()) {
            throw new HttpException(409, 'The product is not available');
        }

        if ($product->quantity < $request->quantity) {
            throw new HttpException(409, 'The product does not have enough units for this transaction');
        }
    }
}
