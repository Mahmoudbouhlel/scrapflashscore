<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { CalendarDays, ClipboardList, Database, ExternalLink, Filter, Sparkles, Trophy } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Candidate {
    matchId: string;
    matchUrl: string | null;
    country: string;
    league: string;
    matchDate: string | null;
    matchTime: string | null;
    status: string;
    homeTeam: string;
    awayTeam: string;
    homeRank: number | null;
    awayRank: number | null;
    homePoints: number | null;
    awayPoints: number | null;
    hasRanks: boolean;
    hasPrediction: boolean;
    modelName: string | null;
    modelMarket: string | null;
    modelConfidence: number | null;
    expectedGoals: number;
    expectedHomeGoals: number;
    expectedAwayGoals: number;
    statFireScore: number;
    homeStrength: number;
    awayStrength: number;
    homeFormScore: number;
    awayFormScore: number;
    hasForms: boolean;
    hasDominantRank: boolean;
    odds: { home: number | null; draw: number | null; away: number | null };
    market: string;
    marketOdd: number | null;
    score: number;
    why: string[];
}

const props = defineProps<{
    summary: {
        matches: number;
        candidates: number;
        withPredictions: number;
        dates: string[];
    };
    filters: {
        dates: string[];
        countries: string[];
        leagues: string[];
        markets: string[];
    };
    candidates: Candidate[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Betslip Generator', href: '/betslip-generator' }];

const selectedDate = ref('all');
const selectedCountry = ref('all');
const selectedLeague = ref('all');
const selectedMarket = ref('all');
const matchCount = ref(10);
const minScore = ref(55);
const minOdd = ref(1.0);
const uniqueMatchesOnly = ref(true);
const requirePrediction = ref(false);
const requireRanks = ref(false);
const requireForms = ref(false);
const requireDominantRank = ref(false);

const formatDate = (value: string | null) => {
    if (!value) return 'TBD';
    const [year, month, day] = value.split('-');
    return year && month && day ? `${day}/${month}/${year}` : value;
};

const todayValue = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Lagos' }).format(new Date());
const defaultDate = () => (props.filters.dates.includes(todayValue()) ? todayValue() : 'all');

onMounted(() => {
    selectedDate.value = defaultDate();
});

const formatNumber = (value: number | null | undefined, digits = 1) => {
    if (value === null || value === undefined || Number.isNaN(value)) return '-';
    return value.toLocaleString('en-US', { maximumFractionDigits: digits, minimumFractionDigits: digits });
};

const marketTone = (market: string) => {
    if (market.includes('Over')) return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    if (market.includes('GG')) return 'bg-amber-50 text-amber-800 ring-amber-200';
    if (market.includes('Away')) return 'bg-sky-50 text-sky-800 ring-sky-200';
    if (market.includes('Home')) return 'bg-rose-50 text-rose-800 ring-rose-200';
    return 'bg-slate-100 text-slate-700 ring-slate-200';
};

const filteredCandidates = computed(() => {
    return props.candidates
        .filter((candidate) => {
            if (selectedDate.value !== 'all' && candidate.matchDate !== selectedDate.value) return false;
            if (selectedCountry.value !== 'all' && candidate.country !== selectedCountry.value) return false;
            if (selectedLeague.value !== 'all' && candidate.league !== selectedLeague.value) return false;
            if (selectedMarket.value !== 'all' && candidate.market !== selectedMarket.value) return false;
            if (candidate.score < minScore.value) return false;
            if (candidate.marketOdd !== null && candidate.marketOdd < minOdd.value) return false;
            if (requirePrediction.value && !candidate.hasPrediction) return false;
            if (requireRanks.value && !candidate.hasRanks) return false;
            if (requireForms.value && !candidate.hasForms) return false;
            if (requireDominantRank.value && !candidate.hasDominantRank) return false;
            return true;
        })
        .sort((left, right) => right.score - left.score);
});

const generatedSlip = computed(() => {
    const rows: Candidate[] = [];
    const usedMatches = new Set<string>();

    for (const candidate of filteredCandidates.value) {
        if (uniqueMatchesOnly.value && usedMatches.has(candidate.matchId)) continue;
        rows.push(candidate);
        usedMatches.add(candidate.matchId);
        if (rows.length >= matchCount.value) break;
    }

    return rows;
});

const averageScore = computed(() => {
    if (!generatedSlip.value.length) return 0;
    return generatedSlip.value.reduce((total, candidate) => total + candidate.score, 0) / generatedSlip.value.length;
});
</script>

<template>
    <Head title="Betslip Generator" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-slate-50 text-slate-950">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-[1600px] px-4 py-6 lg:px-8">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700"
                            >
                                <Sparkles class="h-3.5 w-3.5" />
                                AI betslip generator
                            </div>
                            <h1 class="mt-4 text-3xl font-semibold tracking-tight md:text-4xl">Generate best combo by conditions</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                Select date, market, ranks, prediction requirement, odds, score, and match count. The generator builds the strongest
                                combo from your stored matches.
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[560px] xl:grid-cols-4">
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <Database class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ summary.matches }}</p>
                                <p class="text-xs text-slate-500">matches</p>
                            </article>
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <ClipboardList class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ summary.candidates }}</p>
                                <p class="text-xs text-slate-500">candidate bets</p>
                            </article>
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <Trophy class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ generatedSlip.length }}</p>
                                <p class="text-xs text-slate-500">in combo</p>
                            </article>
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <CalendarDays class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ formatNumber(averageScore, 1) }}</p>
                                <p class="text-xs text-slate-500">avg score</p>
                            </article>
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto grid max-w-[1600px] gap-6 px-4 py-6 lg:px-8 xl:grid-cols-[360px_1fr]">
                <aside class="rounded-lg border border-slate-200 bg-white p-5">
                    <div class="flex items-center gap-2">
                        <Filter class="h-5 w-5 text-slate-500" />
                        <h2 class="text-lg font-semibold">Conditions</h2>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <label class="grid gap-1 text-sm font-semibold text-slate-700">
                            Match number
                            <input
                                v-model.number="matchCount"
                                type="number"
                                min="1"
                                max="30"
                                class="h-10 rounded-md border border-slate-200 px-3 font-normal outline-none focus:border-slate-400"
                            />
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-slate-700">
                            Date matches
                            <select
                                v-model="selectedDate"
                                class="h-10 rounded-md border border-slate-200 bg-white px-3 font-normal outline-none focus:border-slate-400"
                            >
                                <option value="all">All dates</option>
                                <option v-for="date in filters.dates" :key="date" :value="date">{{ formatDate(date) }}</option>
                            </select>
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-slate-700">
                            Market
                            <select
                                v-model="selectedMarket"
                                class="h-10 rounded-md border border-slate-200 bg-white px-3 font-normal outline-none focus:border-slate-400"
                            >
                                <option value="all">All markets</option>
                                <option v-for="market in filters.markets" :key="market" :value="market">{{ market }}</option>
                            </select>
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-slate-700">
                            Country
                            <select
                                v-model="selectedCountry"
                                class="h-10 rounded-md border border-slate-200 bg-white px-3 font-normal outline-none focus:border-slate-400"
                            >
                                <option value="all">All countries</option>
                                <option v-for="country in filters.countries" :key="country" :value="country">{{ country }}</option>
                            </select>
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-slate-700">
                            League
                            <select
                                v-model="selectedLeague"
                                class="h-10 rounded-md border border-slate-200 bg-white px-3 font-normal outline-none focus:border-slate-400"
                            >
                                <option value="all">All leagues</option>
                                <option v-for="league in filters.leagues" :key="league" :value="league">{{ league }}</option>
                            </select>
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-slate-700">
                            Minimum score: {{ minScore }}
                            <input v-model.number="minScore" type="range" min="1" max="95" class="accent-slate-950" />
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-slate-700">
                            Minimum odd: {{ formatNumber(minOdd, 2) }}
                            <input v-model.number="minOdd" type="range" min="1" max="5" step="0.05" class="accent-slate-950" />
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input v-model="requirePrediction" type="checkbox" class="rounded border-slate-300 text-slate-950" />
                            Need prediction/model
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input v-model="requireRanks" type="checkbox" class="rounded border-slate-300 text-slate-950" />
                            Need rank details
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input v-model="requireForms" type="checkbox" class="rounded border-slate-300 text-slate-950" />
                            Need team forms
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input v-model="requireDominantRank" type="checkbox" class="rounded border-slate-300 text-slate-950" />
                            Dominant rank only
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input v-model="uniqueMatchesOnly" type="checkbox" class="rounded border-slate-300 text-slate-950" />
                            One bet per match
                        </label>
                    </div>
                </aside>

                <section class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex flex-col gap-3 border-b border-slate-200 p-5 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Generated combo</p>
                            <h2 class="mt-1 text-xl font-semibold">Best {{ generatedSlip.length }} selections</h2>
                        </div>
                        <p class="text-sm text-slate-500">{{ filteredCandidates.length }} candidates match current conditions</p>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <article v-for="(candidate, index) in generatedSlip" :key="`${candidate.matchId}-${candidate.market}`" class="p-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-md bg-slate-950 text-sm font-bold text-white">{{
                                            index + 1
                                        }}</span>
                                        <span
                                            class="rounded-md px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                            :class="marketTone(candidate.market)"
                                            >{{ candidate.market }}</span
                                        >
                                        <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                            >Score {{ formatNumber(candidate.score, 1) }}</span
                                        >
                                    </div>

                                    <a
                                        :href="candidate.matchUrl ?? '#'"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-3 inline-flex items-center gap-2 text-lg font-semibold text-slate-950 hover:text-rose-700"
                                    >
                                        {{ candidate.homeTeam }} vs {{ candidate.awayTeam }}
                                        <ExternalLink class="h-4 w-4" />
                                    </a>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ candidate.country }} / {{ candidate.league }} / {{ formatDate(candidate.matchDate) }}
                                        {{ candidate.matchTime?.slice(0, 5) ?? 'TBD' }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-2 text-sm lg:min-w-[300px]">
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Ranks</p>
                                        <p class="font-semibold">H {{ candidate.homeRank ?? '-' }} / A {{ candidate.awayRank ?? '-' }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Market odd</p>
                                        <p class="font-semibold">{{ formatNumber(candidate.marketOdd, 2) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Expected goals</p>
                                        <p class="font-semibold">{{ formatNumber(candidate.expectedGoals, 2) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Model</p>
                                        <p class="font-semibold">{{ candidate.hasPrediction ? 'Yes' : 'No' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 rounded-lg border border-slate-200 p-3">
                                <p class="text-sm font-semibold">Why this pick?</p>
                                <ul class="mt-2 space-y-1.5 text-sm leading-6 text-slate-600">
                                    <li v-for="reason in candidate.why" :key="reason" class="flex gap-2">
                                        <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" />
                                        <span>{{ reason }}</span>
                                    </li>
                                </ul>
                            </div>
                        </article>

                        <p v-if="!generatedSlip.length" class="p-10 text-center text-sm text-slate-500">
                            No combo found for the selected conditions. Lower minimum score/odd or remove rank/model requirements.
                        </p>
                    </div>
                </section>
            </main>
        </div>
    </AppLayout>
</template>
