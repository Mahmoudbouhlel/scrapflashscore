<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { BarChart3, Brain, CheckCircle2, ExternalLink, Goal, ListFilter, Search, Target } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface GoalPrediction {
    idKey: string;
    matchId: string;
    sourceMatchUrl: string | null;
    modelName: string | null;
    generatedAt: string | null;
    goalMarket: string | null;
    advice: string | null;
    probability: number | null;
    confidence: number | null;
    goalScore: number | null;
    homeXg: number | null;
    awayXg: number | null;
    totalXg: number | null;
    bttsYesProbability: number | null;
    over25Probability: number | null;
    recommended: boolean;
    featureSummary: string | null;
    rationale: string | null;
    featuresJson: string | null;
    country: string;
    league: string;
    matchDate: string | null;
    matchTime: string | null;
    status: string;
    fixtureLabel: string;
}

const props = defineProps<{
    summary: {
        total: number;
        recommended: number;
        avgProbability: number;
        avgTotalXg: number;
        latestGeneratedAt: string | null;
    };
    filters: {
        markets: string[];
        dates: string[];
        advice: string[];
        models: string[];
        generatedAts: string[];
    };
    items: GoalPrediction[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Goal Predictions', href: '/goal-predictions' }];

const search = ref('');
const selectedDate = ref('all');
const selectedMarket = ref('all');
const selectedAdvice = ref('all');
const selectedModel = ref('all');
const onlyRecommended = ref(false);
const minProbability = ref(0);
const minXg = ref(0);

const formatNumber = (value: number | null | undefined, digits = 1) => {
    if (value === null || value === undefined || Number.isNaN(value)) return '-';
    return value.toLocaleString('en-US', { maximumFractionDigits: digits, minimumFractionDigits: digits });
};

const formatPercent = (value: number | null | undefined) => (value === null || value === undefined ? '-' : `${formatNumber(value, 1)}%`);

const formatTimestamp = (value: string | null) => {
    if (!value) return 'No generation yet';
    return value.replace('T', ' ').replace('Z', '').slice(0, 16);
};

const marketTone = (market: string | null) => {
    if ((market ?? '').includes('OVER')) return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    if ((market ?? '').includes('BTTS')) return 'bg-amber-50 text-amber-800 ring-amber-200';
    return 'bg-slate-100 text-slate-700 ring-slate-200';
};

const adviceTone = (advice: string | null) => {
    if ((advice ?? '').toUpperCase() === 'YES') return 'bg-emerald-600 text-white';
    if ((advice ?? '').toUpperCase() === 'NO') return 'bg-rose-600 text-white';
    return 'bg-slate-700 text-white';
};

const filteredItems = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.items
        .filter((item) => {
            if (selectedMarket.value !== 'all' && item.goalMarket !== selectedMarket.value) return false;
            if (selectedDate.value !== 'all' && item.matchDate !== selectedDate.value) return false;
            if (selectedAdvice.value !== 'all' && item.advice !== selectedAdvice.value) return false;
            if (selectedModel.value !== 'all' && item.modelName !== selectedModel.value) return false;
            if (onlyRecommended.value && !item.recommended) return false;
            if ((item.probability ?? 0) < minProbability.value) return false;
            if ((item.totalXg ?? 0) < minXg.value) return false;
            if (!term) return true;

            return [item.fixtureLabel, item.matchId, item.goalMarket ?? '', item.advice ?? '', item.featureSummary ?? '', item.rationale ?? '']
                .join(' ')
                .toLowerCase()
                .includes(term);
        })
        .sort((left, right) => {
            if (left.recommended !== right.recommended) return Number(right.recommended) - Number(left.recommended);
            return (right.confidence ?? 0) - (left.confidence ?? 0);
        });
});

const topRecommended = computed(() => filteredItems.value.filter((item) => item.recommended).slice(0, 5));

const resetFilters = () => {
    search.value = '';
    selectedDate.value = 'all';
    selectedMarket.value = 'all';
    selectedAdvice.value = 'all';
    selectedModel.value = 'all';
    onlyRecommended.value = false;
    minProbability.value = 0;
    minXg.value = 0;
};
</script>

<template>
    <Head title="Goal Predictions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-slate-50 text-slate-950">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-[1600px] px-4 py-6 lg:px-8">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700"
                            >
                                <Goal class="h-3.5 w-3.5" />
                                prediction_goals
                            </div>
                            <h1 class="mt-4 text-3xl font-semibold tracking-tight md:text-4xl">Goal market predictions</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                Dedicated view for over goals and BTTS-style model output with xG, probabilities, confidence, recommendation,
                                features, and rationale.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3 xl:min-w-[640px] xl:grid-cols-4">
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <Brain class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ summary.total }}</p>
                                <p class="text-xs text-slate-500">goal rows</p>
                            </article>
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                                <p class="mt-3 text-2xl font-semibold">{{ summary.recommended }}</p>
                                <p class="text-xs text-slate-500">recommended</p>
                            </article>
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <Target class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ formatPercent(summary.avgProbability) }}</p>
                                <p class="text-xs text-slate-500">avg probability</p>
                            </article>
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <BarChart3 class="h-4 w-4 text-slate-500" />
                                <p class="mt-3 text-2xl font-semibold">{{ formatNumber(summary.avgTotalXg, 2) }}</p>
                                <p class="text-xs text-slate-500">avg total xG</p>
                            </article>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 xl:grid-cols-[minmax(260px,1fr)_160px_130px_180px_150px_150px_160px_auto]">
                        <label class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="search"
                                class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-10 pr-3 text-sm outline-none focus:border-slate-400"
                                placeholder="Search fixture, market, rationale..."
                            />
                        </label>

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
                            v-model="selectedModel"
                            class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-slate-400"
                        >
                            <option value="all">All models</option>
                            <option v-for="model in filters.models" :key="model" :value="model">{{ model }}</option>
                        </select>

                        <label
                            class="flex h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"
                        >
                            <input v-model="onlyRecommended" type="checkbox" class="rounded border-slate-300 text-slate-950" />
                            Recommended
                        </label>

                        <label class="grid gap-1 text-xs font-semibold text-slate-600">
                            Min prob {{ minProbability }}%
                            <input v-model.number="minProbability" type="range" min="0" max="100" class="accent-slate-950" />
                        </label>

                        <label class="grid gap-1 text-xs font-semibold text-slate-600">
                            Min xG {{ formatNumber(minXg, 1) }}
                            <input v-model.number="minXg" type="range" min="0" max="6" step="0.1" class="accent-slate-950" />
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

            <main class="mx-auto grid max-w-[1600px] gap-6 px-4 py-6 lg:px-8">
                <section class="rounded-lg border border-slate-200 bg-slate-950 text-white">
                    <div class="flex items-center justify-between gap-3 border-b border-white/10 p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">Top recommended</p>
                            <h2 class="mt-1 text-2xl font-semibold">Best goal market picks</h2>
                        </div>
                        <span class="rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold">{{ filteredItems.length }} visible</span>
                    </div>

                    <div v-if="topRecommended.length" class="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-5">
                        <article
                            v-for="item in topRecommended"
                            :key="`top-${item.idKey}`"
                            class="rounded-lg border border-white/10 bg-white/[0.06] p-4"
                        >
                            <span class="rounded-md px-2 py-1 text-xs font-semibold ring-1 ring-inset" :class="marketTone(item.goalMarket)">{{
                                item.goalMarket
                            }}</span>
                            <a
                                :href="item.sourceMatchUrl ?? '#'"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 block font-semibold leading-tight text-white hover:text-emerald-200"
                            >
                                {{ item.fixtureLabel }}
                            </a>
                            <p class="mt-3 text-xs text-slate-300">{{ item.rationale ?? 'No rationale stored.' }}</p>
                            <div class="mt-4 flex items-center justify-between gap-3">
                                <span class="rounded-md px-2.5 py-1.5 text-sm font-bold" :class="adviceTone(item.advice)">{{
                                    item.advice ?? '-'
                                }}</span>
                                <span class="text-sm font-semibold text-emerald-200">{{ formatPercent(item.probability) }}</span>
                            </div>
                        </article>
                    </div>

                    <p v-else class="p-8 text-center text-slate-300">No recommended goal predictions match the filters.</p>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">All goal predictions</p>
                        <h2 class="mt-1 text-xl font-semibold">Prediction goals table</h2>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <article v-for="item in filteredItems" :key="item.idKey" class="p-5">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-md px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                            :class="marketTone(item.goalMarket)"
                                            >{{ item.goalMarket }}</span
                                        >
                                        <span class="rounded-md px-2.5 py-1 text-xs font-semibold" :class="adviceTone(item.advice)">{{
                                            item.advice ?? '-'
                                        }}</span>
                                        <span
                                            v-if="item.recommended"
                                            class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800"
                                            >Recommended</span
                                        >
                                    </div>

                                    <a
                                        :href="item.sourceMatchUrl ?? '#'"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-3 inline-flex items-center gap-2 text-lg font-semibold text-slate-950 hover:text-rose-700"
                                    >
                                        {{ item.fixtureLabel }}
                                        <ExternalLink class="h-4 w-4" />
                                    </a>
                                    <p class="mt-1 text-xs text-slate-500">{{ item.modelName }} / {{ formatTimestamp(item.generatedAt) }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-2 text-sm md:grid-cols-5 xl:min-w-[620px]">
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Probability</p>
                                        <p class="font-semibold">{{ formatPercent(item.probability) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Confidence</p>
                                        <p class="font-semibold">{{ formatPercent(item.confidence) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Score</p>
                                        <p class="font-semibold">{{ formatNumber(item.goalScore, 2) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">xG</p>
                                        <p class="font-semibold">{{ formatNumber(item.homeXg, 2) }} - {{ formatNumber(item.awayXg, 2) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Total xG</p>
                                        <p class="font-semibold">{{ formatNumber(item.totalXg, 2) }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_280px]">
                                <div class="rounded-lg border border-slate-200 p-3">
                                    <p class="text-sm font-semibold text-slate-950">Rationale</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">
                                        {{ item.rationale ?? item.featureSummary ?? 'No rationale stored.' }}
                                    </p>
                                </div>
                                <div class="rounded-lg border border-slate-200 p-3">
                                    <p class="text-sm font-semibold text-slate-950">Extra probabilities</p>
                                    <p class="mt-2 text-sm text-slate-600">BTTS yes: {{ formatPercent(item.bttsYesProbability) }}</p>
                                    <p class="mt-1 text-sm text-slate-600">Over 2.5: {{ formatPercent(item.over25Probability) }}</p>
                                </div>
                            </div>
                        </article>

                        <p v-if="!filteredItems.length" class="p-10 text-center text-sm text-slate-500">No goal prediction rows match the filters.</p>
                    </div>
                </section>
            </main>
        </div>
    </AppLayout>
</template>
