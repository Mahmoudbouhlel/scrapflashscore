<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { CircleCheck, CircleDashed, CircleX, ExternalLink, History, ListFilter, Search, Trophy } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface HistoryRow {
    idKey: string;
    slipDate: string | null;
    slipPosition: number;
    matchId: string;
    sourceMatchUrl: string | null;
    generatedAt: string | null;
    country: string;
    league: string;
    homeTeam: string;
    awayTeam: string;
    market: string | null;
    advice: string | null;
    selectedOdd: number | null;
    probability: number | null;
    confidence: number | null;
    edge: number | null;
    dailyScore: number | null;
    totalSlipOdd: number | null;
    rationale: string | null;
    matchStatus: string;
    matchDate: string | null;
    matchTime: string | null;
    scoreHome: number | string | null;
    scoreAway: number | string | null;
    result: 'won' | 'lost' | 'pending';
    resultLabel: string;
}

const props = defineProps<{
    summary: {
        total: number;
        won: number;
        lost: number;
        pending: number;
        latestDate: string | null;
    };
    filters: {
        dates: string[];
        markets: string[];
        countries: string[];
        leagues: string[];
        results: string[];
    };
    items: HistoryRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Betslip History', href: '/betslip-history' }];

const search = ref('');
const selectedDate = ref('all');
const selectedResult = ref('all');
const selectedMarket = ref('all');
const selectedCountry = ref('all');
const selectedLeague = ref('all');

const todayValue = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Lagos' }).format(new Date());
const defaultDate = () => (props.filters.dates.includes(todayValue()) ? todayValue() : (props.summary.latestDate ?? 'all'));

onMounted(() => {
    selectedDate.value = defaultDate();
});

const formatDate = (value: string | null | undefined) => {
    if (!value) return 'TBD';
    const [year, month, day] = value.split('-');
    return year && month && day ? `${day}/${month}/${year}` : value;
};

const formatNumber = (value: number | null | undefined, digits = 1) => {
    if (value === null || value === undefined || Number.isNaN(value)) return '-';
    return value.toLocaleString('en-US', { maximumFractionDigits: digits, minimumFractionDigits: digits });
};

const formatPercent = (value: number | null | undefined) => (value === null || value === undefined ? '-' : `${formatNumber(value, 1)}%`);

const scoreLabel = (item: HistoryRow) => {
    if (item.scoreHome === null || item.scoreAway === null) return '-';
    return `${item.scoreHome} - ${item.scoreAway}`;
};

const rowTone = (result: HistoryRow['result']) => {
    if (result === 'won') return 'border-emerald-200 bg-emerald-50/80 hover:bg-emerald-100/80';
    if (result === 'lost') return 'border-rose-200 bg-rose-50/80 hover:bg-rose-100/80';
    return 'border-amber-200 bg-amber-50/80 hover:bg-amber-100/80';
};

const badgeTone = (result: HistoryRow['result']) => {
    if (result === 'won') return 'bg-emerald-600 text-white';
    if (result === 'lost') return 'bg-rose-600 text-white';
    return 'bg-amber-500 text-white';
};

const resultIcon = (result: HistoryRow['result']) => {
    if (result === 'won') return CircleCheck;
    if (result === 'lost') return CircleX;
    return CircleDashed;
};

const filteredItems = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.items.filter((item) => {
        if (selectedDate.value !== 'all' && item.slipDate !== selectedDate.value) return false;
        if (selectedResult.value !== 'all' && item.result !== selectedResult.value) return false;
        if (selectedMarket.value !== 'all' && item.market !== selectedMarket.value) return false;
        if (selectedCountry.value !== 'all' && item.country !== selectedCountry.value) return false;
        if (selectedLeague.value !== 'all' && item.league !== selectedLeague.value) return false;
        if (!term) return true;

        return [
            item.matchId,
            item.country,
            item.league,
            item.homeTeam,
            item.awayTeam,
            item.market ?? '',
            item.advice ?? '',
            item.matchStatus,
            item.resultLabel,
            item.rationale ?? '',
        ]
            .join(' ')
            .toLowerCase()
            .includes(term);
    });
});

const visibleSummary = computed(() => ({
    won: filteredItems.value.filter((item) => item.result === 'won').length,
    lost: filteredItems.value.filter((item) => item.result === 'lost').length,
    pending: filteredItems.value.filter((item) => item.result === 'pending').length,
}));

const resetFilters = () => {
    search.value = '';
    selectedDate.value = defaultDate();
    selectedResult.value = 'all';
    selectedMarket.value = 'all';
    selectedCountry.value = 'all';
    selectedLeague.value = 'all';
};
</script>

<template>
    <Head title="Betslip History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-slate-50 text-slate-950">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-[1600px] px-4 py-6 lg:px-8">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700"
                            >
                                <History class="h-3.5 w-3.5" />
                                daily betslip history
                            </div>
                            <h1 class="mt-4 text-3xl font-semibold tracking-tight md:text-4xl">Betslip result history</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                Green rows are won picks, red rows are lost picks, and yellow rows are pending until the final score is available.
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[620px] xl:grid-cols-4">
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <Trophy class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ filteredItems.length }}</p>
                                <p class="text-xs text-slate-500">visible picks</p>
                            </article>
                            <article class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                                <CircleCheck class="h-4 w-4 text-emerald-700" />
                                <p class="mt-3 text-2xl font-semibold text-emerald-900">{{ visibleSummary.won }}</p>
                                <p class="text-xs text-emerald-700">won</p>
                            </article>
                            <article class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                                <CircleX class="h-4 w-4 text-rose-700" />
                                <p class="mt-3 text-2xl font-semibold text-rose-900">{{ visibleSummary.lost }}</p>
                                <p class="text-xs text-rose-700">lost</p>
                            </article>
                            <article class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                                <CircleDashed class="h-4 w-4 text-amber-700" />
                                <p class="mt-3 text-2xl font-semibold text-amber-900">{{ visibleSummary.pending }}</p>
                                <p class="text-xs text-amber-700">pending</p>
                            </article>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 xl:grid-cols-[minmax(280px,1fr)_140px_130px_150px_150px_150px_auto]">
                        <label class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="search"
                                class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-10 pr-3 text-sm outline-none focus:border-slate-400"
                                placeholder="Search match, market, result..."
                            />
                        </label>

                        <select
                            v-model="selectedDate"
                            class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-slate-400"
                        >
                            <option value="all">All dates</option>
                            <option v-for="date in filters.dates" :key="date" :value="date">{{ formatDate(date) }}</option>
                        </select>

                        <select
                            v-model="selectedResult"
                            class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm capitalize outline-none focus:border-slate-400"
                        >
                            <option value="all">All results</option>
                            <option v-for="result in filters.results" :key="result" :value="result">{{ result }}</option>
                        </select>

                        <select
                            v-model="selectedMarket"
                            class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-slate-400"
                        >
                            <option value="all">All markets</option>
                            <option v-for="market in filters.markets" :key="market" :value="market">{{ market }}</option>
                        </select>

                        <select
                            v-model="selectedCountry"
                            class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-slate-400"
                        >
                            <option value="all">All countries</option>
                            <option v-for="country in filters.countries" :key="country" :value="country">{{ country }}</option>
                        </select>

                        <select
                            v-model="selectedLeague"
                            class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-slate-400"
                        >
                            <option value="all">All leagues</option>
                            <option v-for="league in filters.leagues" :key="league" :value="league">{{ league }}</option>
                        </select>

                        <button
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                            @click="resetFilters"
                        >
                            <ListFilter class="h-4 w-4" />
                            Reset
                        </button>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-[1600px] px-4 py-6 lg:px-8">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1240px] text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-center">Result</th>
                                    <th class="px-4 py-3 text-left">Match</th>
                                    <th class="px-4 py-3 text-center">Score</th>
                                    <th class="px-4 py-3 text-center">Tip</th>
                                    <th class="px-4 py-3 text-center">Odd</th>
                                    <th class="px-4 py-3 text-center">Model</th>
                                    <th class="px-4 py-3 text-left">Why</th>
                                    <th class="px-4 py-3 text-left">Open</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!filteredItems.length">
                                    <td colspan="8" class="px-4 py-16 text-center text-slate-500">No history rows match the current filters.</td>
                                </tr>
                                <tr
                                    v-for="item in filteredItems"
                                    :key="item.idKey"
                                    class="border-t align-top transition"
                                    :class="rowTone(item.result)"
                                >
                                    <td class="px-4 py-4 text-center">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-bold uppercase"
                                            :class="badgeTone(item.result)"
                                        >
                                            <component :is="resultIcon(item.result)" class="h-3.5 w-3.5" />
                                            {{ item.resultLabel }}
                                        </span>
                                        <p class="mt-2 text-xs text-slate-500">#{{ item.slipPosition }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-slate-950">{{ item.homeTeam }} vs {{ item.awayTeam }}</p>
                                        <p class="mt-1 text-xs text-slate-600">
                                            {{ item.country }} / {{ item.league }} / {{ formatDate(item.slipDate) }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">{{ item.matchStatus }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <p class="text-lg font-bold">{{ scoreLabel(item) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ item.matchTime?.slice(0, 5) ?? 'TBD' }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <p class="font-bold">{{ item.market ?? '-' }}</p>
                                        <p class="mt-1 text-xs text-slate-600">{{ item.advice ?? '-' }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <p class="text-lg font-bold">{{ formatNumber(item.selectedOdd, 2) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Total {{ formatNumber(item.totalSlipOdd, 2) }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="grid grid-cols-2 gap-2 text-center text-xs">
                                            <span class="rounded-md bg-white/70 p-2 font-semibold text-slate-700">
                                                Prob {{ formatPercent(item.probability) }}
                                            </span>
                                            <span class="rounded-md bg-white/70 p-2 font-semibold text-slate-700">
                                                Conf {{ formatPercent(item.confidence) }}
                                            </span>
                                            <span class="rounded-md bg-white/70 p-2 font-semibold text-slate-700">
                                                Edge {{ formatPercent(item.edge) }}
                                            </span>
                                            <span class="rounded-md bg-white/70 p-2 font-semibold text-slate-700">
                                                Score {{ formatNumber(item.dailyScore, 1) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="max-w-[380px] px-4 py-4 text-xs leading-5 text-slate-700">
                                        {{ item.rationale ?? 'No rationale stored.' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <a
                                            v-if="item.sourceMatchUrl"
                                            :href="item.sourceMatchUrl"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                        >
                                            <ExternalLink class="h-4 w-4" />
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </AppLayout>
</template>
