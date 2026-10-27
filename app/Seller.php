<?php

namespace App;

use App\Scopes\SellerScope;

class Seller extends User
{
    /**
     * Buyers and sellers are listed publicly, so only expose public profile fields.
     *
     * @var array
     */
    protected $hidden = [
        'email',
        'password',
        'remember_token',
        'verified',
        'verification_token',
        'admin'
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new SellerScope());
    }
}
