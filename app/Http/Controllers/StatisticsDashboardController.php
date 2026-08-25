<?php

namespace App\Http\Controllers;

use App\Models\StatisticSeries;
use Inertia\Inertia;
use Inertia\Response;

class StatisticsDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $catalogSlugs = [];
        $explanations = config('statistic_explanations', []);
        $definitions = config('statistics.series', []);

        if (! is_array($explanations)) {
            $explanations = [];
        }

        if (is_array($definitions)) {
            foreach ($definitions as $definition) {
                if (is_array($definition) && is_string($definition['slug'] ?? null)) {
                    $catalogSlugs[] = $definition['slug'];
                }
            }
        }

        $records = StatisticSeries::query()
            ->whereIn('slug', $catalogSlugs)
            ->with('points')
            ->orderByRaw("CASE category
                WHEN 'Befolkning' THEN 1
                WHEN 'Arbetsmarknad' THEN 2
                WHEN 'Priser' THEN 3
                WHEN 'Hushållsekonomi' THEN 4
                WHEN 'Ekonomi' THEN 5
                WHEN 'Boende' THEN 6
                WHEN 'Utbildning' THEN 7
                WHEN 'Energi' THEN 8
                WHEN 'Miljö' THEN 9
                WHEN 'Offentlig ekonomi' THEN 10
                WHEN 'Demokrati' THEN 11
                WHEN 'Hälsa' THEN 12
                WHEN 'Levnadsförhållanden' THEN 13
                WHEN 'Internationellt' THEN 14
                ELSE 15 END")
            ->get();
        $series = [];

        foreach ($records as $item) {
            $points = [];

            foreach ($item->points as $point) {
                $points[] = [
                    'geography' => $point->geography,
                    'geographyLabel' => $point->geography_label,
                    'period' => $point->period,
                    'periodStart' => $point->period_start->toDateString(),
                    'value' => $point->value,
                ];
            }

            $series[] = [
                'slug' => $item->slug,
                'title' => $item->title,
                'shortTitle' => $item->short_title,
                'description' => $item->description,
                'explanation' => is_string($explanations[$item->slug] ?? null)
                    ? $explanations[$item->slug]
                    : $item->description,
                'unit' => $item->unit,
                'frequency' => $item->frequency,
                'category' => $item->category,
                'chartType' => $item->chart_type,
                'accent' => $item->accent,
                'sourceName' => $item->source_name,
                'sourceUrl' => $item->source_url,
                'sourceUpdatedAt' => $item->source_updated_at?->toIso8601String(),
                'syncedAt' => $item->synced_at?->toIso8601String(),
                'points' => $points,
            ];
        }

        return Inertia::render('dashboard', [
            'series' => $series,
        ]);
    }
}
