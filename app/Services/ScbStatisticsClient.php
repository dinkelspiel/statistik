<?php

namespace App\Services;

use App\Models\StatisticSeries;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ScbStatisticsClient
{
    public function syncAll(): void
    {
        foreach (config('statistics.series', []) as $definition) {
            if (($definition['source'] ?? 'scb') !== 'scb') {
                continue;
            }

            $this->sync($definition);
        }
    }

    /**
     * @param  array{
     *     slug: string,
     *     path: string,
     *     title: string,
     *     short_title: string,
     *     description: string,
     *     unit: string,
     *     frequency: string,
     *     category: string,
     *     chart_type: string,
     *     accent: string,
     *     source?: string,
    *     calculation?: array<string, mixed>,
     *     query?: array<string, list<string>>,
     *     additional_sources?: list<array{
     *         path: string,
     *         query: array<string, list<string>>
     *     }>,
     *     lines?: list<array{
     *         code: string,
     *         label: string,
     *         path?: string,
     *         query: array<string, list<string>>,
     *         additional_sources?: list<array{
     *             path: string,
     *             query: array<string, list<string>>
     *         }>
     *     }>
     * }  $definition
     */
    public function sync(array $definition): StatisticSeries
    {
        $baseUrl = rtrim((string) config('statistics.scb_base_url'), '/');

        if (isset($definition['calculation'])) {
            return $this->syncCalculated($definition, $baseUrl);
        }

        if (isset($definition['lines'])) {
            $lines = $definition['lines'];
        } elseif (isset($definition['query'])) {
            $lines = [[
                'code' => 'SE',
                'label' => 'Sverige',
                'path' => $definition['path'],
                'query' => $definition['query'],
                'additional_sources' => $definition['additional_sources'] ?? [],
            ]];
        } else {
            throw new RuntimeException("SCB definition {$definition['slug']} has no query lines.");
        }

        $datasets = [];
        $points = [];
        $primaryDataset = null;

        foreach ($lines as $lineOrder => $line) {
            $sources = $line['additional_sources'] ?? [];
            $sources[] = [
                'path' => $line['path'] ?? $definition['path'],
                'query' => $line['query'],
            ];
            $pointsByPeriod = [];
            $linePrimaryDataset = null;

            foreach ($sources as $source) {
                $sourceUrl = "{$baseUrl}/{$source['path']}";
                $response = $this->fetchDataset(
                    $sourceUrl,
                    $source['query'],
                    $definition['slug'],
                );
                $linePrimaryDataset = [
                    'url' => $sourceUrl,
                    'response' => $response,
                ];
                $datasets[] = $linePrimaryDataset;

                foreach ($this->pointsFromJsonStat($response, $definition['frequency']) as $point) {
                    $pointsByPeriod[$point['period']] = $point;
                }
            }

            if ($pointsByPeriod === []) {
                throw new RuntimeException("SCB returned no usable values for {$definition['slug']} ({$line['label']}).");
            }

            $primaryDataset ??= $linePrimaryDataset;

            foreach ($pointsByPeriod as $point) {
                $points[] = [
                    ...$point,
                    'geography' => $line['code'],
                    'geography_label' => $line['label'],
                    'line_order' => $lineOrder,
                ];
            }
        }

        if ($primaryDataset === null) {
            throw new RuntimeException("SCB returned no usable values for {$definition['slug']}.");
        }

        $sourceUrl = $primaryDataset['url'];
        $response = $primaryDataset['response'];
        usort(
            $points,
            fn (array $left, array $right): int => [$left['period_start'], $left['line_order']]
                <=> [$right['period_start'], $right['line_order']],
        );

        foreach ($points as $index => &$point) {
            $point['sort_order'] = $index;
            unset($point['line_order']);
        }
        unset($point);

        return $this->store($definition, $sourceUrl, $response, $points, $datasets);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function syncCalculated(array $definition, string $baseUrl): StatisticSeries
    {
        $calculation = $definition['calculation'];

        if (($calculation['type'] ?? null) !== 'percentage_ratio') {
            throw new RuntimeException("Unsupported SCB calculation for {$definition['slug']}.");
        }

        $datasets = [];

        foreach (['numerator', 'denominator'] as $part) {
            $source = $calculation[$part] ?? null;

            if (! is_array($source)) {
                throw new RuntimeException("SCB calculation {$definition['slug']} has no {$part} source.");
            }

            $url = "{$baseUrl}/{$source['path']}";
            $response = $this->fetchDataset($url, $source['query'], $definition['slug']);
            $datasets[$part] = [
                'url' => $url,
                'response' => $response,
                'points' => $this->annualValues(
                    $this->pointsFromJsonStat($response, $source['frequency']),
                    $source['frequency'],
                ),
            ];
        }

        $points = [];

        foreach ($datasets['denominator']['points'] as $year => $denominator) {
            $numerator = $datasets['numerator']['points'][$year] ?? null;

            if ($numerator === null || $denominator == 0.0) {
                continue;
            }

            $points[] = [
                'period' => $year,
                'period_start' => "{$year}-01-01",
                'value' => $numerator / $denominator * 100,
                'sort_order' => count($points),
                'geography' => 'SE',
                'geography_label' => 'Sverige',
            ];
        }

        if ($points === []) {
            throw new RuntimeException("SCB returned no matching values for {$definition['slug']}.");
        }

        $sourceDatasets = array_map(
            fn (array $dataset): array => Arr::only($dataset, ['url', 'response']),
            array_values($datasets),
        );

        return $this->store(
            $definition,
            $datasets['numerator']['url'],
            $datasets['numerator']['response'],
            $points,
            $sourceDatasets,
        );
    }

    /**
     * @param  list<array{period: string, period_start: string, value: float, sort_order: int}>  $points
     * @return array<string, float>
     */
    private function annualValues(array $points, string $frequency): array
    {
        $values = [];

        foreach ($points as $point) {
            if ($frequency === 'month' && ! str_ends_with($point['period'], 'M12')) {
                continue;
            }

            if (preg_match('/^(\d{4})/', $point['period'], $matches) === 1) {
                $values[$matches[1]] = $point['value'];
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $response
     * @param  list<array<string, mixed>>  $points
     * @param  list<array{url: string, response: array<string, mixed>}>  $datasets
     */
    private function store(
        array $definition,
        string $sourceUrl,
        array $response,
        array $points,
        array $datasets,
    ): StatisticSeries {
        return DB::transaction(function () use ($definition, $sourceUrl, $response, $points, $datasets): StatisticSeries {
            $series = StatisticSeries::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'source_name' => (string) ($response['source'] ?? 'SCB'),
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
                        'labels' => array_map(
                            fn (array $dataset): mixed => $dataset['response']['label'] ?? null,
                            $datasets,
                        ),
                        'notes' => collect($datasets)
                            ->flatMap(fn (array $dataset): array => Arr::wrap($dataset['response']['note'] ?? []))
                            ->values()
                            ->all(),
                        'source_urls' => array_column($datasets, 'url'),
                    ],
                ],
            );

            $pointRows = array_map(
                fn (array $point): array => [
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

    /**
     * @param  array<string, list<string>>  $query
     * @return array<string, mixed>
     */
    private function fetchDataset(string $sourceUrl, array $query, string $slug): array
    {
        $response = $this->http()->post($sourceUrl, [
            'query' => collect($query)
                ->map(fn (array $values, string $code): array => [
                    'code' => $code,
                    'selection' => [
                        'filter' => $values === ['*'] ? 'all' : 'item',
                        'values' => $values,
                    ],
                ])
                ->values()
                ->all(),
            'response' => ['format' => 'json-stat2'],
        ])->throw()->json();

        if (! is_array($response)) {
            throw new RuntimeException("SCB returned an invalid response for {$slug}.");
        }

        return $response;
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(3, 500);
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return list<array{period: string, period_start: string, value: float, sort_order: int}>
     */
    private function pointsFromJsonStat(array $dataset, string $frequency): array
    {
        $ids = $dataset['id'] ?? null;
        $sizes = $dataset['size'] ?? null;
        $dimensions = $dataset['dimension'] ?? null;
        $values = $dataset['value'] ?? null;

        if (! is_array($ids) || ! is_array($sizes) || ! is_array($dimensions) || ! is_array($values)) {
            throw new RuntimeException('SCB JSON-stat response is missing required dimensions.');
        }

        $normalizedSizes = [];

        foreach ($sizes as $size) {
            if (! is_int($size) || $size < 1) {
                throw new RuntimeException('SCB JSON-stat response contains an invalid dimension size.');
            }

            $normalizedSizes[] = $size;
        }

        $timeId = Arr::first(Arr::wrap(data_get($dataset, 'role.time')));
        $timePosition = is_string($timeId) ? array_search($timeId, $ids, true) : false;

        if (! is_int($timePosition) || ! isset($normalizedSizes[$timePosition], $dimensions[$timeId])) {
            throw new RuntimeException('SCB JSON-stat response has no time dimension.');
        }

        $periods = $this->orderedCategoryCodes($dimensions[$timeId]);
        $timeStride = 1;

        foreach (array_slice($normalizedSizes, $timePosition + 1) as $size) {
            $timeStride *= $size;
        }

        $timeSize = $normalizedSizes[$timePosition];
        $totals = array_fill(0, $timeSize, 0.0);
        $hasValue = array_fill(0, $timeSize, false);

        foreach ($values as $flatIndex => $value) {
            if (! is_numeric($flatIndex) || ! is_numeric($value)) {
                continue;
            }

            $timeIndex = intdiv((int) $flatIndex, $timeStride) % $timeSize;
            $totals[$timeIndex] += (float) $value;
            $hasValue[$timeIndex] = true;
        }

        $points = [];

        foreach ($periods as $index => $period) {
            if (! ($hasValue[$index] ?? false)) {
                continue;
            }

            $points[] = [
                'period' => $period,
                'period_start' => $this->periodStart($period, $frequency),
                'value' => $totals[$index],
                'sort_order' => $index,
            ];
        }

        return $points;
    }

    /**
     * @param  array<string, mixed>  $dimension
     * @return list<string>
     */
    private function orderedCategoryCodes(array $dimension): array
    {
        $index = data_get($dimension, 'category.index');

        if (is_array($index)) {
            asort($index);

            return array_map('strval', array_keys($index));
        }

        if (is_string($index)) {
            return [$index];
        }

        throw new RuntimeException('SCB JSON-stat time categories are invalid.');
    }

    private function periodStart(string $period, string $frequency): string
    {
        if ($frequency === 'month' && preg_match('/^(\d{4})M(\d{2})$/', $period, $matches) === 1) {
            return "{$matches[1]}-{$matches[2]}-01";
        }

        if ($frequency === 'quarter' && preg_match('/^(\d{4})K([1-4])$/', $period, $matches) === 1) {
            $month = ((int) $matches[2] - 1) * 3 + 1;

            return sprintf('%s-%02d-01', $matches[1], $month);
        }

        if ($frequency === 'half_year' && preg_match('/^(\d{4})H([1-2])$/', $period, $matches) === 1) {
            $month = $matches[2] === '1' ? 1 : 7;

            return sprintf('%s-%02d-01', $matches[1], $month);
        }

        if (preg_match('/^\d{4}$/', $period) === 1) {
            return "{$period}-01-01";
        }

        if (preg_match('/^(\d{4})[-\/]\d{2,4}$/', $period, $matches) === 1) {
            return "{$matches[1]}-01-01";
        }

        throw new RuntimeException("Unsupported SCB period format: {$period}.");
    }
}
