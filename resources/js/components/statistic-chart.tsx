import { useState } from 'react';
import type { PointerEvent } from 'react';
import {
    Area,
    Brush,
    CartesianGrid,
    ComposedChart,
    Legend,
    Line,
    ReferenceArea,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import type { LabelProps } from 'recharts';
import type { StatisticSeries } from '@/types';

type StatisticChartProps = {
    series: StatisticSeries;
    interactive?: boolean;
};

type GovernmentKind = 'coalition' | 'left' | 'right' | 'right-sd';

type GovernmentPeriod = {
    start: string;
    end: string;
    kind: GovernmentKind;
};

type ChartPoint = {
    period: string;
    timestamp: number;
    [geography: string]: string | number | null;
};

type TimeRange = {
    start: number;
    end: number;
};

export type ChartEvent = {
    date: string;
    shortTitle: string;
    title: string;
    description: string;
    categories: string[];
};

const accents: Record<StatisticSeries['accent'], string> = {
    blue: '#527b9d',
    coral: '#c75d50',
    green: '#58806b',
    ochre: '#bd8245',
    plum: '#8e6175',
};

const comparisonColors = [
    '#527b9d',
    '#bd8245',
    '#8e6175',
    '#58806b',
    '#d97768',
    '#6f5a8a',
    '#9c7b45',
    '#467f83',
    '#a45c72',
];

const governments: GovernmentPeriod[] = [
    { start: '1905-08-01', end: '1920-02-29', kind: 'right' },
    { start: '1920-03-01', end: '1920-10-31', kind: 'left' },
    { start: '1920-11-01', end: '1921-09-30', kind: 'right' },
    { start: '1921-10-01', end: '1923-03-31', kind: 'left' },
    { start: '1923-04-01', end: '1924-09-30', kind: 'right' },
    { start: '1924-10-01', end: '1926-05-31', kind: 'left' },
    { start: '1926-06-01', end: '1932-09-30', kind: 'right' },
    { start: '1932-10-01', end: '1976-09-30', kind: 'left' },
    { start: '1976-10-01', end: '1982-09-30', kind: 'right' },
    { start: '1982-10-01', end: '1991-09-30', kind: 'left' },
    { start: '1991-10-01', end: '1994-09-30', kind: 'right' },
    { start: '1994-10-01', end: '2006-09-30', kind: 'left' },
    { start: '2006-10-01', end: '2014-09-30', kind: 'right' },
    { start: '2014-10-01', end: '2021-11-30', kind: 'coalition' },
    { start: '2021-12-01', end: '2022-09-30', kind: 'left' },
    { start: '2022-10-01', end: '2100-12-31', kind: 'right-sd' },
];

const chartEvents: ChartEvent[] = [
    {
        date: '1973-10-17',
        shortTitle: 'Oljekris',
        title: 'Oljekrisen 1973',
        description:
            'Kraftigt högre oljepriser påverkade inflation, energianvändning och konjunktur.',
        categories: [
            'Priser',
            'Ekonomi',
            'Energi',
            'Hushållsekonomi',
            'Offentlig ekonomi',
            'Internationellt',
        ],
    },
    {
        date: '1990-01-01',
        shortTitle: '90-talskris',
        title: 'Svenska finanskrisen',
        description:
            'Bank- och fastighetskrisen följdes av lågkonjunktur, stigande arbetslöshet och pressade offentliga finanser.',
        categories: [
            'Arbetsmarknad',
            'Boende',
            'Ekonomi',
            'Hushållsekonomi',
            'Offentlig ekonomi',
        ],
    },
    {
        date: '1995-01-01',
        shortTitle: 'EU-inträde',
        title: 'Sverige går med i EU',
        description:
            'EU-medlemskapet förändrade Sveriges handel, regelverk och ekonomiska integration.',
        categories: ['Arbetsmarknad', 'Ekonomi', 'Internationellt'],
    },
    {
        date: '2000-03-10',
        shortTitle: 'IT-krasch',
        title: 'IT-bubblan spricker',
        description:
            'Teknikaktier föll kraftigt och den internationella konjunkturen bromsade.',
        categories: ['Arbetsmarknad', 'Ekonomi', 'Internationellt'],
    },
    {
        date: '2008-09-15',
        shortTitle: 'Finanskris',
        title: 'Globala finanskrisen',
        description:
            'Lehman Brothers kollaps utlöste en global kredit- och konjunkturkris.',
        categories: [
            'Arbetsmarknad',
            'Boende',
            'Ekonomi',
            'Hushållsekonomi',
            'Offentlig ekonomi',
            'Internationellt',
        ],
    },
    {
        date: '2010-05-02',
        shortTitle: 'Eurokris',
        title: 'Skuldkrisen i euroområdet',
        description:
            'Statsskuldproblem och åtstramningar dämpade europeisk efterfrågan under flera år.',
        categories: [
            'Arbetsmarknad',
            'Ekonomi',
            'Hushållsekonomi',
            'Offentlig ekonomi',
            'Internationellt',
        ],
    },
    {
        date: '2015-09-01',
        shortTitle: 'Flyktingkris',
        title: 'Europeiska flyktingsituationen',
        description:
            'Ett ovanligt stort antal människor sökte skydd i Sverige och övriga Europa.',
        categories: ['Befolkning', 'Arbetsmarknad', 'Internationellt'],
    },
    {
        date: '2020-03-11',
        shortTitle: 'Covid-19',
        title: 'Covid-19-pandemin',
        description:
            'Restriktioner och beteendeförändringar påverkade hälsa, resande, arbete, utsläpp och ekonomi.',
        categories: [
            'Arbetsmarknad',
            'Befolkning',
            'Boende',
            'Demokrati',
            'Ekonomi',
            'Energi',
            'Hälsa',
            'Hushållsekonomi',
            'Internationellt',
            'Miljö',
            'Offentlig ekonomi',
            'Priser',
        ],
    },
    {
        date: '2022-02-24',
        shortTitle: 'Energikris',
        title: 'Rysslands fullskaliga invasion av Ukraina',
        description:
            'Kriget bidrog till energikris, hög inflation och svagare europeisk konjunktur.',
        categories: [
            'Ekonomi',
            'Energi',
            'Hushållsekonomi',
            'Internationellt',
            'Miljö',
            'Offentlig ekonomi',
            'Priser',
        ],
    },
];

function formatValue(value: number, unit: string): string {
    const integerUnits = ['personer', 'lägenheter', 'GWh', 'miljoner kr'];
    const maximumFractionDigits =
        unit === 'Gini' ? 3 : integerUnits.includes(unit) ? 0 : 1;

    return `${new Intl.NumberFormat('sv-SE', { maximumFractionDigits }).format(value)} ${unit}`;
}

const frequencyLabels: Record<StatisticSeries['frequency'], string> = {
    year: 'år',
    month: 'månad',
    quarter: 'kvartal',
    half_year: 'halvår',
};

export function frequencyLabel(
    frequency: StatisticSeries['frequency'],
    capitalize = false,
): string {
    const label = frequencyLabels[frequency];

    return capitalize
        ? label[0].toLocaleUpperCase('sv') + label.slice(1)
        : label;
}

export function formatPeriod(period: string): string {
    return period.replace(/([MKH])/, '–$1');
}

function tickPeriod(period: string): string {
    return formatPeriod(period);
}

function timestamp(value: string): number {
    return new Date(`${value}T00:00:00Z`).getTime();
}

function pointerTimestamp(
    event: PointerEvent<HTMLDivElement>,
    data: ChartPoint[],
): number | null {
    const firstTimestamp = data.at(0)?.timestamp;
    const lastTimestamp = data.at(-1)?.timestamp;

    if (
        firstTimestamp === undefined ||
        lastTimestamp === undefined ||
        data.length < 2
    ) {
        return null;
    }

    const bounds = event.currentTarget.getBoundingClientRect();
    const plotStart = bounds.left + 80;
    const plotEnd = bounds.right - 12;

    if (
        plotEnd <= plotStart ||
        event.clientX < plotStart ||
        event.clientX > plotEnd
    ) {
        return null;
    }

    const ratio = (event.clientX - plotStart) / (plotEnd - plotStart);
    const target = firstTimestamp + ratio * (lastTimestamp - firstTimestamp);
    let closest = data[0];

    for (const point of data) {
        if (
            Math.abs(point.timestamp - target) <
            Math.abs(closest.timestamp - target)
        ) {
            closest = point;
        }
    }

    return closest.timestamp;
}

function EventMarkerLabel({
    value,
    viewBox,
    parentViewBox,
    row,
}: LabelProps & { row: number }) {
    if (!viewBox || !('x' in viewBox)) {
        return null;
    }

    const label = String(value);
    const width = Math.max(58, label.length * 6.2 + 16);
    const parent =
        parentViewBox && 'x' in parentViewBox ? parentViewBox : viewBox;
    const right = parent.x + parent.width;
    const x =
        viewBox.x + width + 8 <= right ? viewBox.x + 6 : viewBox.x - width - 6;
    const y = parent.y + 8 + (row % 3) * 24;

    return (
        <g aria-hidden="true" pointerEvents="none">
            <rect
                x={x}
                y={y}
                width={width}
                height={20}
                rx={10}
                fill="#fffaf4"
                stroke="#6f5a8a"
                strokeOpacity={0.35}
            />
            <text
                x={x + width / 2}
                y={y + 13.5}
                fill="#5e4973"
                fontSize={10}
                fontWeight={600}
                textAnchor="middle"
            >
                {label}
            </text>
        </g>
    );
}

function tickTimestamp(
    value: number,
    frequency: StatisticSeries['frequency'],
): string {
    return new Intl.DateTimeFormat('sv-SE', {
        year: 'numeric',
        month: frequency === 'month' ? '2-digit' : undefined,
        timeZone: 'UTC',
    }).format(new Date(value));
}

export function eventsForSeries(series: StatisticSeries): ChartEvent[] {
    const starts = series.points.map((point) => timestamp(point.periodStart));
    const firstTimestamp = Math.min(...starts);
    const lastTimestamp = Math.max(...starts);

    return chartEvents.filter((event) => {
        const eventTimestamp = timestamp(event.date);

        return (
            event.categories.includes(series.category) &&
            eventTimestamp >= firstTimestamp &&
            eventTimestamp <= lastTimestamp
        );
    });
}

function chartData(series: StatisticSeries, interactive: boolean) {
    const geographyLabels = new Map<string, string>();

    for (const point of series.points) {
        geographyLabels.set(point.geography, point.geographyLabel);
    }

    const geographies = [...geographyLabels.keys()].sort((a, b) => {
        const order = ['SE', 'EU', 'WLD'];
        const left = order.indexOf(a);
        const right = order.indexOf(b);

        return (
            (left === -1 ? order.length : left) -
            (right === -1 ? order.length : right)
        );
    });
    const periods = [
        ...new Map(
            series.points.map((point) => [
                point.period,
                {
                    period: point.period,
                    timestamp: timestamp(point.periodStart),
                },
            ]),
        ).values(),
    ].sort((a, b) => a.timestamp - b.timestamp);
    const visiblePeriods = (
        interactive
            ? periods
            : periods.slice(series.frequency === 'month' ? -48 : -70)
    ).map((point) => point.period);
    const visible = new Set(visiblePeriods);
    const rows = new Map<string, ChartPoint>(
        periods
            .filter((point) => visible.has(point.period))
            .map((point) => [point.period, point]),
    );

    for (const point of series.points) {
        if (visible.has(point.period)) {
            rows.get(point.period)![point.geography] = point.value;
        }
    }

    return {
        data: [...rows.values()],
        geographies,
        geographyLabels,
    };
}

export function StatisticChart({
    series,
    interactive = false,
}: StatisticChartProps) {
    const [selection, setSelection] = useState<TimeRange | null>(null);
    const [zoom, setZoom] = useState<TimeRange | null>(null);
    const color = accents[series.accent];
    const {
        data: completeData,
        geographies,
        geographyLabels,
    } = chartData(series, interactive);
    const data = zoom
        ? completeData.filter(
              ({ timestamp }) =>
                  timestamp >= zoom.start && timestamp <= zoom.end,
          )
        : completeData;
    const firstTimestamp = data.at(0)?.timestamp;
    const lastTimestamp = data.at(-1)?.timestamp;
    const visibleGovernments =
        firstTimestamp !== undefined && lastTimestamp !== undefined
            ? governments.filter(
                  ({ start, end }) =>
                      timestamp(end) >= firstTimestamp &&
                      timestamp(start) <= lastTimestamp,
              )
            : [];
    const visibleEvents = interactive ? eventsForSeries(series) : [];
    const patternPrefix = `${series.slug}-${interactive ? 'detail' : 'card'}`;
    const hasComparisons = geographies.length > 1;
    const zoomLabel =
        zoom && data.length > 0
            ? `${formatPeriod(data[0].period)} till ${formatPeriod(data.at(-1)!.period)}`
            : null;

    const startSelection = (event: PointerEvent<HTMLDivElement>) => {
        if (
            !interactive ||
            event.button !== 0 ||
            (event.target instanceof Element &&
                event.target.closest('.recharts-brush'))
        ) {
            return;
        }

        const value = pointerTimestamp(event, data);

        if (value !== null) {
            event.preventDefault();
            event.currentTarget.setPointerCapture(event.pointerId);
            setSelection({ start: value, end: value });
        }
    };

    const updateSelection = (event: PointerEvent<HTMLDivElement>) => {
        if (!selection) {
            return;
        }

        const value = pointerTimestamp(event, data);

        if (value !== null) {
            setSelection({ ...selection, end: value });
        }
    };

    const finishSelection = (event: PointerEvent<HTMLDivElement>) => {
        if (!selection) {
            return;
        }

        const activeValue = pointerTimestamp(event, data);
        const end = activeValue ?? selection.end;
        const start = Math.min(selection.start, end);
        const finish = Math.max(selection.start, end);
        const selectedPoints = data.filter(
            ({ timestamp }) => timestamp >= start && timestamp <= finish,
        );

        if (selectedPoints.length >= 2) {
            setZoom({
                start: selectedPoints[0].timestamp,
                end: selectedPoints.at(-1)!.timestamp,
            });
        }

        setSelection(null);

        if (event.currentTarget.hasPointerCapture(event.pointerId)) {
            event.currentTarget.releasePointerCapture(event.pointerId);
        }
    };

    return (
        <div
            className={
                interactive
                    ? 'flex h-[50vh] min-h-96 w-full min-w-0 flex-col'
                    : 'h-64 w-full min-w-0'
            }
            aria-label={`${series.title}, ${series.unit} per ${frequencyLabel(series.frequency)}`}
        >
            {interactive && (
                <div className="mb-1 flex min-h-7 items-center justify-between gap-3 px-2 text-xs text-[#78665f]">
                    <span>
                        {zoomLabel
                            ? `Visar ${zoomLabel}`
                            : 'Dra över grafen för att zooma in'}
                    </span>
                    {zoom && (
                        <button
                            type="button"
                            className="rounded-full bg-[#f5eee5] px-3 py-1 font-medium text-[#725e56] transition hover:bg-[#f2e1d8]"
                            onClick={() => {
                                setZoom(null);
                                setSelection(null);
                            }}
                        >
                            Visa hela perioden
                        </button>
                    )}
                </div>
            )}
            <div
                className={
                    interactive
                        ? 'min-h-0 flex-1 cursor-crosshair touch-pan-y select-none'
                        : 'h-full'
                }
                onPointerDown={startSelection}
                onPointerMove={updateSelection}
                onPointerUp={finishSelection}
                onPointerCancel={() => setSelection(null)}
            >
                <ResponsiveContainer width="100%" height="100%">
                    <ComposedChart
                        data={data}
                        margin={{
                            top: 10,
                            right: 12,
                            bottom: interactive ? 20 : 4,
                            left: 8,
                        }}
                    >
                        <defs>
                            <pattern
                                id={`${patternPrefix}-coalition`}
                                width="24"
                                height="24"
                                patternUnits="userSpaceOnUse"
                            >
                                <rect
                                    width="12"
                                    height="24"
                                    fill="#c75d50"
                                    fillOpacity="0.16"
                                />
                                <rect
                                    x="12"
                                    width="12"
                                    height="24"
                                    fill="#58806b"
                                    fillOpacity="0.16"
                                />
                            </pattern>
                            <pattern
                                id={`${patternPrefix}-right-sd`}
                                width="24"
                                height="24"
                                patternUnits="userSpaceOnUse"
                            >
                                <rect
                                    width="12"
                                    height="24"
                                    fill="#527b9d"
                                    fillOpacity="0.16"
                                />
                                <rect
                                    x="12"
                                    width="12"
                                    height="24"
                                    fill="#d4aa3d"
                                    fillOpacity="0.2"
                                />
                            </pattern>
                        </defs>
                        <CartesianGrid
                            vertical={false}
                            stroke="#5b4640"
                            strokeDasharray="2 7"
                            opacity={0.14}
                        />
                        {visibleGovernments.map((government) => {
                            const x1 = Math.max(
                                timestamp(government.start),
                                firstTimestamp!,
                            );
                            const x2 = Math.min(
                                timestamp(government.end),
                                lastTimestamp!,
                            );
                            const fill =
                                government.kind === 'left'
                                    ? '#c75d50'
                                    : government.kind === 'right'
                                      ? '#527b9d'
                                      : `url(#${patternPrefix}-${government.kind})`;

                            return (
                                <ReferenceArea
                                    key={`${government.start}-${government.kind}`}
                                    x1={x1}
                                    x2={x2}
                                    fill={fill}
                                    fillOpacity={
                                        government.kind === 'left' ||
                                        government.kind === 'right'
                                            ? 0.09
                                            : 1
                                    }
                                    strokeOpacity={0}
                                    ifOverflow="hidden"
                                />
                            );
                        })}
                        {selection && (
                            <ReferenceArea
                                x1={selection.start}
                                x2={selection.end}
                                fill={color}
                                fillOpacity={0.18}
                                stroke={color}
                                strokeOpacity={0.55}
                                ifOverflow="hidden"
                            />
                        )}
                        <XAxis
                            dataKey="timestamp"
                            type="number"
                            scale="time"
                            domain={['dataMin', 'dataMax']}
                            axisLine={false}
                            tickLine={false}
                            minTickGap={interactive ? 35 : 24}
                            tick={{ fill: '#78665f', fontSize: 11 }}
                            tickFormatter={(value: number) =>
                                tickTimestamp(value, series.frequency)
                            }
                            label={
                                interactive
                                    ? {
                                          value: frequencyLabel(
                                              series.frequency,
                                              true,
                                          ),
                                          position: 'insideBottom',
                                          offset: -14,
                                          fill: '#78665f',
                                          fontSize: 12,
                                      }
                                    : undefined
                            }
                        />
                        <YAxis
                            domain={['auto', 'auto']}
                            axisLine={false}
                            tickLine={false}
                            width={interactive ? 72 : 54}
                            tick={{ fill: '#78665f', fontSize: 11 }}
                            tickFormatter={(value: number) =>
                                new Intl.NumberFormat('sv-SE', {
                                    notation:
                                        Math.abs(value) >= 100_000
                                            ? 'compact'
                                            : 'standard',
                                    maximumFractionDigits: 1,
                                }).format(value)
                            }
                            label={
                                interactive
                                    ? {
                                          value: series.unit,
                                          angle: -90,
                                          position: 'insideLeft',
                                          fill: '#78665f',
                                          fontSize: 12,
                                      }
                                    : undefined
                            }
                        />
                        <Tooltip
                            cursor={{
                                stroke: color,
                                strokeDasharray: '3 5',
                                strokeOpacity: 0.5,
                            }}
                            contentStyle={{
                                background: '#fffaf4',
                                border: '1px solid #eadbd0',
                                borderRadius: '16px',
                                boxShadow: '0 12px 30px rgb(74 48 39 / 0.12)',
                                fontSize: '12px',
                            }}
                            labelFormatter={(_timestamp, payload) => {
                                const point = payload.at(0)?.payload as
                                    ChartPoint | undefined;

                                return `${frequencyLabel(series.frequency, true)} ${point ? tickPeriod(point.period) : ''}`;
                            }}
                            formatter={(value, name) => [
                                formatValue(Number(value), series.unit),
                                String(name),
                            ]}
                        />
                        {hasComparisons && (
                            <Legend
                                verticalAlign="top"
                                align="right"
                                iconType="circle"
                                iconSize={8}
                                wrapperStyle={{
                                    fontSize: 12,
                                    color: '#78665f',
                                }}
                            />
                        )}
                        <ReferenceLine
                            y={0}
                            stroke="#5b4640"
                            strokeOpacity={0.28}
                        />
                        {geographies.map((geography, index) => {
                            const lineColor =
                                index === 0
                                    ? color
                                    : comparisonColors[
                                          (index - 1) % comparisonColors.length
                                      ];

                            return series.chartType === 'area' ? (
                                <Area
                                    key={geography}
                                    type="monotone"
                                    dataKey={geography}
                                    name={
                                        geographyLabels.get(geography) ??
                                        geography
                                    }
                                    stackId="total"
                                    stroke={lineColor}
                                    fill={lineColor}
                                    fillOpacity={0.72}
                                    connectNulls
                                    activeDot={{
                                        r: 4,
                                        fill: lineColor,
                                        stroke: '#fffaf4',
                                        strokeWidth: 2,
                                    }}
                                />
                            ) : (
                                <Line
                                    key={geography}
                                    type="monotone"
                                    dataKey={geography}
                                    name={
                                        hasComparisons
                                            ? (geographyLabels.get(geography) ??
                                              geography)
                                            : series.shortTitle
                                    }
                                    stroke={lineColor}
                                    strokeWidth={
                                        index === 0
                                            ? interactive
                                                ? 3
                                                : 2.5
                                            : 2
                                    }
                                    strokeDasharray={
                                        geography === 'WLD' ? '5 5' : undefined
                                    }
                                    dot={false}
                                    connectNulls
                                    activeDot={{
                                        r: 4,
                                        fill: lineColor,
                                        stroke: '#fffaf4',
                                        strokeWidth: 2,
                                    }}
                                />
                            );
                        })}
                        {interactive && data.length > 16 && (
                            <Brush
                                dataKey="timestamp"
                                height={30}
                                travellerWidth={12}
                                stroke={color}
                                fill="#fffaf4"
                                tickFormatter={(value: number) =>
                                    tickTimestamp(value, series.frequency)
                                }
                            />
                        )}
                        {visibleEvents.map((event, index) => (
                            <ReferenceLine
                                key={event.date}
                                x={timestamp(event.date)}
                                stroke="#6f5a8a"
                                strokeWidth={1.5}
                                strokeDasharray="4 4"
                                ifOverflow="hidden"
                                label={
                                    <EventMarkerLabel
                                        value={event.shortTitle}
                                        row={index}
                                    />
                                }
                            />
                        ))}
                    </ComposedChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export { formatValue };
