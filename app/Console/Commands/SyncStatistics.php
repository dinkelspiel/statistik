<?php

namespace App\Console\Commands;

use App\Jobs\SyncScbStatistics;
use App\Services\ScbStatisticsClient;
use App\Services\WorldBankStatisticsClient;
use Illuminate\Console\Command;

class SyncStatistics extends Command
{
    protected $signature = 'statistics:sync {--now : Run immediately instead of dispatching a queue job}';

    protected $description = 'Synchronize locally stored statistics with official data sources';

    public function handle(
        ScbStatisticsClient $scbClient,
        WorldBankStatisticsClient $worldBankClient,
    ): int {
        if ($this->option('now')) {
            $this->components->task('Fetching official SCB datasets', fn () => $scbClient->syncAll());
            $this->components->task('Fetching international comparisons', fn () => $worldBankClient->syncAll());
            $this->components->info('Statistics are up to date.');

            return self::SUCCESS;
        }

        SyncScbStatistics::dispatch();
        $this->components->info('Statistics synchronization was added to the queue.');

        return self::SUCCESS;
    }
}
