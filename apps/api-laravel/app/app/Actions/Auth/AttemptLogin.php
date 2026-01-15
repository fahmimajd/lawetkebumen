<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttemptLogin
{
    /**
     * @param array{email:string,password:string} $credentials
     */
    public function handle(array $credentials, Request $request): ?User
    {
        $attempt = array_merge($credentials, ['is_active' => true]);

        if (! Auth::attempt($attempt)) {
            return null;
        }

        $request->session()->regenerate();

        return $request->user();
    }
}
