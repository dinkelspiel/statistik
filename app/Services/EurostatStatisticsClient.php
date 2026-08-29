<?php

namespace App\Services;

use App\Models\StatisticSeries;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EurostatStatisticsClient
{
    public function syncAll(): void
    {
        foreach (config('statistics.series', []) as $definition) {
            if (($definition['source'] ?? 'scb') !== 'eurostat') {
                continue;
            }

            $this->sync($definition);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function sync(array $definition): StatisticSeries
    {
        $baseUrl = rtrim((string) config('statistics.eurostat_base_url'), '/');
        $dataset = $definition['dataset'];
        $sourceUrl = "{$baseUrl}/{$dataset}";
        $response = $this->http()->get($sourceUrl, [
            ...$definition['query'],
            'lang' => 'en',
        ])->throw()->json();

        if (!is_array($response)) {
            throw new RuntimeException("Eurostat returned an invalid response for {$definition['slug']}.");
        }

        $values = $this->dimensionValues($response, 'cofog99', 'time');
        $points = [];
        $sortOrder = 0;

        foreach ($definition['breakdown'] as $code => $label) {
            foreach ($values['time_codes'] as $period) {
                $total = $values['values']['TOTAL'][$period] ?? null;
                $value = $values['values'][$code][$period] ?? null;

                if ($total === null || $value === null || $total == 0.0) {
                    continue;
                }

                $points[] = [
                    'geography' => $code,
                    'geography_label' => $label,
                    'period' => $period,
                    'period_start' => "{$period}-01-01",
                    'value' => $value / $total * 100,
                    'sort_order' => $sortOrder++,
                ];
            }
        }

        if ($points === []) {
            throw new RuntimeException("Eurostat returned no usable values for {$definition['slug']}.");
        }

        return DB::transaction(function () use ($definition, $response, $points): StatisticSeries {
            $sourceUrl = "https://ec.europa.eu/eurostat/databrowser/view/{$definition['dataset']}/default/table?lang=en";
            $series = StatisticSeries::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'source_name' => 'Eurostat',
                    'source_url' => $sourceUrl,
                    'title' => $definition['title'],
                    'short_title' => $definition['short_title'],
                    'description' => $definition['description'],
                    'unit' => $definition['unit'],
                    'frequency' => $definition['frequency'],
                    'category' => $definition['category'],
                    'chart_type' => $definition['chart_type'],
                    'accent' => $definition['accent'],
                    'source_updated_at' => isset($response['updated'])
                        ? CarbonImmutable::parse($response['updated'])
                        : null,
                    'synced_at' => now(),
                    'metadata' => [
                        'dataset' => $definition['dataset'],
                        'breakdown' => $definition['breakdown'],
                        'sector' => $definition['query']['sector'] ?? null,
                    ],
                ],
            );

            $pointRows = array_map(
                fn(array $point): array => [
                    ...$point,
                    'statistic_series_id' => $series->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $points,
            );

            $series->points()->upsert(
                $pointRows,
                ['statistic_series_id', 'geography', 'period'],
                ['geography_label', 'period_start', 'value', 'sort_order', 'updated_at'],
            );

            $validKeys = collect($pointRows)
                ->map(fn(array $point): string => "{$point['geography']}:{$point['period']}")
                ->all();

            $series->points()
                ->get()
                ->reject(fn($point): bool => in_array("{$point->geography}:{$point->period}", $validKeys, true))
                ->each
                ->delete();

            return $series->load('points');
        });
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return array{time_codes: list<string>, values: array<string, array<string, float>>}
     */
    private function dimensionValues(array $dataset, string $categoryDimension, string $timeDimension): array
    {
        $ids = $dataset['id'] ?? null;
        $sizes = $dataset['size'] ?? null;
        $dimensions = $dataset['dimension'] ?? null;
        $rawValues = $dataset['value'] ?? null;

        if (!is_array($ids) || !is_array($sizes) || !is_array($dimensions) || !is_array($rawValues)) {
            throw new RuntimeException('Eurostat JSON-stat response is missing required dimensions.');
        }

        $categoryPosition = array_search($categoryDimension, $ids, true);
        $timePosition = array_search($timeDimension, $ids, true);

        if (!is_int($categoryPosition) || !is_int($timePosition)) {
            throw new RuntimeException('Eurostat JSON-stat response has no breakdown or time dimension.');
        }

        $categoryCodes = $this->orderedCategoryCodes($dimensions[$categoryDimension] ?? []);
        $timeCodes = $this->orderedCategoryCodes($dimensions[$timeDimension] ?? []);
        $categoryStride = array_product(array_slice($sizes, $categoryPosition + 1));
        $timeStride = array_product(array_slice($sizes, $timePosition + 1));
        $values = [];

        foreach ($rawValues as $flatIndex => $value) {
            if (!is_numeric($flatIndex) || !is_numeric($value)) {
                continue;
            }

            $categoryIndex = intdiv((int) $flatIndex, $categoryStride) % $sizes[$categoryPosition];
            $timeIndex = intdiv((int) $flatIndex, $timeStride) % $sizes[$timePosition];
            $categoryCode = $categoryCodes[$categoryIndex] ?? null;
            $timeCode = $timeCodes[$timeIndex] ?? null;

            if ($categoryCode !== null && $timeCode !== null) {
                $values[$categoryCode][$timeCode] = (float) $value;
            }
        }

        return ['time_codes' => $timeCodes, 'values' => $values];
    }

    /**
     * @param  array<string, mixed>  $dimension
     * @return list<string>
     */
    private function orderedCategoryCodes(array $dimension): array
    {
        $index = $dimension['category']['index'] ?? null;

        if (!is_array($index)) {
            throw new RuntimeException('Eurostat JSON-stat categories are invalid.');
        }

        asort($index);

        return array_map('strval', array_keys($index));
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(3, 500);
    }
}
