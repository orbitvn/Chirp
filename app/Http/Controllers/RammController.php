<?php

namespace App\Http\Controllers;

use App\Models\RammRoad;
use App\Services\RammService;
use App\Services\RammSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Throwable;

class RammController extends Controller
{
    private function ensureAuth(): bool
    {
        return (bool) Session::get('auth_user');
    }

    /**
     * Confirm we can authenticate with RAMM.
     */
    public function ping(RammService $ramm)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $ramm->isConfigured()) {
            return response()->json(['ok' => false, 'message' => 'RAMM credentials not set in .env (RAMM_DATABASE / RAMM_USERNAME / RAMM_PASSWORD).']);
        }

        try {
            $token = $ramm->token();
            return response()->json([
                'ok'            => true,
                'authenticated' => true,
                'token_preview' => substr($token, 0, 8) . '…',
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Pull RAMM data for one road so we can inspect RP / direction.
     * Example: /ramm/road/9019  (East Road)
     */
    public function road(Request $request, string $roadId, RammService $ramm)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Add ?geom=1 to test whether RAMM returns carriageway geometry (WKT).
        $geom = $request->boolean('geom');

        try {
            $filter = [RammService::eq('road_id', $roadId)];

            return response()->json([
                'road_id'     => $roadId,
                'roadnames'   => $ramm->queryTable('roadnames', $filter, 10),
                'carriageway' => $ramm->queryTable('carr_way', $filter, 100, $geom),
                'surfacing'   => $ramm->queryTable('top_surface', $filter, 100),
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * RP-ordered road geometry + surfacing history, ready to draw on the map.
     * Example: /ramm/road/9019/works
     */
    public function works(string $roadId, RammSync $sync)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $road = $this->roadFromDb($roadId, $sync);
            if (! $road) {
                return response()->json(['ok' => false, 'message' => 'No RAMM data for that road.']);
            }

            return response()->json([
                'road_id'   => $road->road_id,
                'road_name' => $road->road_name,
                'line'      => $road->line,
                'total_rp'  => $road->total_rp,
                'sections'  => $road->sections,
                'surfacing' => $road->surfacings->map(fn ($s) => [
                    'start_m'       => $s->start_m,
                    'end_m'         => $s->end_m,
                    'surf_offset'   => $s->surf_offset,
                    'surface_date'  => optional($s->surface_date)->toDateString(),
                    'surf_material' => $s->surf_material,
                    'surf_function' => $s->surf_function,
                    'chip_size'     => $s->chip_size,
                    'chip_2nd_size' => $s->chip_2nd_size,
                    'surf_width'    => $s->surf_width,
                    'life'          => $s->life,
                ]),
                'treatment_lengths' => $road->treatmentLengths->map(fn ($t) => [
                    'start_m' => $t->start_m,
                    'end_m'   => $t->end_m,
                    'tl_id'   => $t->tl_id,
                    'name'    => $t->tl_name,
                ]),
                'fwp' => \App\Models\RammFwp::where('road_id', $road->road_id)->get()->map(fn ($f) => [
                    'treat_length_id' => $f->treat_length_id,
                    'start_m'      => $f->start_m,
                    'end_m'        => $f->end_m,
                    'treatment_id' => $f->treatment_id,
                    'treatment'    => $f->treatment,
                    'category'     => $f->category,
                    'fw_year'      => $f->fw_year,
                    'year_start'   => $f->year_start,
                    'reason'       => $f->reason,
                    'cost'         => $f->cost,
                    'locked'       => $f->locked,
                ]),
                'fwp_overrides' => \App\Models\FwpOverride::where('road_id', $road->road_id)->get(),
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Load a road from the local DB, lazily importing it from RAMM if missing.
     */
    private function roadFromDb(string $roadId, RammSync $sync): ?RammRoad
    {
        $road = RammRoad::with(['surfacings', 'treatmentLengths'])->find((int) $roadId);
        if ($road) {
            return $road;
        }

        $sync->importRoad($roadId);
        return RammRoad::with(['surfacings', 'treatmentLengths'])->find((int) $roadId);
    }

    /**
     * Diagnostic: find the real treatment-length table + its column names.
     * Open /ramm/tl-debug/9019 (East Road) and read the JSON.
     */
    public function tlDebug(string $roadId, RammService $ramm)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Fast probe: only road-filtered queries (indexed) on a few likely names.
        $tables = ['treatment_length', 'treat_length', 'treatment_lengths', 'c_treatment_length'];

        $probe = [];
        foreach ($tables as $t) {
            try {
                $res = $ramm->queryTable($t, [RammService::eq('road_id', $roadId)], 3);
                $probe[$t] = [
                    'columns'         => $res['rows'] ? array_keys($res['rows'][0]) : $ramm->getColumns($t),
                    'rows_for_road'   => $res['total'],
                    'sample_for_road' => $res['rows'][0] ?? null,
                ];
            } catch (Throwable $e) {
                $probe[$t] = ['error' => $e->getMessage()];
            }
        }

        return response()->json([
            'road_id' => (int) $roadId,
            'probe'   => $probe,
        ]);
    }

    /**
     * Diagnostic: why is the surfacing-history table empty? Shows the real
     * top_surface columns + sample rows and the treatment-length RP ranges.
     * Open /ramm/road/9019/surf-debug and read the JSON.
     */
    public function surfDebug(string $roadId, RammService $ramm)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $filter = [RammService::eq('road_id', $roadId)];
            $ts     = $ramm->queryTable('top_surface', $filter, 8);
            $tls    = $ramm->roadTreatmentLengths($roadId);

            return response()->json([
                'road_id'              => (int) $roadId,
                'top_surface_total'    => $ts['total'],
                'top_surface_columns'  => $ts['rows'] ? array_keys($ts['rows'][0]) : $ramm->getColumns('top_surface'),
                'top_surface_sample'   => array_slice($ts['rows'], 0, 8),
                'treatment_lengths'    => array_map(
                    fn ($t) => ['start_m' => $t['start_m'], 'end_m' => $t['end_m'], 'name' => $t['name'] ?? null],
                    $tls
                ),
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Diagnostic: run each piece of /works separately and report only sizes/errors,
     * so we can see which sub-call is empty or failing. Open /ramm/road/9019/works-check.
     */
    public function worksCheck(string $roadId, RammService $ramm)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $out = ['road_id' => (int) $roadId];

        $t0 = microtime(true);
        try {
            $geo = $ramm->roadGeometry($roadId);
            $out['line_points'] = count($geo['line']);
            $out['total_rp']    = $geo['total_rp'];
            $out['sections']    = count($geo['sections']);
        } catch (Throwable $e) {
            $out['geometry_error'] = $e->getMessage();
        }
        $out['geometry_secs'] = round(microtime(true) - $t0, 1);

        try {
            $out['surfacing_count'] = count($ramm->queryTable('top_surface', [RammService::eq('road_id', $roadId)], 500)['rows']);
        } catch (Throwable $e) {
            $out['surfacing_error'] = $e->getMessage();
        }

        try {
            $out['treatment_lengths_count'] = count($ramm->roadTreatmentLengths($roadId));
        } catch (Throwable $e) {
            $out['tl_error'] = $e->getMessage();
        }

        return response()->json($out);
    }

    /**
     * List all RAMM table names (to discover e.g. the treatment-length table).
     */
    public function tables(RammService $ramm)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        try {
            return response()->json($ramm->request('GET', 'data/tables', [], ['tableTypes' => 255]));
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Inspect any RAMM table, optionally filtered by road_id.
     * Example: /ramm/table/treatment_length?road_id=9019
     */
    public function table(Request $request, string $table, RammService $ramm)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        try {
            $filters = [];
            if ($request->filled('road_id')) {
                $filters[] = RammService::eq('road_id', $request->query('road_id'));
            }
            return response()->json($ramm->queryTable(
                $table,
                $filters,
                (int) $request->query('take', 50),
                $request->boolean('geom')
            ));
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Discovery: find the Forward Works Programme (proposed treatment + year).
     * Scans treatment_length columns and likely FWP tables for year/treatment
     * fields, flagging the promising ones. Open /ramm/fwp-probe/9019.
     */
    public function fwpProbe(string $roadId, RammService $ramm)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $hint = '/(year|treat|tmt|dtims|programme|program|fwp|activity|scheme|cost|priority|rank)/i';
        $flag = fn (array $cols) => array_values(array_filter($cols, fn ($c) => preg_match($hint, (string) $c)));

        $out = ['road_id' => (int) $roadId];

        // 1) treatment_length — the FWP year/treatment likely already lives here.
        try {
            $res = $ramm->queryTable('treatment_length', [RammService::eq('road_id', $roadId)], 3, false, true);
            $cols = $res['rows'] ? array_keys($res['rows'][0]) : $ramm->getColumns('treatment_length');
            $out['treatment_length'] = [
                'all_columns'     => $cols,
                'likely_fwp_cols' => $flag($cols),
                'sample_row'      => $res['rows'][0] ?? null,
                'rows_for_road'   => $res['total'] ?? 0,
            ];
        } catch (Throwable $e) {
            $out['treatment_length'] = ['error' => $e->getMessage()];
        }

        // 2) Candidate dedicated FWP / treatment-selection tables.
        $candidates = ['fwp', 'forward_work', 'forward_works', 'treatment_selection',
                       'treatment_select', 'pm_treatment', 'programme', 'program', 'work_programme'];
        $probe = [];
        foreach ($candidates as $t) {
            try {
                $res = $ramm->queryTable($t, [RammService::eq('road_id', $roadId)], 3, false, true);
                $cols = $res['rows'] ? array_keys($res['rows'][0]) : $ramm->getColumns($t);
                $probe[$t] = [
                    'exists'          => true,
                    'rows_for_road'   => $res['total'] ?? 0,
                    'likely_fwp_cols' => $flag($cols),
                    'sample_row'      => $res['rows'][0] ?? null,
                ];
            } catch (Throwable $e) {
                $probe[$t] = ['exists' => false, 'error' => $e->getMessage()];
            }
        }
        $out['candidate_tables'] = $probe;

        // 3) Any table whose NAME hints at forward works.
        try {
            $tables = $ramm->request('GET', 'data/tables', [], ['tableTypes' => 255]);
            $names = [];
            foreach ((is_array($tables) ? $tables : []) as $t) {
                $n = is_array($t) ? ($t['tableName'] ?? $t['name'] ?? null) : $t;
                if ($n && preg_match('/(fwp|forward|treat|programme|program|scheme|activity)/i', (string) $n)) {
                    $names[] = $n;
                }
            }
            $out['tables_named_like_fwp'] = array_values(array_unique($names));
        } catch (Throwable $e) {
            $out['tables_named_like_fwp'] = ['error' => $e->getMessage()];
        }

        return response()->json($out);
    }

    /**
     * Discovery pass 2: inspect the specific RAMM forward-works tables that pass 1
     * surfaced, so we can see their columns + a sample for one road. Tries a
     * road_id filter first, falls back to unfiltered (just to read the schema).
     * Open /ramm/fwp-inspect/9019  (override the list with ?tables=a,b,c).
     */
    public function fwpInspect(Request $request, string $roadId, RammService $ramm)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Diagnostic hits several RAMM tables; give it headroom past PHP's 30s.
        @set_time_limit(180);

        // Keep the default small so one request stays well under any time limit.
        // Inspect others in batches with ?tables=a,b,c
        $default = [
            'fw_forward_work_view', 'ud_rfwp_vw_current',
            'ud_fwp_works', 'ud_fwp_validated_treat_year',
        ];
        $tables = $request->filled('tables')
            ? array_filter(array_map('trim', explode(',', $request->query('tables'))))
            : $default;

        $out = ['road_id' => (int) $roadId, 'tables' => []];

        foreach ($tables as $t) {
            $entry = ['filtered_by_road' => true];
            try {
                // Try road-filtered first.
                $res = $ramm->queryTable($t, [RammService::eq('road_id', $roadId)], 3, false, true);
            } catch (Throwable $e) {
                // Retry unfiltered — table probably has no road_id column.
                $entry['filtered_by_road'] = false;
                $entry['filter_error'] = $e->getMessage();
                try {
                    $res = $ramm->queryTable($t, [], 3, false, true);
                } catch (Throwable $e2) {
                    $out['tables'][$t] = ['error' => $e2->getMessage()];
                    continue;
                }
            }

            $cols = $res['rows'] ? array_keys($res['rows'][0]) : $ramm->getColumns($t);
            $entry['columns']     = $cols;
            $entry['row_count']   = $res['total'] ?? count($res['rows']);
            $entry['sample_rows'] = array_slice($res['rows'], 0, 2);
            $out['tables'][$t] = $entry;
        }

        return response()->json($out);
    }

    /**
     * Lightweight: just the RP-ordered line + total RP (for hover route-position readout).
     */
    public function line(string $roadId, RammSync $sync)
    {
        if (! $this->ensureAuth()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $road = $this->roadFromDb($roadId, $sync);
            if (! $road) {
                return response()->json(['ok' => false, 'message' => 'No RAMM data for that road.']);
            }

            return response()->json([
                'road_id'   => $road->road_id,
                'road_name' => $road->road_name,
                'line'      => $road->line,
                'total_rp'  => $road->total_rp,
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }
}
