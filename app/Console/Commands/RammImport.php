<?php

namespace App\Console\Commands;

use App\Models\RammRoad;
use App\Services\RammSync;
use App\Support\Council;
use Illuminate\Console\Command;
use Throwable;

class RammImport extends Command
{
    protected $signature = 'ramm:import
        {road? : Import just this road_id}
        {--fresh : Re-import roads that are already stored}
        {--council= : Council slug from config/councils.php (default: COUNCIL_DEFAULT)}';

    protected $description = 'Import curated RAMM data (geometry, surfacing, treatment lengths) into the local database';

    public function handle(RammSync $sync): int
    {
        try {
            Council::use($this->option('council') ?: Council::default());
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->line('Council: <info>' . Council::config()['name'] . '</info>');

        // Single road mode.
        if ($road = $this->argument('road')) {
            $this->info("Importing road {$road}…");
            try {
                $res = $sync->importRoad($road);
            } catch (Throwable $e) {
                $this->error("Failed: {$e->getMessage()}");
                return self::FAILURE;
            }
            $this->line(json_encode($res));
            return self::SUCCESS;
        }

        // Bulk mode.
        $this->info('Fetching road list from RAMM…');
        $roads = $sync->allRoads();
        $this->info(count($roads) . ' roads found.');

        $fresh = (bool) $this->option('fresh');
        $existing = $fresh ? collect() : RammRoad::pluck('road_id')->flip();

        $imported = 0;
        $skipped  = 0;
        $empty    = 0;
        $failed   = 0;

        $bar = $this->output->createProgressBar(count($roads));
        $bar->start();

        foreach ($roads as $r) {
            $id = $r['road_id'];

            if (! $fresh && $existing->has($id)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            try {
                $res = $sync->importRoad($id);
                $res['imported'] ? $imported++ : $empty++;
            } catch (Throwable $e) {
                $failed++;
                // Keep going; report at the end.
                $this->newLine();
                $this->warn("road {$id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. imported={$imported} skipped={$skipped} no-geometry={$empty} failed={$failed}");

        return self::SUCCESS;
    }
}
