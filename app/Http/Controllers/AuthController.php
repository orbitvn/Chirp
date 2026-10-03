<?php

namespace App\Http\Controllers;

use App\Support\Council;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    // Demo credentials — swap these out for real DB auth when ready
    private const DEMO_EMAIL    = 'demo@chirp.com';
    private const DEMO_PASSWORD = 'password';

    public function showLogin()
    {
        // Already logged in? Go to dashboard
        if (Session::get('auth_user')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        $email    = $request->input('email');
        $password = $request->input('password');

        // Demo auth check — replace with Auth::attempt() once you have a DB
        if ($email === self::DEMO_EMAIL && $password === self::DEMO_PASSWORD) {
            Session::put('auth_user', [
                'email' => $email,
                'name'  => 'Marius',
            ]);

            return redirect()->route('dashboard');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'These credentials do not match our records.']);
    }

    public function dashboard()
    {
        $user = Session::get('auth_user');

        if (! $user) {
            return redirect()->route('login');
        }

        return view('dashboard', compact('user'));
    }

    public function maps()
    {
        $user = Session::get('auth_user');

        if (! $user) {
            return redirect()->route('login');
        }

        $council  = Council::current();
        $councils = Council::all();

        return view('maps', compact('user', 'council', 'councils'));
    }

    public function logout(Request $request)
    {
        Session::forget('auth_user');

        return redirect()->route('login');
    }
}
