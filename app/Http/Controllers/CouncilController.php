<?php

namespace App\Http\Controllers;

use App\Support\Council;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CouncilController extends Controller
{
    /** Switch the council the maps / RAMM / FWP pages work on. */
    public function switch(Request $request)
    {
        if (! Session::get('auth_user')) {
            return redirect()->route('login');
        }

        $slug = (string) $request->input('council');
        if (! Council::exists($slug)) {
            return back()->withErrors(['council' => 'Unknown council.']);
        }

        Council::choose($slug);
        return back();
    }
}
