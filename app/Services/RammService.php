<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the RAMM REST API (RammApi6.1).
 *
 * Auth:  POST {base}/authenticate/login?database=&userName=&password=  -> token string.
 * Data:  POST {base}/data/table  with a JSON body (tableName + filters + gridPaging).
 *        Rows come back as { total, rows: [ { values: [...] } ] } aligned to the table schema.
 *
 * Format follows the proven open-source RAMM wrappers (r-ramm / pyramm). Credentials come
 * from .env (never hard-coded).
 */
class RammService
{
    private string $base;
    private ?string $database;
    private ?string $username;
    private ?string $password;

    public function __construct()
    {
        $this->base     = rtrim((string) config('services.ramm.base'), '/');
        $this->database = config('services.ramm.database');
        $this->username = config('services.ramm.username');
        $this->password = config('services.ramm.password');
    }

    public function isConfigured(): bool
    {
        return filled($this->database) && filled($this->username) && filled($this->password);
    }

    /**
     * Get a valid auth token, authenticating and caching (45 min) when needed.
     */
    public function token(bool $forceRefresh = false): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('RAMM credentials are not set — fill RAMM_DATABASE / RAMM_USERNAME / RAMM_PASSWORD in .env.');
        }

        $cacheKey = 'ramm_token_' . md5($this->base . '|' . $this->database . '|' . $this->username);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes(45), function () {
            $res = Http::send('POST', $this->base . '/authenticate/login', [
                'query' => [
                    'database' => $this->database,
                    'userName' => $this->username,
                    'password' => $this->password,
                ],
            ]);

            if ($res->failed()) {
                throw new RuntimeException('RAMM login failed (HTTP ' . $res->status() . '): ' . $res->body());
            }

            $token = trim($res->body(), " \t\n\r\"");

            if ($token === '') {
                throw new RuntimeException('RAMM login returned an empty token.');
            }

            return $token;
        });
    }

    /**
     * Authenticated request. Retries once with a fresh token on 401 (expiry).
     *
     * @return mixed  Decoded JSON, or raw body if not JSON.
     */
    public function request(string $method, string $path, array $body = [], array $query = [])
    {
        $send = function (string $token) use ($method, $path, $body, $query) {
            return Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'referer'       => config('app.url', 'https://localhost'),
            ])->connectTimeout(10)->timeout(20)->send($method, $this->base . '/' . ltrim($path, '/'), array_filter([
                'json'  => $body ?: null,
                'query' => $query ?: null,
            ]));
        };

        $res = $send($this->token());

        if ($res->status() === 401) {
            $res = $send($this->token(true));
        }

        if ($res->failed()) {
            throw new RuntimeException('RAMM request failed (HTTP ' . $res->status() . '): ' . $res->body());
        }

        return $res->json() ?? $res->body();
    }

    /**
     * Column names for a table, in schema order (matches the order of row "values").
     */
    public function getColumns(string $table, bool $getGeometry = false): array
    {
        // Schema is stable, so cache the column list (a day) to avoid a RAMM
        // round-trip on every data query — roughly halves calls during a bulk import.
        $cols = Cache::remember('ramm_cols_' . $table, now()->addDay(), function () use ($table) {
            $schema = $this->request('GET', 'schema/' . $table, [], ['loadType' => 3]);

            $out = [];
            if (is_array($schema)) {
                foreach ($schema as $c) {
                    if (is_array($c) && isset($c['columnName'])) {
                        $out[] = $c['columnName'];
                    }
                }
            }
            return $out;
        });

        if ($getGeometry) {
            $cols[] = 'wkt';
        }
        return $cols;
    }

    /**
     * Query a RAMM table. Returns ['total' => int, 'rows' => [ column => value, ... ]].
     *
     * @param  array  $filters  e.g. [ RammService::eq('road_id', 9019) ]
     */
    public function queryTable(string $table, array $filters = [], int $take = 50, bool $getGeometry = false, bool $expandLookups = false): array
    {
        $columns = $this->getColumns($table, $getGeometry);

        // RAMM's data/table with loadType "All" forces an ORDER BY on a column literally
        // named "id" for OFFSET/FETCH paging; tables without that column (e.g. roadnames)
        // 500 with "Invalid column name 'id'." Using loadType "Specified" with an explicit
        // column list avoids that path. Columns MUST be plain name strings — column
        // objects ({columnName:…}) trigger a server NullReferenceException.
        //
        // The schema also lists synthetic columns that are NOT real SQL columns and 500
        // if selected: "id" (RAMM's entity id) and our own appended "wkt" marker. Drop
        // both from the request. Geometry still comes back via the getGeometry flag as a
        // trailing value per row.
        $requestColumns = array_values(array_filter(
            $getGeometry ? array_slice($columns, 0, -1) : $columns,
            fn ($c) => strcasecmp((string) $c, 'id') !== 0 && strcasecmp((string) $c, 'wkt') !== 0
        ));

        $body = [
            'filters'             => array_values($filters),
            'expandLookups'       => $expandLookups,
            'getGeometry'         => $getGeometry,
            'isLongitudeLatitude' => true,
            'gridPaging'          => ['skip' => 0, 'take' => $take],
            'excludeReplacedData' => true,
            'returnEntityId'      => false,
            'tableName'           => $table,
            'loadType'            => 'Specified',
            'columns'             => $requestColumns,
        ];

        $res = $this->request('POST', 'data/table', $body);

        // In "Specified" mode the returned values align to the requested columns, in order
        // (plus a trailing geometry value when getGeometry). Map against exactly that list.
        $mapColumns = $requestColumns;
        if ($getGeometry) {
            $mapColumns[] = 'wkt';
        }

        $rows = [];
        if (is_array($res) && isset($res['rows'])) {
            foreach ($res['rows'] as $row) {
                $values = $row['values'] ?? $row;
                if (is_array($values) && $mapColumns) {
                    // Map by the overlapping prefix so a value/column count mismatch
                    // (e.g. expandLookups appending display columns) can't blank the row.
                    $n = min(count($mapColumns), count($values));
                    $rows[] = array_combine(array_slice($mapColumns, 0, $n), array_slice($values, 0, $n));
                } else {
                    $rows[] = $values;
                }
            }
        }

        return ['total' => $res['total'] ?? count($rows), 'rows' => $rows];
    }

    /**
     * Build an EqualTo filter clause.
     */
    public static function eq(string $column, $value): array
    {
        return ['columnName' => $column, 'operator' => 'EqualTo', 'value' => (string) $value];
    }

    /**
     * Assemble a road's RP-ordered centreline from its carriageway sections.
     * Returns ['line' => [[lng,lat],...], 'total_rp' => float, 'sections' => [...]].
     */
    public function roadGeometry(string $roadId): array
    {
        $rows = $this->queryTable('carr_way', [self::eq('road_id', $roadId)], 500, true)['rows'];

        // Order sections by RP start.
        usort($rows, fn ($a, $b) => ($a['carrway_start_m'] ?? 0) <=> ($b['carrway_start_m'] ?? 0));

        $line = [];
        $sections = [];
        $totalRp = 0.0;

        foreach ($rows as $r) {
            $coords = self::parseWktLine($r['wkt'] ?? '');
            if (count($coords) < 2) {
                continue;
            }

            // Keep sections continuous: reverse a following section if its far end
            // connects better (first section is trusted as RP-ordered).
            if ($line) {
                $last = end($line);
                if (self::dist2($last, $coords[array_key_last($coords)]) < self::dist2($last, $coords[0])) {
                    $coords = array_reverse($coords);
                }
                if (self::dist2(end($line), $coords[0]) < 1e-12) {
                    array_shift($coords); // drop duplicate join vertex
                }
            }

            foreach ($coords as $c) {
                $line[] = $c;
            }

            $sections[] = [
                'carr_way_no' => $r['carr_way_no'] ?? null,
                'start_m'     => $r['carrway_start_m'] ?? null,
                'end_m'       => $r['carrway_end_m'] ?? null,
                'start_name'  => $r['start_name'] ?? null,
                'end_name'    => $r['end_name'] ?? null,
                'hierarchy'   => $r['cway_hierarchy'] ?? null,
            ];

            $totalRp = max($totalRp, (float) ($r['carrway_end_m'] ?? 0));
        }

        return ['line' => $line, 'total_rp' => $totalRp, 'sections' => $sections];
    }

    /**
     * Treatment lengths for a road — the maintenance segmentation the surfacing
     * history is grouped by. Returns [ ['start_m'=>float,'end_m'=>float,'tl_id'=>?,'raw'=>[...]], ... ]
     * ordered by start RP. Column names vary between RAMM databases, so start/end
     * are resolved from a list of candidates.
     */
    public function roadTreatmentLengths(string $roadId): array
    {
        $rows = $this->queryTable('treatment_length', [self::eq('road_id', $roadId)], 1000)['rows'];

        $out = [];
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            // Confirmed RAMM treatment_length columns: tl_start_m / tl_end_m (road RP),
            // treat_length_id, tl_name. Fallbacks kept for other RAMM databases.
            $start = self::pick($r, ['tl_start_m', 'start_m', 'lr_start_m', 'start']);
            $end   = self::pick($r, ['tl_end_m', 'end_m', 'lr_end_m', 'end']);
            if ($start === null || $end === null) {
                continue;
            }
            $s = (float) $start;
            $e = (float) $end;
            if ($e < $s) {
                [$s, $e] = [$e, $s];
            }
            $out[] = [
                'start_m' => $s,
                'end_m'   => $e,
                'tl_id'   => self::pick($r, ['treat_length_id', 'treatment_length_no', 'tl_no', 'tl_id']),
                'name'    => self::pick($r, ['tl_name', 'name']),
                'raw'     => $r,
            ];
        }

        usort($out, fn ($a, $b) => $a['start_m'] <=> $b['start_m']);
        return $out;
    }

    /**
     * First non-empty value among $keys in an associative row, else null.
     */
    private static function pick(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                return $row[$k];
            }
        }
        return null;
    }

    /**
     * Parse a "LINESTRING (lng lat, lng lat, ...)" into [[lng,lat],...].
     */
    private static function parseWktLine(string $wkt): array
    {
        if (! preg_match('/\(([^)]*)\)/', $wkt, $m)) {
            return [];
        }
        $out = [];
        foreach (explode(',', $m[1]) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            $xy = preg_split('/\s+/', $pair);
            if (count($xy) >= 2 && is_numeric($xy[0]) && is_numeric($xy[1])) {
                $out[] = [(float) $xy[0], (float) $xy[1]];
            }
        }
        return $out;
    }

    private static function dist2(array $a, array $b): float
    {
        $dx = $a[0] - $b[0];
        $dy = $a[1] - $b[1];
        return $dx * $dx + $dy * $dy;
    }
}
