<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { CalendarDays, ExternalLink, ListFilter, ReceiptText, Search, Sparkles, Target, TrendingUp } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface DailyBet {
    idKey: string;
    slipDate: string | null;
    slipPosition: number;
    matchId: string;
    sourceMatchUrl: string | null;
    modelName: string | null;
    generatedAt: string | null;
    country: string;
    league: string;
    homeTeam: string;
    awayTeam: string;
    market: string | null;
    advice: string | null;
    selectedOdd: number | null;
    minOdd: number | null;
    maxOdd: number | null;
    probability: number | null;
    impliedProbability: number | null;
    edge: number | null;
    confidence: number | null;
    dailyScore: number | null;
    totalSlipOdd: number | null;
    featureSummary: string | null;
    rationale: string | null;
}

interface DailySlip {
    date: string;
    count: number;
    totalOdd: number | null;
    avgConfidence: number | null;
    avgEdge: number | null;
    items: DailyBet[];
}

const props = defineProps<{
    summary: {
        totalRows: number;
        slipCount: number;
        latestDate: string | null;
        latestTotalOdd: number | null;
        avgConfidence: number | null;
        avgEdge: number | null;
    };
    filters: {
        dates: string[];
        markets: string[];
        advice: string[];
        countries: string[];
        leagues: string[];
    };
    slips: DailySlip[];
    items: DailyBet[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Daily Betslip', href: '/daily-betslip' }];

const search = ref('');
const selectedDate = ref('all');
const selectedMarket = ref('all');
const selectedAdvice = ref('all');
const selectedCountry = ref('all');
const selectedLeague = ref('all');
const minOdd = ref(1);
const minConfidence = ref(0);

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

const formatTimestamp = (value: string | null | undefined) => {
    if (!value) return 'No generation yet';
    return value.replace('T', ' ').replace('Z', '').slice(0, 16);
};

const marketTone = (market: string | null) => {
    const value = (market ?? '').toUpperCase();
    if (value.includes('HOME')) return 'bg-rose-50 text-rose-800 ring-rose-200';
    if (value.includes('AWAY')) return 'bg-sky-50 text-sky-800 ring-sky-200';
    if (value.includes('OVER')) return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    if (value.includes('GG') || value.includes('BTTS')) return 'bg-amber-50 text-amber-800 ring-amber-200';
    return 'bg-slate-100 text-slate-700 ring-slate-200';
};

const filteredItems = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.items
        .filter((item) => {
            if (selectedDate.value !== 'all' && item.slipDate !== selectedDate.value) return false;
            if (selectedMarket.value !== 'all' && item.market !== selectedMarket.value) return false;
            if (selectedAdvice.value !== 'all' && item.advice !== selectedAdvice.value) return false;
            if (selectedCountry.value !== 'all' && item.country !== selectedCountry.value) return false;
            if (selectedLeague.value !== 'all' && item.league !== selectedLeague.value) return false;
            if ((item.selectedOdd ?? 0) < minOdd.value) return false;
            if ((item.confidence ?? 0) < minConfidence.value) return false;
            if (!term) return true;

            return [
                item.matchId,
                item.country,
                item.league,
                item.homeTeam,
                item.awayTeam,
                item.market ?? '',
                item.advice ?? '',
                item.featureSummary ?? '',
                item.rationale ?? '',
            ]
                .join(' ')
                .toLowerCase()
                .includes(term);
        })
        .sort((left, right) => {
            if ((left.slipDate ?? '') !== (right.slipDate ?? '')) return (right.slipDate ?? '').localeCompare(left.slipDate ?? '');
            return left.slipPosition - right.slipPosition;
        });
});

const filteredSlips = computed(() =>
    props.slips
        .map((slip) => ({
            ...slip,
            items: filteredItems.value.filter((item) => item.slipDate === slip.date),
        }))
        .filter((slip) => slip.items.length > 0),
);

const activeSlip = computed(() => filteredSlips.value[0] ?? null);

const resetFilters = () => {
    search.value = '';
    selectedDate.value = defaultDate();
    selectedMarket.value = 'all';
    selectedAdvice.value = 'all';
    selectedCountry.value = 'all';
    selectedLeague.value = 'all';
    minOdd.value = 1;
    minConfidence.value = 0;
};
</script>

<template>
    <Head title="Daily Betslip" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-slate-50 text-slate-950">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-[1600px] px-4 py-6 lg:px-8">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700"
                            >
                                <ReceiptText class="h-3.5 w-3.5" />
                                prediction_daily_betslip
                            </div>
                            <h1 class="mt-4 text-3xl font-semibold tracking-tight md:text-4xl">Daily AI betslip</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                Daily generated combo picks with slip order, odds range, model probability, confidence, edge and why the tip was
                                selected.
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[680px] xl:grid-cols-4">
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <CalendarDays class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ summary.slipCount }}</p>
                                <p class="text-xs text-slate-500">daily slips</p>
                            </article>
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <Sparkles class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ summary.totalRows }}</p>
                                <p class="text-xs text-slate-500">picks stored</p>
                            </article>
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <Target class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ formatNumber(summary.latestTotalOdd, 2) }}</p>
                                <p class="text-xs text-slate-500">latest total odd</p>
                            </article>
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <TrendingUp class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ formatPercent(summary.avgConfidence) }}</p>
                                <p class="text-xs text-slate-500">avg confidence</p>
                            </article>
                        </div>
                    </div>

                    <div
                        class="mt-5 grid gap-3 md:grid-cols-[minmax(240px,1fr)_150px_150px_140px_150px_150px] xl:grid-cols-[minmax(280px,1fr)_140px_150px_130px_150px_150px_120px_150px_auto]"
                    >
                        <label class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="search"
                                class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-10 pr-3 text-sm outline-none focus:border-slate-400"
                                placeholder="Search team, league, market, rationale..."
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
                            v-model="selectedMarket"
                            class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-slate-400"
                        >
                            <option value="all">All markets</option>
                            <option v-for="market in filters.markets" :key="market" :value="market">{{ market }}</option>
                        </select>

                        <select
                            v-model="selectedAdvice"
                            class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-slate-400"
                        >
                            <option value="all">All advice</option>
                            <option v-for="advice in filters.advice" :key="advice" :value="advice">{{ advice }}</option>
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

                        <label class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Odd</span>
                            <input
                                v-model.number="minOdd"
                                type="number"
                                min="1"
                                step="0.05"
                                class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-11 pr-3 text-sm outline-none focus:border-slate-400"
                            />
                        </label>

                        <label class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Conf</span>
                            <input
                                v-model.number="minConfidence"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-12 pr-3 text-sm outline-none focus:border-slate-400"
                            />
                        </label>

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

            <main class="mx-auto grid max-w-[1600px] gap-5 px-4 py-6 lg:px-8">
                <section v-if="activeSlip" class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Selected slip</p>
                            <h2 class="mt-1 text-xl font-semibold">Ticket {{ formatDate(activeSlip.date) }}</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ activeSlip.items.length }} picks visible / generated {{ formatTimestamp(activeSlip.items[0]?.generatedAt) }}
                            </p>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-md bg-slate-100 px-4 py-2">
                                <p class="text-xs text-slate-500">Total odd</p>
                                <p class="font-semibold">{{ formatNumber(activeSlip.totalOdd, 2) }}</p>
                            </div>
                            <div class="rounded-md bg-slate-100 px-4 py-2">
                                <p class="text-xs text-slate-500">Confidence</p>
                                <p class="font-semibold">{{ formatPercent(activeSlip.avgConfidence) }}</p>
                            </div>
                            <div class="rounded-md bg-slate-100 px-4 py-2">
                                <p class="text-xs text-slate-500">Edge</p>
                                <p class="font-semibold">{{ formatPercent(activeSlip.avgEdge) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1180px] text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-center">#</th>
                                    <th class="px-4 py-3 text-left">Match</th>
                                    <th class="px-4 py-3 text-center">Tip</th>
                                    <th class="px-4 py-3 text-center">Odd</th>
                                    <th class="px-4 py-3 text-center">Model</th>
                                    <th class="px-4 py-3 text-left">Why choose</th>
                                    <th class="px-4 py-3 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in activeSlip.items" :key="item.idKey" class="border-t border-slate-100 align-top hover:bg-slate-50">
                                    <td class="px-4 py-4 text-center font-bold text-slate-500">{{ item.slipPosition }}</td>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-slate-950">{{ item.homeTeam }} vs {{ item.awayTeam }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ item.country }} / {{ item.league }} / {{ formatDate(item.slipDate) }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-400">{{ item.matchId }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="rounded-md px-2.5 py-1 text-xs font-bold ring-1 ring-inset" :class="marketTone(item.market)">
                                            {{ item.market ?? '-' }}
                                        </span>
                                        <p class="mt-2 font-semibold">{{ item.advice ?? '-' }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <p class="text-lg font-bold text-slate-950">{{ formatNumber(item.selectedOdd, 2) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ formatNumber(item.minOdd, 2) }} - {{ formatNumber(item.maxOdd, 2) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="grid grid-cols-2 gap-2 text-center text-xs">
                                            <span class="rounded-md bg-emerald-50 p-2 font-semibold text-emerald-700">
                                                Prob {{ formatPercent(item.probability) }}
                                            </span>
                                            <span class="rounded-md bg-slate-100 p-2 font-semibold text-slate-700">
                                                Conf {{ formatPercent(item.confidence) }}
                                            </span>
                                            <span class="rounded-md bg-sky-50 p-2 font-semibold text-sky-700">
                                                Edge {{ formatPercent(item.edge) }}
                                            </span>
                                            <span class="rounded-md bg-amber-50 p-2 font-semibold text-amber-700">
                                                Score {{ formatNumber(item.dailyScore, 1) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="max-w-[440px] px-4 py-4 text-xs leading-5 text-slate-600">
                                        <p>{{ item.rationale ?? 'No rationale stored.' }}</p>
                                        <p v-if="item.featureSummary" class="mt-2 text-slate-500">{{ item.featureSummary }}</p>
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

                <section v-else class="rounded-lg border border-slate-200 bg-white p-12 text-center text-slate-500">
                    No daily betslip rows match the current filters.
                </section>
            </main>
        </div>
    </AppLayout>
</template>
