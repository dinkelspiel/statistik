<?php

namespace App\Services;

use App\Models\StatisticSeries;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WorldBankStatisticsClient
{
    /** @var array<string, string> */
    private const GEOGRAPHIES = [
        'SE' => 'Sverige',
        'EUU' => 'EU',
        'WLD' => 'Världen',
    ];

    public function syncAll(): void
    {
        foreach (config('statistics.series', []) as $definition) {
            if (($definition['source'] ?? 'scb') !== 'world_bank') {
                continue;
            }

            $this->sync($definition);
        }
    }

    /**
     * @param  array{
     *     slug: string,
     *     source: string,
     *     indicator: string,
     *     title: string,
     *     short_title: string,
     *     description: string,
     *     unit: string,
     *     frequency: string,
     *     category: string,
     *     chart_type: string,
     *     accent: string
     * }  $definition
     */
    public function sync(array $definition): StatisticSeries
    {
        $baseUrl = rtrim((string) config('statistics.world_bank_base_url'), '/');
        $responses = [];
        $updatedAt = null;

        foreach (self::GEOGRAPHIES as $code => $label) {
            $url = "{$baseUrl}/country/{$code}/indicator/{$definition['indicator']}";
            $response = $this->http()->get($url, [
                'format' => 'json',
                'per_page' => 100,
            ])->throw()->json();

            if (! is_array($response) || ! is_array($response[0] ?? null) || ! is_array($response[1] ?? null)) {
                throw new RuntimeException("The World Bank returned an invalid response for {$definition['slug']} ({$label}).");
            }

            $responses[$code] = $response[1];
            $lastUpdated = $response[0]['lastupdated'] ?? null;

            if (is_string($lastUpdated)) {
                $updatedAt = CarbonImmutable::parse($lastUpdated);
            }
        }

        return DB::transaction(function () use ($definition, $responses, $updatedAt): StatisticSeries {
            $sourceUrl = "https://data.worldbank.org/indicator/{$definition['indicator']}";
            $series = StatisticSeries::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'source_name' => 'Världsbanken',
                    'source_url' => $sourceUrl,
                    'title' => $definition['title'],
                    'short_title' => $definition['short_title'],
                    'description' => $definition['description'],
                    'unit' => $definition['unit'],
                    'frequency' => $definition['frequency'],
                    'category' => $definition['category'],
                    'chart_type' => $definition['chart_type'],
                    'accent' => $definition['accent'],
                    'source_updated_at' => $updatedAt,
                    'synced_at' => now(),
                    'metadata' => [
                        'indicator' => $definition['indicator'],
                        'geographies' => self::GEOGRAPHIES,
                    ],
                ],
            );

            $pointRows = [];
            $sortOrder = 0;

            foreach (self::GEOGRAPHIES as $code => $label) {
                $periods = [];

                foreach ($responses[$code] as $observation) {
                    if (! is_array($observation) || ! is_string($observation['date'] ?? null) || ! is_numeric($observation['value'] ?? null)) {
                        continue;
                    }

                    $periods[$observation['date']] = (float) $observation['value'];
                }

                ksort($periods);

                foreach ($periods as $period => $value) {
                    $pointRows[] = [
                        'statistic_series_id' => $series->id,
                        'geography' => $code === 'EUU' ? 'EU' : $code,
                        'geography_label' => $label,
                        'period' => $period,
                        'period_start' => "{$period}-01-01",
                        'value' => $value,
                        'sort_order' => $sortOrder++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($pointRows === []) {
                throw new RuntimeException("The World Bank returned no usable values for {$definition['slug']}.");
            }

            $series->points()->upsert(
                $pointRows,
                ['statistic_series_id', 'geography', 'period'],
                ['geography_label', 'period_start', 'value', 'sort_order', 'updated_at'],
            );

            $validKeys = collect($pointRows)
                ->map(fn (array $point): string => "{$point['geography']}:{$point['period']}")
                ->all();

            $series->points()
                ->get()
                ->reject(fn ($point): bool => in_array("{$point->geography}:{$point->period}", $validKeys, true))
                ->each
                ->delete();

            return $series->load('points');
        });
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30)
            ->retry(3, 500);
    }
}
