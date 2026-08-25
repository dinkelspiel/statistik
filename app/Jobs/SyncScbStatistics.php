<?php

namespace App\Jobs;

use App\Services\ScbStatisticsClient;
use App\Services\WorldBankStatisticsClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncScbStatistics implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function handle(
        ScbStatisticsClient $scbClient,
        WorldBankStatisticsClient $worldBankClient,
    ): void {
        $scbClient->syncAll();
        $worldBankClient->syncAll();
    }
}
