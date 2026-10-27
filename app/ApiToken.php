<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    protected $fillable = ['token'];

    protected $hidden = ['token'];

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function findByPlainToken(?string $plainToken): ?ApiToken
    {
        return $plainToken ? static::where('token', static::hash($plainToken))->first() : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
