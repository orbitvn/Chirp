<?php

namespace App\Console\Commands;

use App\Models\RammRoad;
use App\Services\RammSync;
use Illuminate\Console\Command;
use Throwable;

class RammFwpImport extends Command
{
    protected $signature = 'ramm:fwp {road? : Just this road_id}';

    protected $description = 'Backfill the Forward Works Programme (ud_fwp_works) for already-imported roads';

    public function handle(RammSync $sync): int
    {
        // Refresh the treatment vocabulary once up front.
        $this->info('Syncing FWP treatment vocabulary…');
        $this->line($sync->importTreatments() . ' treatments.');

        if ($road = $this->argument('road')) {
            $n = $sync->syncFwp($road);
            $this->info("Road {$road}: {$n} FWP segments.");
            return self::SUCCESS;
        }

        $ids = RammRoad::orderBy('road_id')->pluck('road_id');
        $this->info($ids->count() . ' roads to scan for FWP.');

        $bar = $this->output->createProgressBar($ids->count());
        $bar->start();
        $withFwp = 0; $segs = 0; $failed = 0;

        foreach ($ids as $id) {
            try {
                $n = $sync->syncFwp($id);
                if ($n > 0) { $withFwp++; $segs += $n; }
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("road {$id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. roads_with_fwp={$withFwp} segments={$segs} failed={$failed}");
        $this->line('Now run `php artisan ramm:export` to refresh the offline bundle.');

        return self::SUCCESS;
    }
}
