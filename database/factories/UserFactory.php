<?php

namespace Database\Factories;

use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        $verified = $this->faker->randomElement([User::UNVERIFIED_USER, User::VERIFIED_USER]);

        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => static::$password ??= Hash::make('secret'),
            'remember_token' => Str::random(10),
            'verified' => $verified,
            'verification_token' => $verified == User::VERIFIED_USER ? null : User::generateVerificationCode(),
            'admin' => $this->faker->randomElement([User::REGULAR_USER, User::ADMIN_USER])
        ];
    }
}
