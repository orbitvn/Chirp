<?php

namespace App\Services;

use App\Models\RammFwp;
use App\Models\RammFwpTreatment;
use App\Models\RammRoad;
use App\Models\RammSurfacing;
use App\Models\RammTreatmentLength;
use App\Support\Council;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pulls curated RAMM data into the local database (ramm_roads / ramm_surfacings /
 * ramm_treatment_lengths) so the map can be served from MySQL instead of hitting
 * the RAMM API on every view.
 */
class RammSync
{
    public function __construct(private RammService $ramm)
    {
    }

    /**
     * Every road_id RAMM knows about (from the roadnames table).
     *
     * @return array<int, array{road_id:int, road_name:?string}>
     */
    public function allRoads(): array
    {
        $rows = $this->ramm->queryTable('roadnames', [], 100000)['rows'];

        $out = [];
        foreach ($rows as $r) {
            if (! is_array($r) || ! isset($r['road_id'])) {
                continue;
            }
            $out[] = [
                'road_id'   => (int) $r['road_id'],
                'road_name' => $r['road_name'] ?? null,
            ];
        }
        return $out;
    }

    /**
     * Fetch one road from RAMM and upsert its geometry, surfacing and treatment
     * lengths. Returns a small status array.
     *
     * @return array{road_id:int, imported:bool, reason?:string, surfacings?:int, treatment_lengths?:int}
     */
    public function importRoad(string|int $roadId): array
    {
        $roadId = (int) $roadId;

        $geo = $this->ramm->roadGeometry((string) $roadId);
        if (empty($geo['line']) || count($geo['line']) < 2) {
            return ['road_id' => $roadId, 'imported' => false, 'reason' => 'no geometry'];
        }

        $names    = $this->ramm->queryTable('roadnames', [RammService::eq('road_id', $roadId)], 1)['rows'];
        $roadName = $names[0]['road_name'] ?? null;

        // expandLookups so surf_material / surf_function are stored as readable names.
        $surf = $this->ramm->queryTable('top_surface', [RammService::eq('road_id', $roadId)], 1000, false, true)['rows'];
        $tls  = $this->ramm->roadTreatmentLengths((string) $roadId);
        $fwp  = $this->fetchFwp($roadId);

        $council = Council::current();   // bulk insert() skips the model hook

        DB::transaction(function () use ($council, $roadId, $roadName, $geo, $surf, $tls, $fwp) {
            RammRoad::updateOrCreate(
                ['road_id' => $roadId],
                [
                    'road_name'   => $roadName,
                    'total_rp'    => $geo['total_rp'] ?? null,
                    'line'        => $geo['line'],
                    'sections'    => $geo['sections'] ?? [],
                    'imported_at' => now(),
                ]
            );

            RammSurfacing::where('road_id', $roadId)->delete();
            $surfRows = [];
            foreach ($surf as $s) {
                if (! is_array($s)) {
                    continue;
                }
                $surfRows[] = [
                    'council'       => $council,
                    'road_id'       => $roadId,
                    'start_m'       => self::num($s['start_m'] ?? null),
                    'end_m'         => self::num($s['end_m'] ?? null),
                    'surf_offset'   => self::num($s['surf_offset'] ?? null),
                    'surface_date'  => self::date($s['surface_date'] ?? null),
                    'surf_material' => self::str($s['surf_material'] ?? null),
                    'surf_function' => self::str($s['surf_function'] ?? null),
                    'chip_size'     => self::str($s['chip_size'] ?? null),
                    'chip_2nd_size' => self::str($s['chip_2nd_size'] ?? null),
                    'surf_width'    => self::num($s['surf_width'] ?? null),
                    'life'          => is_numeric($s['life'] ?? null) ? (int) $s['life'] : null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }
            if ($surfRows) {
                RammSurfacing::insert($surfRows);
            }

            RammTreatmentLength::where('road_id', $roadId)->delete();
            $tlRows = [];
            foreach ($tls as $t) {
                $tlRows[] = [
                    'council'    => $council,
                    'road_id'    => $roadId,
                    'tl_id'      => is_numeric($t['tl_id'] ?? null) ? (int) $t['tl_id'] : null,
                    'tl_name'    => self::str($t['name'] ?? null),
                    'start_m'    => self::num($t['start_m'] ?? null),
                    'end_m'      => self::num($t['end_m'] ?? null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($tlRows) {
                RammTreatmentLength::insert($tlRows);
            }

            RammFwp::where('road_id', $roadId)->delete();
            if ($fwp) {
                $now = now();
                RammFwp::insert(array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now], $fwp));
            }
        });

        return [
            'road_id'           => $roadId,
            'imported'          => true,
            'surfacings'        => count($surf),
            'treatment_lengths' => count($tls),
            'fwp'               => count($fwp),
        ];
    }

    /**
     * Refresh ONLY the Forward Works Programme for an already-imported road
     * (no geometry/surfacing re-fetch) — used by the fast `ramm:fwp` backfill.
     */
    public function syncFwp(string|int $roadId): int
    {
        $fwp = $this->fetchFwp($roadId);
        DB::transaction(function () use ($roadId, $fwp) {
            RammFwp::where('road_id', (int) $roadId)->delete();
            if ($fwp) {
                $now = now();
                RammFwp::insert(array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now], $fwp));
            }
        });
        return count($fwp);
    }

    /** Which RAMM table holds this council's FWP (see config/councils.php). */
    private function fwpSource(): string
    {
        return Council::config()['fwp_source'] ?? 'ud_fwp_works';
    }

    /**
     * Pull the Forward Works Programme for one road and normalise it to
     * ramm_fwp rows. Degrades to [] if the table isn't available.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchFwp(string|int $roadId): array
    {
        return $this->fwpSource() === 'fw_forward_work_view'
            ? $this->fetchFwpView($roadId)
            : $this->fetchFwpUd($roadId);
    }

    /**
     * RAMM's standard Forward Work Treatment View (fw_forward_work_view), e.g. Wairoa.
     * Raw values (no expandLookups) so road_id stays numeric and fw_treatment is
     * the code (RS24, AWPT) matching fw_treatment.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchFwpView(string|int $roadId): array
    {
        $catMap = $this->treatmentCategories();

        try {
            $rows = $this->ramm->queryTable('fw_forward_work_view', [RammService::eq('road_id', $roadId)], 500)['rows'];
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $code   = self::str($r['fw_treatment'] ?? null);
            $year   = self::str($r['fw_year'] ?? null);
            $reason = implode(' — ', array_filter([self::str($r['reasons'] ?? null), self::str($r['reason_note'] ?? null)]));
            $out[] = [
                'council'         => Council::current(),
                'road_id'         => (int) $roadId,
                'treat_length_id' => is_numeric($r['treat_length_id'] ?? null) ? (int) $r['treat_length_id'] : null,
                'works_id'        => null,
                'start_m'         => self::num($r['tl_start_m'] ?? null),
                'end_m'           => self::num($r['tl_end_m'] ?? null),
                'treatment_id'    => $code,
                'treatment'       => $code,
                'category'        => $code !== null ? ($catMap[$code] ?? null) : null,
                'fw_year'         => $year,
                'year_start'      => self::fyStart($year),
                'reason'          => $reason !== '' ? $reason : null,
                'rank_score'      => null,
                'cost'            => null,
                'coverage_pct'    => self::num($r['coverage'] ?? null),
                'locked'          => false,
            ];
        }
        return $out;
    }

    /**
     * Hastings-style user-defined FWP table (ud_fwp_works).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchFwpUd(string|int $roadId): array
    {
        $catMap = $this->treatmentCategories();

        try {
            // expandLookups=true so treatment_id comes back as its code (CS, TAC) not
            // the raw lookup id. We ignore the row's decoded road_id and use $roadId.
            $rows = $this->ramm->queryTable('ud_fwp_works', [RammService::eq('road_id', $roadId)], 500, false, true)['rows'];
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $code = self::str($r['treatment_id'] ?? null);
            $year = self::str($r['trt_year'] ?? null);
            $out[] = [
                'council'         => Council::current(),
                'road_id'         => (int) $roadId,
                'treat_length_id' => is_numeric($r['treat_length_id'] ?? null) ? (int) $r['treat_length_id'] : null,
                'works_id'        => is_numeric($r['system_id'] ?? null) ? (int) $r['system_id'] : null,
                'start_m'         => self::num($r['start_m'] ?? null),
                'end_m'           => self::num($r['end_m'] ?? null),
                'treatment_id'    => $code,
                'treatment'       => $code,
                'category'        => $code !== null ? ($catMap[$code] ?? null) : null,
                'fw_year'         => $year,
                'year_start'      => self::fyStart($year),
                'reason'          => self::str($r['trt_reason'] ?? null),
                'rank_score'      => self::num($r['trt_rank_score'] ?? null),
                'cost'            => self::num($r['trt_cost'] ?? null),
                'coverage_pct'    => self::num($r['tl_coverage_pct'] ?? null),
                'locked'          => (bool) ($r['trt_locked'] ?? false),
            ];
        }
        return $out;
    }

    /**
     * code => category map from the cached treatment vocabulary (synced on demand).
     *
     * @return array<string, ?string>
     */
    private function treatmentCategories(): array
    {
        if (RammFwpTreatment::count() === 0) {
            $this->importTreatments();
        }
        return RammFwpTreatment::pluck('category', 'code')->all();
    }

    /**
     * Sync the FWP treatment vocabulary into ramm_fwp_treatments: ud_fwp_treatments
     * (whose `description` is the code: AWPT, CS, TAC, ...) or, for councils on the
     * standard view, fw_treatment.
     */
    public function importTreatments(): int
    {
        if ($this->fwpSource() === 'fw_forward_work_view') {
            return $this->importFwTreatments();
        }

        try {
            $rows = $this->ramm->queryTable('ud_fwp_treatments', [], 500)['rows'];
        } catch (Throwable $e) {
            return 0;
        }

        $n = 0;
        foreach ($rows as $r) {
            $code = self::str($r['description'] ?? null);
            if ($code === null) {
                continue;
            }
            RammFwpTreatment::updateOrCreate(['code' => $code], [
                'category'   => self::str($r['category'] ?? null),
                'asset_type' => self::str($r['asset_type'] ?? null),
                'ra1_rate'   => self::num($r['ra1_rate'] ?? null),
                'ra2_rate'   => self::num($r['ra2_rate'] ?? null),
                'active'     => (bool) ($r['active'] ?? true),
                'seq'        => is_numeric($r['display_sequence'] ?? null) ? (int) $r['display_sequence'] : null,
            ]);
            $n++;
        }
        return $n;
    }

    /** fw_treatment lookup (code, description, group) into ramm_fwp_treatments. */
    private function importFwTreatments(): int
    {
        try {
            // expandLookups so treatment_group reads "Surfacing (Reseal)" not "RS".
            $rows = $this->ramm->queryTable('fw_treatment', [], 500, false, true)['rows'];
        } catch (Throwable $e) {
            return 0;
        }

        $n = 0;
        foreach ($rows as $r) {
            $code = self::str($r['fw_treatment'] ?? null);
            if ($code === null) {
                continue;
            }
            RammFwpTreatment::updateOrCreate(['code' => $code], [
                'category'   => self::str($r['treatment_group'] ?? null),
                'asset_type' => self::str($r['funding_group'] ?? null),
                'active'     => (bool) ($r['active'] ?? true),
                'seq'        => is_numeric($r['display_sequence'] ?? null) ? (int) $r['display_sequence'] : null,
            ]);
            $n++;
        }
        return $n;
    }

    /** "2026/27" -> 2026 */
    private static function fyStart(?string $fy): ?int
    {
        if (! $fy || ! preg_match('/(\d{4})/', $fy, $m)) {
            return null;
        }
        return (int) $m[1];
    }

    private static function num($v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    private static function str($v): ?string
    {
        return ($v === null || $v === '') ? null : (string) $v;
    }

    private static function date($v): ?string
    {
        if (! $v) {
            return null;
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (Throwable $e) {
            return null;
        }
    }
}
