import {
    ArrowUpRight,
    BarChart3,
    BriefcaseBusiness,
    CalendarDays,
    CloudSun,
    Coins,
    Earth,
    ExternalLink,
    GraduationCap,
    HeartPulse,
    House,
    Info,
    Landmark,
    Lightbulb,
    TrendingUp,
    UsersRound,
    Vote,
    WalletCards,
} from 'lucide-react';
import { useState } from 'react';
import {
    eventsForSeries,
    formatPeriod,
    formatValue,
    frequencyLabel,
    StatisticChart,
} from '@/components/statistic-chart';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { StatisticSeries } from '@/types';

const categoryIcons = {
    Arbetsmarknad: BriefcaseBusiness,
    Befolkning: UsersRound,
    Boende: House,
    Demokrati: Vote,
    Energi: Lightbulb,
    Ekonomi: TrendingUp,
    Hälsa: HeartPulse,
    Hushållsekonomi: WalletCards,
    Internationellt: Earth,
    Levnadsförhållanden: UsersRound,
    Miljö: CloudSun,
    'Offentlig ekonomi': Landmark,
    Priser: Coins,
    Utbildning: GraduationCap,
};

const accentClasses: Record<StatisticSeries['accent'], string> = {
    blue: 'bg-[#e5eef3] text-[#3f6685]',
    coral: 'bg-[#f5ded8] text-[#9f473f]',
    green: 'bg-[#e2eee4] text-[#486f5b]',
    ochre: 'bg-[#f4e8d1] text-[#98652f]',
    plum: 'bg-[#eee1e7] text-[#754d60]',
};

function formatDate(value: string | null): string {
    if (!value) {
        return 'Datum saknas';
    }

    return new Intl.DateTimeFormat('sv-SE', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(value));
}

function GovernmentLegend() {
    return (
        <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-[#78665f]">
            <span className="font-medium text-[#4b3832]">
                Regering från 1905:
            </span>
            <span className="flex items-center gap-2">
                <span className="size-3 rounded-full bg-[#c75d50]/35" />
                Vänsterledd
            </span>
            <span className="flex items-center gap-2">
                <span className="size-3 rounded-full bg-[#527b9d]/35" />
                Högerledd
            </span>
            <span className="flex items-center gap-2">
                <span className="h-3 w-5 rounded-sm bg-[repeating-linear-gradient(90deg,#c75d5050_0_5px,#58806b50_5px_10px)]" />
                S + MP
            </span>
            <span className="flex items-center gap-2">
                <span className="h-3 w-5 rounded-sm bg-[repeating-linear-gradient(90deg,#527b9d50_0_5px,#d4aa3d55_5px_10px)]" />
                Höger + SD-avtal
            </span>
        </div>
    );
}

export function StatisticCard({
    series,
    featured = false,
}: {
    series: StatisticSeries;
    featured?: boolean;
}) {
    const [showTable, setShowTable] = useState(false);
    const Icon =
        categoryIcons[series.category as keyof typeof categoryIcons] ??
        BarChart3;
    const primaryPoints = series.points.filter(
        (point) => point.geography === 'SE',
    );
    const latest = primaryPoints.at(-1) ?? series.points.at(-1);
    const previous = primaryPoints.at(-2) ?? series.points.at(-2);
    const change = latest && previous ? latest.value - previous.value : null;
    const geographies = [
        ...new Map(
            series.points.map((point) => [
                point.geography,
                point.geographyLabel,
            ]),
        ),
    ].sort(([left], [right]) => {
        if (left === 'SE') {
            return -1;
        }

        if (right === 'SE') {
            return 1;
        }

        return 0;
    });
    const periods = [...new Set(series.points.map((point) => point.period))]
        .sort()
        .reverse();
    const pointLookup = new Map(
        series.points.map((point) => [
            `${point.geography}:${point.period}`,
            point.value,
        ]),
    );
    const chartEvents = eventsForSeries(series);

    return (
        <Dialog>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className={`group flex min-h-[26rem] w-full flex-col overflow-hidden rounded-[2rem] border border-white/75 bg-white/65 p-5 text-left shadow-[0_18px_60px_rgba(104,71,56,0.08)] backdrop-blur-sm transition duration-300 hover:-translate-y-1 hover:bg-white/85 hover:shadow-[0_24px_70px_rgba(104,71,56,0.13)] focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-[#c75d50]/45 sm:p-7 ${
                        featured ? 'lg:col-span-2' : ''
                    }`}
                    aria-label={`Öppna detaljerad graf för ${series.title}`}
                >
                    <div className="flex w-full items-start justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <span
                                className={`flex size-11 items-center justify-center rounded-2xl ${accentClasses[series.accent]}`}
                            >
                                <Icon className="size-5" />
                            </span>
                            <div>
                                <p className="text-xs font-semibold tracking-[0.16em] text-[#8b746b] uppercase">
                                    {series.category}
                                </p>
                                <h2 className="mt-1 text-xl font-semibold tracking-[-0.025em] text-[#3d2d28]">
                                    {series.title}
                                </h2>
                            </div>
                        </div>
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-full border border-[#eadbd0] bg-[#fffaf4] text-[#9b6f61] transition group-hover:rotate-6 group-hover:bg-[#f5ded8]">
                            <ArrowUpRight className="size-4" />
                        </span>
                    </div>

                    <div className="mt-7 flex w-full items-end justify-between gap-4">
                        <div>
                            <p className="text-3xl font-semibold tracking-[-0.045em] text-[#3d2d28] sm:text-4xl">
                                {latest
                                    ? formatValue(latest.value, series.unit)
                                    : '—'}
                            </p>
                            <p className="mt-1 text-xs text-[#8b746b]">
                                Senaste värdet ·{' '}
                                {latest ? formatPeriod(latest.period) : '—'}
                            </p>
                        </div>
                        {change !== null && (
                            <span className="rounded-full bg-[#f5eee5] px-3 py-1.5 text-xs font-medium text-[#725e56]">
                                {change > 0 ? '+' : ''}
                                {new Intl.NumberFormat('sv-SE', {
                                    maximumFractionDigits:
                                        series.unit === 'personer' ? 0 : 1,
                                }).format(change)}{' '}
                                sedan förra
                            </span>
                        )}
                    </div>
                    {geographies.length > 1 && (
                        <div className="mt-4 flex flex-wrap gap-2">
                            {geographies
                                .slice(0, 4)
                                .map(([geography, label]) => (
                                    <span
                                        key={geography}
                                        className="rounded-full border border-[#eadbd0] bg-white/55 px-3 py-1 text-xs text-[#725e56]"
                                    >
                                        {label}
                                    </span>
                                ))}
                            {geographies.length > 4 && (
                                <span className="rounded-full border border-[#eadbd0] bg-white/55 px-3 py-1 text-xs text-[#725e56]">
                                    +{geographies.length - 4} fler
                                </span>
                            )}
                        </div>
                    )}

                    <div className="mt-auto w-full pt-5">
                        <StatisticChart series={series} />
                    </div>
                </button>
            </DialogTrigger>

            <DialogContent className="h-[calc(100vh-1.5rem)] w-[calc(100vw-1.5rem)] max-w-none min-w-0 overflow-hidden border-white/80 bg-[#fbf5ed] p-0 sm:max-w-none">
                <div className="min-w-0 overflow-x-hidden overflow-y-auto px-5 py-6 sm:px-9 sm:py-8 lg:px-12">
                    <DialogHeader className="pr-14">
                        <div className="mb-3 flex flex-wrap items-center gap-2">
                            <span
                                className={`inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ${accentClasses[series.accent]}`}
                            >
                                <Icon className="size-3.5" />
                                {series.category}
                            </span>
                            <span className="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1.5 text-xs text-[#78665f]">
                                <CalendarDays className="size-3.5" />
                                Uppdaterad {formatDate(series.sourceUpdatedAt)}
                            </span>
                        </div>
                        <DialogTitle className="max-w-4xl text-3xl leading-tight font-semibold tracking-[-0.045em] text-[#3d2d28] sm:text-5xl">
                            {series.title}
                        </DialogTitle>
                        <DialogDescription className="max-w-3xl pt-2 text-base leading-relaxed text-[#78665f]">
                            {series.description}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="mt-7 min-w-0 rounded-[1.75rem] border border-white/80 bg-white/65 p-3 shadow-[0_18px_55px_rgba(104,71,56,0.07)] sm:p-6">
                        <StatisticChart series={series} interactive />
                        <div className="mt-3 border-t border-[#eadbd0] px-2 pt-4">
                            <GovernmentLegend />
                        </div>
                        {chartEvents.length > 0 && (
                            <div className="mt-4 border-t border-[#eadbd0] px-2 pt-4">
                                <div className="mb-3 flex items-center gap-2 text-xs font-medium text-[#4b3832]">
                                    <span className="h-4 border-l-2 border-dashed border-[#6f5a8a]" />
                                    Händelser som kan förklara avvikelser
                                </div>
                                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                    {chartEvents.map((event) => (
                                        <div
                                            key={event.date}
                                            className="rounded-2xl bg-[#f2edf5] px-4 py-3"
                                        >
                                            <p className="text-xs font-semibold text-[#5e4973]">
                                                {event.date.slice(0, 4)} ·{' '}
                                                {event.title}
                                            </p>
                                            <p className="mt-1 text-xs leading-relaxed text-[#75687d]">
                                                {event.description}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="mt-5 grid gap-4 lg:grid-cols-[1.25fr_0.75fr]">
                        <div className="rounded-[1.75rem] bg-[#f2e5d8] p-6 sm:p-7">
                            <div className="flex items-start gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-white/65 text-[#9f594c]">
                                    <Info className="size-5" />
                                </span>
                                <div>
                                    <h3 className="font-semibold text-[#3d2d28]">
                                        Vad betyder det?
                                    </h3>
                                    <p className="mt-2 max-w-3xl text-sm leading-relaxed text-[#6f5a52]">
                                        {series.explanation}
                                    </p>
                                    <p className="mt-3 max-w-3xl text-sm leading-relaxed text-[#6f5a52]">
                                        <span className="font-semibold text-[#59443c]">
                                            Så läser du grafen:
                                        </span>{' '}
                                        Y-axeln visar {series.unit}. X-axeln
                                        visar {frequencyLabel(series.frequency)}
                                        . Dra över grafen för att zooma in på en
                                        period. Välj Visa hela perioden för att
                                        återställa.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div className="flex flex-col justify-between rounded-[1.75rem] bg-[#e7eee8] p-6 sm:p-7">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.15em] text-[#617469] uppercase">
                                    Originalkälla
                                </p>
                                <p className="mt-2 font-semibold text-[#34483d]">
                                    {series.sourceName}
                                </p>
                                <p className="mt-1 text-sm text-[#617469]">
                                    Lokalt synkad {formatDate(series.syncedAt)}
                                </p>
                            </div>
                            <a
                                href={series.sourceUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-[#486f5b] underline decoration-[#486f5b]/30 underline-offset-4"
                            >
                                Öppna hos {series.sourceName}
                                <ExternalLink className="size-4" />
                            </a>
                        </div>
                    </div>

                    <div className="mt-5">
                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            className="h-12 rounded-full border-[#ddc9bc] bg-white/65 px-6 text-[#59443c]"
                            onClick={() => setShowTable((value) => !value)}
                        >
                            <BarChart3 className="size-4" />
                            {showTable
                                ? 'Dölj datatabell'
                                : 'Visa som datatabell'}
                        </Button>
                    </div>

                    {showTable && (
                        <div className="mt-4 overflow-hidden rounded-[1.5rem] border border-[#eadbd0] bg-white/65">
                            <div className="max-h-96 overflow-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="sticky top-0 bg-[#f5e9de] text-[#59443c]">
                                        <tr>
                                            <th className="px-5 py-3 font-semibold">
                                                {frequencyLabel(
                                                    series.frequency,
                                                    true,
                                                )}
                                            </th>
                                            {geographies.map(
                                                ([geography, label]) => (
                                                    <th
                                                        key={geography}
                                                        className="px-5 py-3 text-right font-semibold"
                                                    >
                                                        {label} ({series.unit})
                                                    </th>
                                                ),
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {periods.map((period) => (
                                            <tr
                                                key={period}
                                                className="border-t border-[#eadbd0]/70"
                                            >
                                                <td className="px-5 py-3 text-[#59443c]">
                                                    {formatPeriod(period)}
                                                </td>
                                                {geographies.map(
                                                    ([geography]) => {
                                                        const value =
                                                            pointLookup.get(
                                                                `${geography}:${period}`,
                                                            );

                                                        return (
                                                            <td
                                                                key={geography}
                                                                className="px-5 py-3 text-right font-medium text-[#3d2d28]"
                                                            >
                                                                {value ===
                                                                undefined
                                                                    ? '—'
                                                                    : new Intl.NumberFormat(
                                                                          'sv-SE',
                                                                          {
                                                                              maximumFractionDigits:
                                                                                  series.unit ===
                                                                                  'personer'
                                                                                      ? 0
                                                                                      : 2,
                                                                          },
                                                                      ).format(
                                                                          value,
                                                                      )}
                                                            </td>
                                                        );
                                                    },
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
