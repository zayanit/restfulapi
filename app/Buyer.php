<?php

namespace App;

use App\Scopes\BuyerScope;

class Buyer extends User
{
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new BuyerScope());
    }
}
