<?php

namespace App\Policies;

use App\Product;
use App\User;

class ProductPolicy
{
    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin() || $user->id === $product->seller_id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
