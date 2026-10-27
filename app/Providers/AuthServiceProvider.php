<?php

namespace App\Providers;

use App\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        \App\User::class => \App\Policies\UserPolicy::class,
        \App\Product::class => \App\Policies\ProductPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // each login issues its own token row, so several clients can stay signed in at once
        Auth::viaRequest('api-token', fn (Request $request) => ApiToken::findByPlainToken($request->bearerToken())?->user);

        Gate::define('manage-categories', fn (\App\User $user) => $user->isAdmin());
    }
}
