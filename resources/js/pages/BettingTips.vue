<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { BarChart3, CalendarDays, ClipboardList, Database, Goal, ShieldCheck, Sparkles, Trophy } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Summary {
    matchesAnalysed: number;
    withPredictions: number;
    withOdds: number;
    latestScrape: string | null;
}

interface Tip {
    matchId: string;
    matchUrl: string | null;
    country: string;
    league: string;
    matchDate: string | null;
    matchTime: string | null;
    status: string;
    homeTeam: string;
    awayTeam: string;
    market: string;
    score: number;
    confidenceLabel: string;
    odds: {
        home: number | null;
        draw: number | null;
        away: number | null;
    };
    probabilities: {
        home: number | null;
        draw: number | null;
        away: number | null;
    };
    expectedGoals: number;
    expectedHomeGoals: number;
    expectedAwayGoals: number;
    statFireScore: number;
    homeStrength: number;
    awayStrength: number;
    homeRank: number | null;
    awayRank: number | null;
    hasForms: boolean;
    hasDominantRank: boolean;
    modelName: string | null;
    modelMarket: string | null;
    modelOutcome: string | null;
    modelConfidence: number | null;
    why: string[];
}

interface TipSection {
    title: string;
    market: string;
    items: Tip[];
}

const props = defineProps<{
    summary: Summary;
    sections: Record<string, TipSection>;
    combo: Tip[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Betting Tips',
        href: '/betting-tips',
    },
];

const selectedMarket = ref('all');
const selectedStatus = ref<'all' | 'scheduled' | 'live' | 'finished'>('all');
const selectedHour = ref('all');
const requireForms = ref(false);
const requireDominantRank = ref(false);

const formatDate = (value: string | null) => {
    if (!value) return 'TBD';
    const [year, month, day] = value.split('-');
    return year && month && day ? `${day}/${month}/${year}` : value;
};

const formatTimestamp = (value: string | null) => {
    if (!value) return 'No scrape yet';
    const [date, time] = value.split(' ');
    return `${formatDate(date ?? null)}${time ? ` ${time.slice(0, 5)}` : ''}`;
};

const formatNumber = (value: number | null | undefined, digits = 1) => {
    if (value === null || value === undefined || Number.isNaN(value)) return '-';
    return value.toLocaleString('en-US', { maximumFractionDigits: digits, minimumFractionDigits: digits });
};

const formatOdd = (value: number | null | undefined) => formatNumber(value, 2);
const formatPercent = (value: number | null | undefined) => (value === null || value === undefined ? '-' : `${formatNumber(value, 1)}%`);

const statusGroup = (status: string | null | undefined) => {
    const normalized = (status ?? '').toLowerCase();

    if (normalized.includes('live') || normalized.includes('1st') || normalized.includes('2nd') || normalized.includes('half')) return 'live';
    if (normalized.includes('ft') || normalized.includes('finished') || normalized.includes('after') || normalized.includes('pen')) return 'finished';

    return 'scheduled';
};

const kickoffHour = (time: string | null | undefined) => {
    const match = (time ?? '').match(/^(\d{1,2})/);
    return match ? match[1].padStart(2, '0') : null;
};

const matchesTipFilters = (tip: Tip) => {
    if (selectedStatus.value !== 'all' && statusGroup(tip.status) !== selectedStatus.value) return false;
    if (selectedHour.value !== 'all' && kickoffHour(tip.matchTime) !== selectedHour.value) return false;
    if (requireForms.value && !tip.hasForms) return false;
    if (requireDominantRank.value && !tip.hasDominantRank) return false;

    return true;
};

const allSections = computed(() =>
    Object.entries(props.sections).map(([key, section]) => ({
        key,
        ...section,
        items: section.items.filter(matchesTipFilters),
    })),
);
const sectionList = computed(() =>
    selectedMarket.value === 'all' ? allSections.value : allSections.value.filter((section) => section.key === selectedMarket.value),
);
const selectedSection = computed(() => allSections.value.find((section) => section.key === selectedMarket.value));
const filteredCombo = computed(() => props.combo.filter(matchesTipFilters));

const kickoffHours = computed(() =>
    Array.from(
        new Set(
            Object.values(props.sections)
                .flatMap((section) => section.items)
                .map((tip) => kickoffHour(tip.matchTime))
                .filter((hour): hour is string => Boolean(hour)),
        ),
    ).sort((left, right) => Number(left) - Number(right)),
);

const summaryCards = computed(() => [
    { label: 'Matches analysed', value: props.summary.matchesAnalysed, note: 'from matches table', icon: Database },
    { label: 'With predictions', value: props.summary.withPredictions, note: 'model signal available', icon: Sparkles },
    { label: 'With 1X2 odds', value: props.summary.withOdds, note: 'value filter ready', icon: BarChart3 },
    { label: 'Latest scrape', value: formatTimestamp(props.summary.latestScrape), note: 'database freshness', icon: CalendarDays },
]);

const marketTone = (market: string) => {
    if (market.includes('Over')) return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    if (market.includes('GG') || market.includes('Both')) return 'bg-amber-50 text-amber-800 ring-amber-200';
    if (market.includes('Away')) return 'bg-sky-50 text-sky-800 ring-sky-200';
    if (market.includes('Home')) return 'bg-rose-50 text-rose-800 ring-rose-200';
    return 'bg-slate-100 text-slate-700 ring-slate-200';
};

const sectionTheme = (key: string) => {
    const themes: Record<string, { chip: string; border: string; icon: string; soft: string; accent: string }> = {
        over25: {
            chip: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            border: 'border-emerald-200',
            icon: 'bg-emerald-100 text-emerald-700',
            soft: 'bg-emerald-50',
            accent: 'bg-emerald-500',
        },
        gg: {
            chip: 'bg-amber-50 text-amber-800 ring-amber-200',
            border: 'border-amber-200',
            icon: 'bg-amber-100 text-amber-700',
            soft: 'bg-amber-50',
            accent: 'bg-amber-500',
        },
        homeWin: {
            chip: 'bg-rose-50 text-rose-800 ring-rose-200',
            border: 'border-rose-200',
            icon: 'bg-rose-100 text-rose-700',
            soft: 'bg-rose-50',
            accent: 'bg-rose-500',
        },
        awayWin: {
            chip: 'bg-sky-50 text-sky-800 ring-sky-200',
            border: 'border-sky-200',
            icon: 'bg-sky-100 text-sky-700',
            soft: 'bg-sky-50',
            accent: 'bg-sky-500',
        },
        over15: {
            chip: 'bg-teal-50 text-teal-800 ring-teal-200',
            border: 'border-teal-200',
            icon: 'bg-teal-100 text-teal-700',
            soft: 'bg-teal-50',
            accent: 'bg-teal-500',
        },
        teamOver05: {
            chip: 'bg-violet-50 text-violet-800 ring-violet-200',
            border: 'border-violet-200',
            icon: 'bg-violet-100 text-violet-700',
            soft: 'bg-violet-50',
            accent: 'bg-violet-500',
        },
    };

    return (
        themes[key] ?? {
            chip: 'bg-slate-100 text-slate-700 ring-slate-200',
            border: 'border-slate-200',
            icon: 'bg-slate-100 text-slate-700',
            soft: 'bg-slate-50',
            accent: 'bg-slate-500',
        }
    );
};

const scoreTone = (score: number) => {
    if (score >= 78) return 'bg-emerald-600 text-white';
    if (score >= 65) return 'bg-slate-950 text-white';
    if (score >= 52) return 'bg-amber-500 text-white';
    return 'bg-slate-200 text-slate-700';
};
</script>

<template>
    <Head title="Betting Tips" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-slate-50 text-slate-950">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-[1600px] px-4 py-5 lg:px-8">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700"
                            >
                                <Trophy class="h-3.5 w-3.5" />
                                best bets generator
                            </div>
                            <h1 class="mt-3 text-2xl font-semibold tracking-tight md:text-3xl">Top betting tips by market</h1>
                            <p class="mt-1 max-w-2xl text-sm text-slate-500">Choose one market to read cleanly, or show all sections.</p>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2 xl:min-w-[560px] xl:grid-cols-4">
                            <article v-for="card in summaryCards" :key="card.label" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ card.label }}</p>
                                    <component :is="card.icon" class="h-4 w-4 text-slate-500" />
                                </div>
                                <p class="mt-2 truncate text-xl font-semibold">{{ card.value }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ card.note }}</p>
                            </article>
                        </div>
                    </div>

                    <div class="mt-5 flex gap-2 overflow-x-auto pb-1">
                        <button
                            class="shrink-0 rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-inset transition"
                            :class="
                                selectedMarket === 'all'
                                    ? 'bg-slate-950 text-white ring-slate-950'
                                    : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50'
                            "
                            @click="selectedMarket = 'all'"
                        >
                            All
                        </button>
                        <button
                            v-for="section in allSections"
                            :key="section.key"
                            class="shrink-0 rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-inset transition"
                            :class="
                                selectedMarket === section.key
                                    ? `${sectionTheme(section.key).chip} ${sectionTheme(section.key).border}`
                                    : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50'
                            "
                            @click="selectedMarket = section.key"
                        >
                            {{ section.market }}
                        </button>
                    </div>

                    <div
                        class="mt-3 flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="flex gap-2 overflow-x-auto">
                            <button
                                v-for="status in [
                                    { key: 'all', label: 'All status' },
                                    { key: 'scheduled', label: 'Scheduled' },
                                    { key: 'live', label: 'Live' },
                                    { key: 'finished', label: 'Finished' },
                                ]"
                                :key="status.key"
                                class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold ring-1 ring-inset transition"
                                :class="
                                    selectedStatus === status.key
                                        ? 'bg-slate-950 text-white ring-slate-950'
                                        : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-100'
                                "
                                @click="selectedStatus = status.key as 'all' | 'scheduled' | 'live' | 'finished'"
                            >
                                {{ status.label }}
                            </button>
                        </div>

                        <label class="flex shrink-0 items-center gap-2 text-sm font-semibold text-slate-600">
                            Kickoff HH
                            <select
                                v-model="selectedHour"
                                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-slate-400"
                            >
                                <option value="all">All hours</option>
                                <option v-for="hour in kickoffHours" :key="hour" :value="hour">{{ hour }}:00</option>
                            </select>
                        </label>

                        <label
                            class="flex h-9 shrink-0 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"
                        >
                            <input v-model="requireForms" type="checkbox" class="rounded border-slate-300 text-slate-950" />
                            Has forms
                        </label>

                        <label
                            class="flex h-9 shrink-0 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"
                        >
                            <input v-model="requireDominantRank" type="checkbox" class="rounded border-slate-300 text-slate-950" />
                            Dominant rank
                        </label>
                    </div>
                </div>
            </header>

            <main class="mx-auto grid max-w-[1600px] gap-6 px-4 py-6 lg:px-8">
                <section v-if="selectedMarket === 'all'" class="rounded-lg border border-slate-200 bg-slate-950 text-white">
                    <div class="flex flex-col gap-4 border-b border-white/10 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">10-match combo</p>
                            <h2 class="mt-1 text-2xl font-semibold">Best combo from all markets</h2>
                        </div>
                        <div class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold text-white">
                            <ClipboardList class="h-4 w-4" />
                            {{ filteredCombo.length }} selections
                        </div>
                    </div>

                    <div v-if="filteredCombo.length" class="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-5">
                        <article
                            v-for="(tip, index) in filteredCombo"
                            :key="`combo-${tip.matchId}-${tip.market}`"
                            class="rounded-lg border border-white/10 bg-white/[0.06] p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-emerald-400 text-sm font-bold text-slate-950"
                                    >{{ index + 1 }}</span
                                >
                                <span class="rounded-md px-2 py-1 text-xs font-semibold ring-1 ring-inset" :class="marketTone(tip.market)">{{
                                    tip.market
                                }}</span>
                            </div>
                            <a
                                :href="tip.matchUrl ?? '#'"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 block font-semibold leading-tight text-white hover:text-emerald-200"
                            >
                                {{ tip.homeTeam }} vs {{ tip.awayTeam }}
                            </a>
                            <p class="mt-2 text-xs text-slate-300">{{ tip.country }} / {{ tip.league }}</p>
                            <div class="mt-4 flex items-center justify-between gap-3">
                                <span class="rounded-md px-2.5 py-1.5 text-sm font-bold" :class="scoreTone(tip.score)">{{
                                    formatNumber(tip.score, 1)
                                }}</span>
                                <span class="text-xs text-slate-300">{{ formatDate(tip.matchDate) }} {{ tip.matchTime?.slice(0, 5) ?? 'TBD' }}</span>
                            </div>
                            <p class="mt-2 text-xs text-slate-400">Status: {{ tip.status }}</p>
                        </article>
                    </div>

                    <p v-else class="p-8 text-center text-slate-300">No combo matches the selected status/time filters.</p>
                </section>

                <section v-else-if="selectedSection" class="rounded-lg border border-slate-200 bg-white p-5">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Reading mode</p>
                            <h2 class="mt-1 text-2xl font-semibold">{{ selectedSection.title }}</h2>
                        </div>
                        <span
                            class="w-fit rounded-lg px-3 py-2 text-sm font-semibold ring-1 ring-inset"
                            :class="sectionTheme(selectedSection.key).chip"
                        >
                            {{ selectedSection.market }}
                        </span>
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-5">
                        <article
                            v-for="(tip, index) in selectedSection.items"
                            :key="`focus-${tip.matchId}-${tip.market}`"
                            class="rounded-lg border p-4"
                            :class="[sectionTheme(selectedSection.key).border, sectionTheme(selectedSection.key).soft]"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-md text-sm font-bold text-white"
                                    :class="sectionTheme(selectedSection.key).accent"
                                >
                                    {{ index + 1 }}
                                </span>
                                <span class="rounded-md px-2 py-1 text-xs font-semibold" :class="scoreTone(tip.score)">{{
                                    formatNumber(tip.score, 1)
                                }}</span>
                            </div>
                            <a
                                :href="tip.matchUrl ?? '#'"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 block font-semibold leading-tight text-slate-950 hover:text-rose-700"
                            >
                                {{ tip.homeTeam }} vs {{ tip.awayTeam }}
                            </a>
                            <p class="mt-2 text-xs text-slate-500">
                                {{ tip.country }} / {{ formatDate(tip.matchDate) }} {{ tip.matchTime?.slice(0, 5) ?? 'TBD' }}
                            </p>
                            <p class="mt-1 text-xs font-semibold text-slate-600">{{ tip.status }}</p>
                            <p class="mt-3 text-xs text-slate-600">
                                EG {{ formatNumber(tip.expectedGoals, 2) }} / Fire {{ formatNumber(tip.statFireScore, 2) }}
                            </p>
                        </article>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-2">
                    <article
                        v-for="section in sectionList"
                        :key="section.key"
                        class="rounded-lg border bg-white"
                        :class="sectionTheme(section.key).border"
                    >
                        <div class="flex items-center justify-between gap-3 border-b p-5" :class="sectionTheme(section.key).border">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ section.market }}</p>
                                <h2 class="mt-1 text-xl font-semibold">{{ section.title }}</h2>
                            </div>
                            <div class="rounded-lg p-3" :class="sectionTheme(section.key).icon">
                                <Goal v-if="section.market.includes('Over')" class="h-5 w-5" />
                                <ShieldCheck v-else class="h-5 w-5" />
                            </div>
                        </div>

                        <div class="divide-y divide-slate-100">
                            <article v-for="(tip, index) in section.items" :key="`${section.key}-${tip.matchId}-${tip.market}`" class="p-5">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="flex h-8 w-8 items-center justify-center rounded-md text-sm font-bold text-white"
                                                :class="sectionTheme(section.key).accent"
                                                >{{ index + 1 }}</span
                                            >
                                            <span
                                                class="rounded-md px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                                :class="sectionTheme(section.key).chip"
                                                >{{ tip.market }}</span
                                            >
                                            <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{
                                                tip.confidenceLabel
                                            }}</span>
                                        </div>

                                        <a
                                            :href="tip.matchUrl ?? '#'"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-3 block text-lg font-semibold text-slate-950 hover:text-rose-700"
                                        >
                                            {{ tip.homeTeam }} vs {{ tip.awayTeam }}
                                        </a>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ tip.country }} / {{ tip.league }} / {{ formatDate(tip.matchDate) }}
                                            {{ tip.matchTime?.slice(0, 5) ?? 'TBD' }}
                                        </p>
                                        <p class="mt-1 text-xs font-semibold text-slate-600">Status: {{ tip.status }}</p>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2 text-center text-xs lg:min-w-[230px]">
                                        <div class="rounded-lg bg-emerald-50 p-2 text-emerald-800">
                                            <p class="font-semibold">1</p>
                                            <p>{{ formatOdd(tip.odds.home) }}</p>
                                            <p>{{ formatPercent(tip.probabilities.home) }}</p>
                                        </div>
                                        <div class="rounded-lg bg-amber-50 p-2 text-amber-800">
                                            <p class="font-semibold">X</p>
                                            <p>{{ formatOdd(tip.odds.draw) }}</p>
                                            <p>{{ formatPercent(tip.probabilities.draw) }}</p>
                                        </div>
                                        <div class="rounded-lg bg-sky-50 p-2 text-sky-800">
                                            <p class="font-semibold">2</p>
                                            <p>{{ formatOdd(tip.odds.away) }}</p>
                                            <p>{{ formatPercent(tip.probabilities.away) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 lg:grid-cols-[150px_1fr]">
                                    <div class="rounded-lg border p-3" :class="[sectionTheme(section.key).border, sectionTheme(section.key).soft]">
                                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tip score</p>
                                        <p class="mt-2 text-3xl font-semibold">{{ formatNumber(tip.score, 1) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            EG {{ formatNumber(tip.expectedGoals, 2) }} / Fire {{ formatNumber(tip.statFireScore, 2) }}
                                        </p>
                                    </div>

                                    <div class="rounded-lg border border-slate-200 p-3">
                                        <p class="text-sm font-semibold text-slate-950">Why choose this tip bet?</p>
                                        <ul class="mt-2 space-y-1.5 text-sm leading-6 text-slate-600">
                                            <li v-for="reason in tip.why" :key="reason" class="flex gap-2">
                                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full" :class="sectionTheme(section.key).accent" />
                                                <span>{{ reason }}</span>
                                            </li>
                                        </ul>
                                        <p v-if="tip.modelName" class="mt-3 text-xs text-slate-500">
                                            Model: {{ tip.modelName }} / {{ tip.modelMarket ?? '-' }} / confidence
                                            {{ formatPercent(tip.modelConfidence) }}
                                        </p>
                                    </div>
                                </div>
                            </article>

                            <p v-if="!section.items.length" class="p-8 text-center text-sm text-slate-500">No matches found for this market yet.</p>
                        </div>
                    </article>
                </section>
            </main>
        </div>
    </AppLayout>
</template>
