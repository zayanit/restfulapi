<?php

namespace App\Http\Controllers\Auth;

use App\ApiToken;
use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ApiTokenController extends ApiController
{
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
        $credentials['email'] = strtolower($credentials['email']);

        if (! Auth::guard('web')->once($credentials)) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        $token = Str::random(80);
        Auth::guard('web')->user()->apiTokens()->create(['token' => ApiToken::hash($token)]);

        return $this->showData(['token' => $token, 'token_type' => 'Bearer']);
    }

    public function destroy(Request $request)
    {
        ApiToken::findByPlainToken($request->bearerToken())?->delete();

        return $this->showData(['message' => 'Logged out']);
    }
}
