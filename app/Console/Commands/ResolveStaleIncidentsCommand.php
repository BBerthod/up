<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MonitorIncident;
use Illuminate\Console\Command;

class ResolveStaleIncidentsCommand extends Command
{
    protected $signature = 'incidents:resolve-stale
                            {--dry-run : Show what would be resolved without making changes}';

    protected $description = 'Resolve active incidents for monitors that are currently UP';

    public function handle(): int
    {
        $stale = MonitorIncident::whereNull('resolved_at')
            ->with(['monitor' => fn ($q) => $q->with(['checks' => fn ($q) => $q->latest('checked_at')->limit(1)])])
            ->get()
            ->filter(fn ($incident) => $incident->monitor?->checks->first()?->status->value === 'up');

        if ($stale->isEmpty()) {
            $this->info('No stale incidents found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Monitor', 'Cause', 'Started At'],
            $stale->map(fn ($i) => [
                $i->id,
                $i->monitor->name,
                $i->cause->value,
                $i->started_at->format('Y-m-d H:i'),
            ])
        );

        if ($this->option('dry-run')) {
            $this->warn("Dry-run: {$stale->count()} incident(s) would be resolved.");

            return self::SUCCESS;
        }

        $stale->each(fn ($incident) => $incident->resolve());

        $this->info("Resolved {$stale->count()} stale incident(s).");

        return self::SUCCESS;
    }
}
