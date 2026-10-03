<?php

namespace App\Console\Commands;

use App\Models\RammFwp;
use App\Models\RammFwpTreatment;
use App\Models\RammRoad;
use App\Models\RammSurfacing;
use App\Models\RammTreatmentLength;
use App\Support\Council;
use Illuminate\Console\Command;

class RammExport extends Command
{
    protected $signature = 'ramm:export
        {--council= : Just this council (default: every council with imported roads)}
        {--path= : Output file (default public/data/ramm-offline-<council>.json)}';

    protected $description = 'Export curated RAMM data to a static bundle for offline / on-device use';

    public function handle(): int
    {
        $only = $this->option('council');
        if ($only !== null && ! Council::exists($only)) {
            $this->error("Unknown council '{$only}'.");
            return self::FAILURE;
        }

        $exported = 0;
        foreach ($only !== null ? [$only] : array_keys(Council::all()) as $slug) {
            Council::use($slug);
            $roadCount = RammRoad::count();
            if ($roadCount === 0) {
                if ($only !== null) {
                    $this->error("No roads stored for {$slug}. Run `php artisan ramm:import --council={$slug}` first.");
                    return self::FAILURE;
                }
                $this->line("Skipping {$slug}: no roads imported.");
                continue;
            }
            $this->info('Council: ' . Council::config()['name']);
            $path = ($only !== null ? $this->option('path') : null) ?: public_path("data/ramm-offline-{$slug}.json");
            if ($this->exportCouncil($path, $roadCount) !== self::SUCCESS) {
                return self::FAILURE;
            }
            $exported++;
        }

        if ($exported === 0) {
            $this->error('No roads stored. Run `php artisan ramm:import --council=<slug>` first.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    private function exportCouncil(string $path, int $roadCount): int
    {
        @mkdir(dirname($path), 0755, true);

        // A version stamp the client uses to decide whether to re-download.
        // Changes whenever the imported data changes (last import + row counts).
        $version = substr(sha1(implode('|', [
            $roadCount,
            RammSurfacing::count(),
            RammTreatmentLength::count(),
            RammFwp::count(),
            RammFwpTreatment::count(),
            (string) RammRoad::max('imported_at'),
        ])), 0, 12);

        // FWP treatment vocabulary (small) — shipped whole for the cycle button + rates.
        $treatments = RammFwpTreatment::orderBy('seq')->get()
            ->map(fn ($t) => [
                'code'       => $t->code,
                'category'   => $t->category,
                'asset_type' => $t->asset_type,
                'ra1_rate'   => $t->ra1_rate,
                'ra2_rate'   => $t->ra2_rate,
            ])->all();

        $tmp = $path . '.tmp';
        $fh  = fopen($tmp, 'w');
        if (! $fh) {
            $this->error("Cannot write to {$tmp}");
            return self::FAILURE;
        }

        fwrite($fh, '{"version":' . json_encode($version)
            . ',"council":' . json_encode(Council::current())
            . ',"generated_at":' . json_encode(now()->toIso8601String())
            . ',"count":' . $roadCount
            . ',"treatments":' . json_encode($treatments, JSON_UNESCAPED_SLASHES)
            . ',"roads":{');

        $bar = $this->output->createProgressBar($roadCount);
        $bar->start();

        $first = true;
        RammRoad::with(['surfacings', 'treatmentLengths', 'fwp'])
            ->orderBy('road_id')
            ->chunkById(200, function ($roads) use ($fh, &$first, $bar) {
                foreach ($roads as $road) {
                    $obj = [
                        'road_id'   => $road->road_id,
                        'road_name' => $road->road_name,
                        'line'      => $road->line,
                        'total_rp'  => $road->total_rp,
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
                        ])->all(),
                        'treatment_lengths' => $road->treatmentLengths->map(fn ($t) => [
                            'start_m' => $t->start_m,
                            'end_m'   => $t->end_m,
                            'tl_id'   => $t->tl_id,
                            'name'    => $t->tl_name,
                        ])->all(),
                        'fwp' => $road->fwp->map(fn ($f) => [
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
                        ])->all(),
                    ];

                    fwrite($fh, ($first ? '' : ',') . json_encode((string) $road->road_id)
                        . ':' . json_encode($obj, JSON_UNESCAPED_SLASHES));
                    $first = false;
                    $bar->advance();
                }
            }, 'road_id');

        fwrite($fh, '}}');
        fclose($fh);

        // Atomic-ish replace so a half-written file is never served.
        @unlink($path);
        rename($tmp, $path);

        // Tiny companion file: the client fetches this first (cheap) and only
        // re-downloads the full bundle when the version changes.
        file_put_contents(
            preg_replace('/\.json$/', '.version.json', $path),
            json_encode(['version' => $version, 'count' => $roadCount, 'generated_at' => now()->toIso8601String()])
        );

        $bar->finish();
        $this->newLine(2);
        $sizeMb = round(filesize($path) / 1048576, 2);
        $this->info("Wrote {$path} ({$sizeMb} MB, version {$version}, {$roadCount} roads).");

        return self::SUCCESS;
    }
}
