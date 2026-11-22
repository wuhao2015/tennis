<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);
        Auth::login(User::create($data));
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => '邮箱或密码有误。']);
        }
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
