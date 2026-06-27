<?php

namespace App;

use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends ApiModel
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description'
    ];

    protected $hidden = ['pivot'];

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}
