<?php

namespace App\Http\Controllers\Auth;

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
        Auth::guard('web')->user()->forceFill(['api_token' => hash('sha256', $token)])->save();

        return response()->json(['data' => ['token' => $token, 'token_type' => 'Bearer']]);
    }

    public function destroy(Request $request)
    {
        $request->user()->forceFill(['api_token' => null])->save();

        return response()->json(['data' => ['message' => 'Logged out']]);
    }
}
