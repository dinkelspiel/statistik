export type StatisticPoint = {
    geography: string;
    geographyLabel: string;
    period: string;
    periodStart: string;
    value: number;
};

export type StatisticPointExplanation = {
    label: string;
    description: string;
};

export type StatisticSeries = {
    slug: string;
    title: string;
    shortTitle: string;
    description: string;
    explanation: string;
    unit: string;
    frequency: 'year' | 'month' | 'quarter' | 'half_year';
    category: string;
    chartType: 'area' | 'bar' | 'line';
    accent: 'blue' | 'coral' | 'green' | 'ochre' | 'plum';
    sourceName: string;
    sourceUrl: string;
    sourceUpdatedAt: string | null;
    syncedAt: string | null;
    pointExplanations: StatisticPointExplanation[];
    points: StatisticPoint[];
};
