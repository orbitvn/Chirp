<?php

namespace App\Http\Controllers;

use App\Models\FwpOverride;
use App\Models\RammFwp;
use App\Models\RammFwpTreatment;
use App\Support\Council;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FwpController extends Controller
{
    private function ensureAuth(): bool
    {
        return (bool) Session::get('auth_user');
    }

    private function user(): ?string
    {
        $u = Session::get('auth_user');
        if (is_array($u)) {
            return $u['email'] ?? $u['name'] ?? null;
        }
        return is_string($u) ? $u : null;
    }

    /**
     * Run $fn in the council the client says the edit belongs to (edits can be
     * queued offline and flushed after the user has switched council).
     */
    private function inCouncil(Request $request, Closure $fn): mixed
    {
        $slug = $request->input('council');
        return Council::exists($slug) ? Council::using($slug, $fn) : $fn();
    }

    /** The FWP treatment vocabulary (cycle-button options + rates). */
    public function treatments()
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        return response()->json(
            RammFwpTreatment::orderBy('seq')->get(['code', 'category', 'asset_type', 'ra1_rate', 'ra2_rate'])
        );
    }

    /** Upsert a single override. */
    public function saveOverride(Request $request)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        try {
            $ov = $this->inCouncil($request, fn () => $this->upsert($request->all()));
            return response()->json(['ok' => true, 'override' => $ov]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Batch upsert (flush the offline queue). Body: { items: [ ... ] }. */
    public function sync(Request $request)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $items = $request->input('items', []);
        $saved = 0; $errors = [];
        $this->inCouncil($request, function () use ($items, &$saved, &$errors) {
            foreach ((is_array($items) ? $items : []) as $i => $data) {
                try { $this->upsert($data); $saved++; }
                catch (Throwable $e) { $errors[$i] = $e->getMessage(); }
            }
        });
        return response()->json(['ok' => true, 'saved' => $saved, 'errors' => $errors]);
    }

    /** Remove an override (revert a segment to the RAMM baseline). */
    public function deleteOverride(Request $request)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $n = $this->inCouncil($request, function () use ($request) {
            $q = FwpOverride::query();
            if ($request->filled('id')) {
                $q->where('id', (int) $request->input('id'));
            } else {
                $q->where('road_id', (int) $request->input('road_id'));
                if ($request->filled('treat_length_id')) {
                    $q->where('treat_length_id', (int) $request->input('treat_length_id'));
                } else {
                    $q->whereNull('treat_length_id')
                      ->where('start_m', (float) $request->input('start_m'))
                      ->where('end_m', (float) $request->input('end_m'));
                }
            }
            return $q->delete();
        });
        return response()->json(['ok' => true, 'deleted' => $n]);
    }

    /** All proposed changes joined with their RAMM baseline, for review. */
    public function changes()
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        return response()->json(['changes' => $this->changeRows()]);
    }

    /** CSV of proposed changes for handoff to the RAMM update. */
    public function changesCsv(): StreamedResponse
    {
        $rows = $this->ensureAuth() ? $this->changeRows() : [];
        $cols = ['road_id', 'treat_length_id', 'start_m', 'end_m',
                 'base_treatment', 'treatment', 'base_year', 'year',
                 'note', 'user', 'updated_at'];

        return response()->streamDownload(function () use ($rows, $cols) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $cols);
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($c) => $r[$c] ?? '', $cols));
            }
            fclose($out);
        }, 'fwp-proposed-changes-' . Council::current() . '.csv', ['Content-Type' => 'text/csv']);
    }

    // --- helpers -----------------------------------------------------------

    private function upsert(array $d): FwpOverride
    {
        $roadId = (int) ($d['road_id'] ?? 0);
        if ($roadId <= 0) {
            throw new \InvalidArgumentException('road_id required');
        }
        $tlId = isset($d['treat_length_id']) && $d['treat_length_id'] !== null && $d['treat_length_id'] !== ''
            ? (int) $d['treat_length_id'] : null;

        // Baseline snapshot from RAMM (matched by segment), for diffing in review.
        $base = RammFwp::where('road_id', $roadId)
            ->when($tlId !== null, fn ($q) => $q->where('treat_length_id', $tlId))
            ->when($tlId === null && isset($d['start_m']), fn ($q) => $q->where('start_m', (float) $d['start_m']))
            ->first();

        $year = isset($d['year']) ? (string) $d['year'] : null;

        $attrs = [
            'start_m'        => isset($d['start_m']) ? (float) $d['start_m'] : ($base->start_m ?? null),
            'end_m'          => isset($d['end_m']) ? (float) $d['end_m'] : ($base->end_m ?? null),
            'base_year'      => $base->fw_year ?? ($d['base_year'] ?? null),
            'base_treatment' => $base->treatment ?? ($d['base_treatment'] ?? null),
            'year'           => $year,
            'year_start'     => $this->fyStart($year),
            'treatment_id'   => $d['treatment_id'] ?? null,
            'treatment'      => $d['treatment'] ?? ($d['treatment_id'] ?? null),
            'note'           => $d['note'] ?? null,
            'user'           => $this->user(),
        ];

        if ($tlId !== null) {
            return FwpOverride::updateOrCreate(
                ['road_id' => $roadId, 'treat_length_id' => $tlId],
                $attrs
            );
        }

        // From-scratch segment (no RAMM baseline): key on RP range.
        return FwpOverride::updateOrCreate(
            ['road_id' => $roadId, 'treat_length_id' => null,
             'start_m' => $attrs['start_m'], 'end_m' => $attrs['end_m']],
            $attrs
        );
    }

    private function changeRows(): array
    {
        return FwpOverride::orderBy('road_id')->orderBy('start_m')->get()
            ->map(fn ($o) => [
                'road_id'         => $o->road_id,
                'treat_length_id' => $o->treat_length_id,
                'start_m'         => $o->start_m,
                'end_m'           => $o->end_m,
                'base_treatment'  => $o->base_treatment,
                'treatment'       => $o->treatment,
                'base_year'       => $o->base_year,
                'year'            => $o->year,
                'note'            => $o->note,
                'user'            => $o->user,
                'updated_at'      => optional($o->updated_at)->toDateTimeString(),
            ])->all();
    }

    private function fyStart(?string $fy): ?int
    {
        if (! $fy || ! preg_match('/(\d{4})/', $fy, $m)) {
            return null;
        }
        return (int) $m[1];
    }
}
