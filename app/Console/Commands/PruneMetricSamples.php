<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneMetricSamples extends Command
{
    protected $signature = 'metrics:prune {--days= : Retention days (default from config)}';

    protected $description = 'Delete metric_samples rows not updated within retention window.';

    public function handle(): int
    {
        $days = $this->option('days');
        if ($days === null || $days === '') {
            $days = config('metrics.retention_days', 14);
        }

        $days = (int) $days;
        if ($days <= 0) {
            $this->error('Retention days must be a positive integer.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $deleted = DB::table('metric_samples')
            ->where('updated_at', '<', $cutoff)
            ->delete();

        $deletedActivity = 0;
        if (DB::getSchemaBuilder()->hasTable('user_activity_daily')) {
            $deletedActivity = DB::table('user_activity_daily')
                ->where('activity_date', '<', $cutoff->toDateString())
                ->delete();
        }

        $this->info("Deleted {$deleted} metric_samples rows older than {$days} days.");
        $this->info("Deleted {$deletedActivity} user_activity_daily rows older than {$days} days.");

        return self::SUCCESS;
    }
}
