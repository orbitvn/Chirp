<?php

namespace App\Http\Controllers;

use App\Models\Line;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LineController extends Controller
{
    private function ensureAuth(): bool
    {
        return (bool) Session::get('auth_user');
    }

    /**
     * Return all lines as JSON.
     */
    public function index()
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json(
            Line::orderBy('created_at')->get()
        );
    }

    /**
     * Store a new line.
     */
    public function store(Request $request)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'name'            => 'required|string|max:120',
            'description'     => 'nullable|string|max:1000',
            'value'           => 'nullable|numeric',
            'color'           => 'required|string|max:20',
            'coordinates'     => 'required|array|min:2',
            'coordinates.*'   => 'array|size:2',
            'coordinates.*.*' => 'numeric',
        ]);

        $line = Line::create($data);

        return response()->json($line, 201);
    }

    /**
     * Update an existing line (name/color/value and/or reshaped geometry).
     */
    public function update(Request $request, Line $line)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'name'            => 'sometimes|required|string|max:120',
            'description'     => 'sometimes|nullable|string|max:1000',
            'value'           => 'sometimes|nullable|numeric',
            'color'           => 'sometimes|required|string|max:20',
            'coordinates'     => 'sometimes|required|array|min:2',
            'coordinates.*'   => 'array|size:2',
            'coordinates.*.*' => 'numeric',
        ]);

        $line->update($data);

        return response()->json($line);
    }

    /**
     * Delete a line.
     */
    public function destroy(Line $line)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $line->delete();

        return response()->json(['deleted' => true]);
    }
}
