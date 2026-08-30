<?php

namespace App\Http\Controllers;

use App\Models\Point;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class PointController extends Controller
{
    /**
     * Guard: only signed-in (demo) users may touch points.
     */
    private function ensureAuth()
    {
        return (bool) Session::get('auth_user');
    }

    /**
     * Return all points as JSON.
     */
    public function index()
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json(
            Point::orderBy('created_at')->get()
        );
    }

    /**
     * Store a new point.
     */
    public function store(Request $request)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'value'       => 'nullable|numeric',
            'color'       => 'required|string|max:20',
            'lat'         => 'required|numeric|between:-90,90',
            'lng'         => 'required|numeric|between:-180,180',
        ]);

        $point = Point::create($data);

        return response()->json($point, 201);
    }

    /**
     * Delete a point.
     */
    public function destroy(Point $point)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $point->delete();

        return response()->json(['deleted' => true]);
    }
}
