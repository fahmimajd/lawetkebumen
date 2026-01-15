<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Actions\Auth\Logout;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AttemptLogin $action): RedirectResponse
    {
        $user = $action->handle($request->validated(), $request);

        if (! $user) {
            return back()
                ->withErrors(['email' => 'Invalid credentials.'])
                ->onlyInput('email');
        }

        return redirect()->intended('/inbox');
    }

    public function destroy(Request $request, Logout $action): RedirectResponse
    {
        $action->handle($request);

        return redirect()->route('login');
    }
}
