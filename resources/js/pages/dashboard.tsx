import { Head } from '@inertiajs/react';
import { DatabaseZap, Heart, Landmark, RefreshCw, Search } from 'lucide-react';
import { useState } from 'react';
import { StatisticCard } from '@/components/statistic-card';
import type { StatisticSeries } from '@/types';

export default function Dashboard({ series }: { series: StatisticSeries[] }) {
    const [selectedCategories, setSelectedCategories] = useState<string[]>([]);
    const [search, setSearch] = useState('');
    const latestSync = series
        .map((item) => item.syncedAt)
        .filter((value): value is string => value !== null)
        .sort()
        .at(-1);
    const categories = [...new Set(series.map((item) => item.category))];
    const query = search.trim().toLocaleLowerCase('sv');
    const filteredSeries = series.filter(
        (item) =>
            (selectedCategories.length === 0 ||
                selectedCategories.includes(item.category)) &&
            (query === '' ||
                `${item.title} ${item.description} ${item.category}`
                    .toLocaleLowerCase('sv')
                    .includes(query)),
    );

    return (
        <>
            <Head title="Sverige i siffror">
                <meta
                    name="description"
                    content="Officiell statistik från SCB och Världsbanken, förklarad med tydliga och interaktiva grafer."
                />
            </Head>

            <main className="relative min-h-screen overflow-hidden bg-[#f8f1e8] text-[#3d2d28]">
                <div className="pointer-events-none absolute -top-40 -left-40 size-[34rem] rounded-full bg-[#eeb9a8]/25 blur-3xl" />
                <div className="pointer-events-none absolute top-[35rem] -right-52 size-[38rem] rounded-full bg-[#e5cfaa]/25 blur-3xl" />

                <div className="relative mx-auto max-w-[1500px] px-4 pb-16 sm:px-7 lg:px-10">
                    <header className="flex items-center justify-between py-6 sm:py-8">
                        <a
                            href="/"
                            className="flex items-center gap-3"
                            aria-label="Sverige i siffror"
                        >
                            <span className="flex size-11 items-center justify-center rounded-2xl bg-[#c75d50] text-white shadow-[0_8px_25px_rgba(199,93,80,0.22)]">
                                <Landmark className="size-5" />
                            </span>
                            <span>
                                <span className="block text-base font-semibold tracking-[-0.02em]">
                                    Sverige i siffror
                                </span>
                                <span className="block text-xs text-[#8b746b]">
                                    Officiell data, tydligt berättad
                                </span>
                            </span>
                        </a>
                    </header>

                    <section className="grid gap-8 py-12 sm:py-20 lg:grid-cols-[1.25fr_0.75fr] lg:items-end">
                        <div>
                            <div className="mb-6 inline-flex items-center gap-2 rounded-full bg-[#f3ddd5] px-4 py-2 text-xs font-semibold tracking-[0.12em] text-[#9f4f45] uppercase">
                                <DatabaseZap className="size-4" />
                                Verifierat från officiella källor
                            </div>
                            <h1 className="max-w-4xl text-5xl leading-[0.98] font-semibold tracking-[-0.065em] text-balance sm:text-7xl lg:text-[5.6rem]">
                                Sverige, förklarat med{' '}
                                <span className="text-[#c75d50]">
                                    riktiga siffror.
                                </span>
                            </h1>
                        </div>
                        <div className="lg:pb-2">
                            <p className="max-w-xl text-lg leading-relaxed text-[#725e56]">
                                Utforska samhällsutvecklingen utan fackspråk.
                                Varje graf visar vad axlarna betyder, vem som
                                publicerat datan och när den senast
                                uppdaterades.
                            </p>
                            <div className="mt-6 flex flex-wrap items-center gap-4 text-xs text-[#8b746b]">
                                <span className="flex items-center gap-2">
                                    <RefreshCw className="size-4" />
                                    {latestSync
                                        ? `Lokalt uppdaterad ${new Intl.DateTimeFormat(
                                              'sv-SE',
                                              {
                                                  day: 'numeric',
                                                  month: 'short',
                                                  year: 'numeric',
                                              },
                                          ).format(new Date(latestSync))}`
                                        : 'Väntar på första synkronisering'}
                                </span>
                                <span className="flex items-center gap-2">
                                    <Heart className="size-4" />
                                    Fri att använda
                                </span>
                            </div>
                        </div>
                    </section>

                    {series.length > 0 ? (
                        <section aria-labelledby="statistics-title">
                            <div className="mb-7 flex items-end justify-between gap-4">
                                <div>
                                    <p className="text-xs font-semibold tracking-[0.17em] text-[#a16c5e] uppercase">
                                        Samhällsläget
                                    </p>
                                    <h2
                                        id="statistics-title"
                                        className="mt-2 text-3xl font-semibold tracking-[-0.045em]"
                                    >
                                        Börja med en siffra
                                    </h2>
                                </div>
                                <p className="hidden text-sm text-[#8b746b] sm:block">
                                    {series.length} tidsserier · klicka för att
                                    utforska
                                </p>
                            </div>
                            <div className="mb-7 rounded-[1.75rem] border border-white/75 bg-white/55 p-3 shadow-[0_12px_40px_rgba(104,71,56,0.06)]">
                                <label className="relative block">
                                    <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[#9a8379]" />
                                    <span className="sr-only">
                                        Sök bland statistiken
                                    </span>
                                    <input
                                        type="search"
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Sök befolkning, inkomst, el..."
                                        className="h-12 w-full rounded-2xl border border-[#eadbd0] bg-[#fffaf4]/85 pr-4 pl-11 text-sm text-[#3d2d28] outline-none placeholder:text-[#a58f85] focus:border-[#c75d50]/55 focus:ring-3 focus:ring-[#c75d50]/10"
                                    />
                                </label>
                                <div
                                    className="mt-3 flex flex-wrap gap-2"
                                    aria-label="Filtrera efter ämne"
                                >
                                    <button
                                        type="button"
                                        aria-pressed={
                                            selectedCategories.length === 0
                                        }
                                        onClick={() =>
                                            setSelectedCategories([])
                                        }
                                        className={`rounded-full px-4 py-2 text-xs font-medium transition ${
                                            selectedCategories.length === 0
                                                ? 'bg-[#c75d50] text-white shadow-sm'
                                                : 'bg-[#f5eee5] text-[#725e56] hover:bg-[#f2e1d8]'
                                        }`}
                                    >
                                        Alla
                                    </button>
                                    {categories.map((item) => (
                                        <button
                                            key={item}
                                            type="button"
                                            aria-pressed={selectedCategories.includes(
                                                item,
                                            )}
                                            onClick={() =>
                                                setSelectedCategories(
                                                    (selected) =>
                                                        selected.includes(item)
                                                            ? selected.filter(
                                                                  (category) =>
                                                                      category !==
                                                                      item,
                                                              )
                                                            : [
                                                                  ...selected,
                                                                  item,
                                                              ],
                                                )
                                            }
                                            className={`rounded-full px-4 py-2 text-xs font-medium transition ${
                                                selectedCategories.includes(
                                                    item,
                                                )
                                                    ? 'bg-[#c75d50] text-white shadow-sm'
                                                    : 'bg-[#f5eee5] text-[#725e56] hover:bg-[#f2e1d8]'
                                            }`}
                                        >
                                            {item}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <div className="grid auto-rows-auto gap-5 lg:grid-cols-2 xl:grid-cols-3">
                                {filteredSeries.map((item, index) => (
                                    <StatisticCard
                                        key={item.slug}
                                        series={item}
                                        featured={index === 0 || index === 3}
                                    />
                                ))}
                            </div>
                            {filteredSeries.length === 0 && (
                                <div className="rounded-[2rem] border border-dashed border-[#ddc9bc] bg-white/35 px-6 py-16 text-center text-[#78665f]">
                                    Ingen statistik matchar din sökning.
                                </div>
                            )}
                        </section>
                    ) : (
                        <section className="rounded-[2.5rem] border border-white/80 bg-white/60 px-6 py-20 text-center shadow-[0_18px_60px_rgba(104,71,56,0.08)]">
                            <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-[#f3ddd5] text-[#a65348]">
                                <DatabaseZap className="size-6" />
                            </span>
                            <h2 className="mt-5 text-2xl font-semibold tracking-[-0.035em]">
                                Väntar på officiell statistik
                            </h2>
                            <p className="mx-auto mt-2 max-w-lg text-[#78665f]">
                                Ingen exempeldata visas. Kör bakgrundsjobbet{' '}
                                <code className="rounded-md bg-[#f2e5d8] px-2 py-1 text-sm">
                                    php artisan statistics:sync
                                </code>{' '}
                                och en queue worker för att hämta verifierade
                                värden från SCB och Världsbanken.
                            </p>
                        </section>
                    )}

                    <footer className="mt-16 flex flex-col gap-3 border-t border-[#dfcfc3] py-8 text-xs text-[#8b746b] sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            Data lagras lokalt och uppdateras automatiskt varje
                            natt.
                        </p>
                        <p>Officiell statistik från SCB och Världsbanken.</p>
                    </footer>
                </div>
            </main>
        </>
    );
}
