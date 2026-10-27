<?php

namespace App;

use App\Scopes\BuyerScope;

class Buyer extends User
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
