<?php

namespace App\Console\Commands;

use App\Models\RammRoad;
use App\Models\RammSurfacing;
use App\Models\RammTreatmentLength;
use App\Services\RammSync;
use App\Support\Council;
use Illuminate\Console\Command;
use Throwable;

class RammStatus extends Command
{
    protected $signature = 'ramm:status
        {--remote : Also fetch the total road count from RAMM to show %% complete}
        {--decode : List distinct stored surf_material / surf_function values (checks expandLookups decoded to names, not codes)}
        {--council= : Council slug from config/councils.php (default: COUNCIL_DEFAULT)}';

    protected $description = 'Show how much curated RAMM data is stored locally (import progress)';

    public function handle(RammSync $sync): int
    {
        try {
            Council::use($this->option('council') ?: Council::default());
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->line('Council: <info>' . Council::config()['name'] . '</info>');

        $roads       = RammRoad::count();
        $withGeom    = RammRoad::whereNotNull('line')->where('line', '!=', '[]')->count();
        $noGeom      = $roads - $withGeom;
        $surfacings  = RammSurfacing::count();
        $tls         = RammTreatmentLength::count();
        $roadsWithSurf = RammSurfacing::distinct()->count('road_id');
        $roadsWithTl   = RammTreatmentLength::distinct()->count('road_id');
        $lastImport  = RammRoad::max('imported_at');

        $rows = [
            ['Roads stored',              number_format($roads)],
            ['  with geometry',           number_format($withGeom)],
            ['  without geometry',        number_format($noGeom)],
            ['Roads with surfacings',     number_format($roadsWithSurf)],
            ['Roads with treatment lens', number_format($roadsWithTl)],
            ['Surfacing rows',            number_format($surfacings)],
            ['Treatment-length rows',     number_format($tls)],
            ['Last import at',            $lastImport ?: '—'],
        ];

        if ($this->option('remote')) {
            try {
                $total = count($sync->allRoads());
                $pct   = $total > 0 ? round($roads / $total * 100, 1) : 0;
                $rows[] = ['RAMM total roads', number_format($total)];
                $rows[] = ['Complete', "{$pct}%  ({$roads}/{$total})"];
                if ($roads >= $total) {
                    $this->info('Import looks complete.');
                } else {
                    $this->warn('Import incomplete — run: php artisan ramm:import');
                }
            } catch (Throwable $e) {
                $this->warn("Could not reach RAMM for total: {$e->getMessage()}");
            }
        }

        $this->table(['Metric', 'Value'], $rows);

        if ($this->option('decode')) {
            $materials = RammSurfacing::select('surf_material')->distinct()
                ->orderBy('surf_material')->pluck('surf_material')->filter()->values()->all();
            $functions = RammSurfacing::select('surf_function')->distinct()
                ->orderBy('surf_function')->pluck('surf_function')->filter()->values()->all();

            $numericish = fn (array $vals) => count($vals) > 0
                && count(array_filter($vals, fn ($v) => is_numeric($v))) === count($vals);

            $this->newLine();
            $this->line('<comment>Distinct surf_material (' . count($materials) . '):</comment>');
            $this->line('  ' . (empty($materials) ? '—' : implode(' | ', $materials)));
            $this->newLine();
            $this->line('<comment>Distinct surf_function (' . count($functions) . '):</comment>');
            $this->line('  ' . (empty($functions) ? '—' : implode(' | ', $functions)));
            $this->newLine();

            if ($numericish($materials) || $numericish($functions)) {
                $this->warn('Values look like raw codes — expandLookups did NOT decode to names.');
            } else {
                $this->info('Values look like readable names — expandLookups decoded correctly.');
            }
        }

        if (! $this->option('remote') && ! $this->option('decode')) {
            $this->line('Tip: add --remote to compare against RAMM\'s total road count.');
        }

        return self::SUCCESS;
    }
}
