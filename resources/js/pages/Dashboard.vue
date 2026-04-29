<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Activity, CalendarDays, Database, ExternalLink, Eye, ListFilter, Search, Sparkles, Table2, Timer, Trophy, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface Summary {
    totalMatches: number;
    totalPredictions: number;
    trackedTeams: number;
    activeLeagues: number;
    avgConfidence: number;
    avgStatFireScore: number;
    latestScrape: string | null;
    topMarket: string;
    scrapeErrors: number;
}

interface FiltersData {
    sports: string[];
    countries: string[];
    leagues: string[];
    seasons: string[];
    statuses: string[];
    dates: string[];
    predictionModels: string[];
    predictionMarkets: string[];
    predictionOutcomes: string[];
    predictionGeneratedAts: string[];
}

interface LeagueCard {
    country: string;
    league: string;
    matches: number;
    predictions: number;
    avgConfidence: number;
    topFixture: string;
}

interface RankingRow {
    rank: number;
    team: string;
    played: number;
    wins: number;
    draws: number;
    losses: number;
    goalsFor: number;
    goalsAgainst: number;
    goalDifference: number;
    points: number;
    winRate: number;
    form: string[];
}

interface ProbabilitySet {
    home: number | null;
    draw: number | null;
    away: number | null;
}

interface MatchHistoryRow {
    date: string | null;
    country: string | null;
    league: string | null;
    homeTeam: string | null;
    awayTeam: string | null;
    score: string | null;
    resultText: string | null;
    sectionType?: string | null;
    sectionTeam?: string | null;
    resultFlag?: string | null;
    odds?: {
        home: number | null;
        draw: number | null;
        away: number | null;
    };
}

interface TeamInfo {
    name: string;
    rank: number | null;
    points: number | null;
    played: number | null;
    wins: number | null;
    draws: number | null;
    losses: number | null;
    goalsFor: number | null;
    goalsAgainst: number | null;
    goalDifference: number | null;
    form: string[];
    strengthScore: number | null;
}

interface MatchCard {
    matchId: string;
    sport: string;
    country: string;
    league: string;
    season: string | null;
    matchDate: string | null;
    matchTime: string | null;
    matchDatetimeText: string | null;
    matchDatetimeIso: string | null;
    status: string;
    venue: string | null;
    referee: string | null;
    scoreHome: number | string | null;
    scoreAway: number | string | null;
    scrapedAt: string | null;
    sourcePageUrl: string | null;
    matchUrl: string | null;
    canonicalUrl: string | null;
    pageTitle: string | null;
    hasPrediction: boolean;
    predictedOutcome: string | null;
    recommendedMarket: string | null;
    confidence: number | null;
    valueEdge: number | null;
    statFireScore: number | null;
    drawTensionScore: number | null;
    featureSummary: string | null;
    rationale: string | null;
    probabilities: ProbabilitySet;
    homeTeam: TeamInfo;
    awayTeam: TeamInfo;
    odds: {
        home: number | null;
        draw: number | null;
        away: number | null;
    };
    h2hSnippets: MatchHistoryRow[];
    recentResults: MatchHistoryRow[];
}

interface PredictionRow {
    matchId: string;
    sourceMatchUrl: string | null;
    modelName: string | null;
    generatedAt: string | null;
    predictedOutcome: string | null;
    recommendedMarket: string | null;
    confidence: number | null;
    homeWinProbability: number | null;
    drawProbability: number | null;
    awayWinProbability: number | null;
    valueEdge: number | null;
    statFireScore: number | null;
    featureSummary: string | null;
    rationale: string | null;
    country: string;
    league: string;
    homeTeam: string;
    awayTeam: string;
    homeRank: number | null;
    awayRank: number | null;
    homePlayed: number | null;
    awayPlayed: number | null;
    homeForm: string[];
    awayForm: string[];
    matchDate: string | null;
    matchTime: string | null;
    matchUrl: string | null;
}

interface MarketSlice {
    market: string | null;
    count: number;
    share: number;
}

interface TableStat {
    name: string;
    rows: number;
    columns: number;
}

const props = defineProps<{
    summary: Summary;
    filters: FiltersData;
    leagueCards: LeagueCard[];
    rankings: RankingRow[];
    featuredMatches: MatchCard[];
    marketDistribution: MarketSlice[];
    allMatches: MatchCard[];
    allPredictions: PredictionRow[];
    tableStats: TableStat[];
    scrapeErrors: Record<string, string | number | null>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const search = ref('');
const selectedCountry = ref('all');
const selectedLeague = ref('all');
const selectedDate = ref('all');
const selectedStatus = ref('all');
const requireForms = ref(false);
const requireDominantRank = ref(false);
const selectedMotivation = ref<'all' | 'title' | 'goodPlace' | 'survival'>('all');
const minPlayed = ref<number | null>(null);
const timeFrom = ref('');
const timeTo = ref('');
const activeView = ref<'fixtures' | 'predictions' | 'schema'>('fixtures');
const sortBy = ref<'confidence' | 'kickoff' | 'league'>('confidence');
const currentPage = ref(1);
const pageSize = ref(25);
const selectedMatch = ref<MatchCard | null>(null);
const selectedPrediction = ref<PredictionRow | null>(null);

const safeNumber = (value: number | string | null | undefined): number | null => {
    if (value === null || value === undefined || value === '') return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

const formatNumber = (value: number | string | null | undefined, digits = 0) => {
    const normalized = safeNumber(value);
    return normalized === null ? '-' : normalized.toLocaleString('en-US', { maximumFractionDigits: digits, minimumFractionDigits: digits });
};

const formatPercent = (value: number | null | undefined, digits = 0) => {
    const normalized = safeNumber(value);
    return normalized === null ? '-' : `${formatNumber(normalized, digits)}%`;
};

const formatDate = (value: string | null | undefined) => {
    if (!value) return 'TBD';
    const [year, month, day] = value.split('-');
    return year && month && day ? `${day}/${month}/${year}` : value;
};

const todayValue = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Lagos' }).format(new Date());
const defaultDate = () => (props.filters.dates.includes(todayValue()) ? todayValue() : 'all');

onMounted(() => {
    selectedDate.value = defaultDate();
});

const formatTimestamp = (value: string | null | undefined) => {
    if (!value) return 'No scrape yet';
    const [date, time] = value.split(' ');
    return `${formatDate(date)}${time ? ` ${time.slice(0, 5)}` : ''}`;
};

const scoreLabel = (match: MatchCard) => {
    if (match.scoreHome === null || match.scoreAway === null) return '-';
    return `${match.scoreHome} - ${match.scoreAway}`;
};

const matchExternalUrl = (match: MatchCard) => match.matchUrl ?? match.canonicalUrl ?? match.sourcePageUrl ?? '#';
const predictionExternalUrl = (prediction: PredictionRow) => prediction.matchUrl ?? prediction.sourceMatchUrl ?? '#';

const hasDominantRank = (homeRank: number | string | null | undefined, awayRank: number | string | null | undefined) => {
    const home = safeNumber(homeRank);
    const away = safeNumber(awayRank);

    return home !== null && away !== null && Math.abs(home - away) >= 3;
};

const matchHasForms = (match: MatchCard) => match.homeTeam.form.length > 0 && match.awayTeam.form.length > 0;
const matchHasDominantRank = (match: MatchCard) => hasDominantRank(match.homeTeam.rank, match.awayTeam.rank);
const predictionHasForms = (prediction: PredictionRow) => prediction.homeForm.length > 0 && prediction.awayForm.length > 0;
const predictionHasDominantRank = (prediction: PredictionRow) => hasDominantRank(prediction.homeRank, prediction.awayRank);

const meetsMinPlayed = (homePlayed: number | string | null | undefined, awayPlayed: number | string | null | undefined) => {
    const minimum = safeNumber(minPlayed.value);
    if (minimum === null || minimum <= 0) return true;

    const home = safeNumber(homePlayed);
    const away = safeNumber(awayPlayed);

    return home !== null && away !== null && home >= minimum && away >= minimum;
};

const matchHasMinPlayed = (match: MatchCard) => meetsMinPlayed(match.homeTeam.played, match.awayTeam.played);
const predictionHasMinPlayed = (prediction: PredictionRow) => meetsMinPlayed(prediction.homePlayed, prediction.awayPlayed);

const timeToMinutes = (value: string | null | undefined) => {
    if (!value) return null;

    const [hours, minutes] = value.slice(0, 5).split(':').map(Number);
    if (!Number.isFinite(hours) || !Number.isFinite(minutes)) return null;

    return hours * 60 + minutes;
};

const matchTimeInRange = (value: string | null | undefined) => {
    if (!timeFrom.value && !timeTo.value) return true;

    const matchMinutes = timeToMinutes(value);
    if (matchMinutes === null) return false;

    const fromMinutes = timeToMinutes(timeFrom.value);
    const toMinutes = timeToMinutes(timeTo.value);

    if (fromMinutes !== null && toMinutes !== null && fromMinutes > toMinutes) {
        return matchMinutes >= fromMinutes || matchMinutes <= toMinutes;
    }

    if (fromMinutes !== null && matchMinutes < fromMinutes) return false;
    if (toMinutes !== null && matchMinutes > toMinutes) return false;

    return true;
};

const leagueKey = (country: string | null | undefined, league: string | null | undefined) => `${country ?? 'Unknown'}|${league ?? 'Unknown'}`;

const maxRankByLeague = computed(() => {
    const ranks: Record<string, number> = {};

    props.allMatches.forEach((match) => {
        const key = leagueKey(match.country, match.league);
        const homeRank = safeNumber(match.homeTeam.rank);
        const awayRank = safeNumber(match.awayTeam.rank);
        const current = ranks[key] ?? 0;

        ranks[key] = Math.max(current, homeRank ?? 0, awayRank ?? 0);
    });

    return ranks;
});

const rankMotivationForTeam = (rank: number | null, maxRank: number | null, motivation: 'title' | 'goodPlace' | 'survival') => {
    if (rank === null) return false;
    if (motivation === 'title') return rank <= 3;
    if (motivation === 'goodPlace') return rank >= 4 && rank <= 8;
    if (motivation === 'survival') return maxRank !== null && maxRank >= 8 && rank >= maxRank - 3;

    return false;
};

const matchHasMotivation = (match: MatchCard) => {
    if (selectedMotivation.value === 'all') return true;

    const maxRank = maxRankByLeague.value[leagueKey(match.country, match.league)] ?? null;

    return (
        rankMotivationForTeam(safeNumber(match.homeTeam.rank), maxRank, selectedMotivation.value) ||
        rankMotivationForTeam(safeNumber(match.awayTeam.rank), maxRank, selectedMotivation.value)
    );
};

const predictionHasMotivation = (prediction: PredictionRow) => {
    if (selectedMotivation.value === 'all') return true;

    const maxRank = maxRankByLeague.value[leagueKey(prediction.country, prediction.league)] ?? null;

    return (
        rankMotivationForTeam(safeNumber(prediction.homeRank), maxRank, selectedMotivation.value) ||
        rankMotivationForTeam(safeNumber(prediction.awayRank), maxRank, selectedMotivation.value)
    );
};

const motivationLabel = (match: MatchCard) => {
    const maxRank = maxRankByLeague.value[leagueKey(match.country, match.league)] ?? null;
    const homeRank = safeNumber(match.homeTeam.rank);
    const awayRank = safeNumber(match.awayTeam.rank);

    if (rankMotivationForTeam(homeRank, maxRank, 'title') || rankMotivationForTeam(awayRank, maxRank, 'title')) return 'Needs win for #1/top race';
    if (rankMotivationForTeam(homeRank, maxRank, 'survival') || rankMotivationForTeam(awayRank, maxRank, 'survival'))
        return 'Needs win out of bottom';
    if (rankMotivationForTeam(homeRank, maxRank, 'goodPlace') || rankMotivationForTeam(awayRank, maxRank, 'goodPlace'))
        return 'Needs win for good place';

    return 'Neutral table pressure';
};

const deriveProbabilities = (match: MatchCard): ProbabilitySet => {
    if (match.probabilities.home !== null || match.probabilities.draw !== null || match.probabilities.away !== null) {
        return match.probabilities;
    }

    const home = safeNumber(match.odds.home);
    const draw = safeNumber(match.odds.draw);
    const away = safeNumber(match.odds.away);

    if (home === null || draw === null || away === null) {
        return { home: null, draw: null, away: null };
    }

    const total = 1 / home + 1 / draw + 1 / away;

    return {
        home: Number(((1 / home / total) * 100).toFixed(1)),
        draw: Number(((1 / draw / total) * 100).toFixed(1)),
        away: Number(((1 / away / total) * 100).toFixed(1)),
    };
};

const bestOutcome = (match: MatchCard) => {
    const explicit = (match.predictedOutcome ?? '').toUpperCase();
    if (['1', 'X', '2'].includes(explicit)) return explicit;

    const probabilities = deriveProbabilities(match);
    const rows = [
        ['1', probabilities.home],
        ['X', probabilities.draw],
        ['2', probabilities.away],
    ] as const;

    return [...rows].sort((left, right) => (safeNumber(right[1]) ?? 0) - (safeNumber(left[1]) ?? 0))[0][0];
};

const confidenceFor = (match: MatchCard) => {
    const confidence = safeNumber(match.confidence);
    if (confidence !== null) return confidence;
    const probabilities = deriveProbabilities(match);
    return Math.max(safeNumber(probabilities.home) ?? 0, safeNumber(probabilities.draw) ?? 0, safeNumber(probabilities.away) ?? 0);
};

const filteredMatches = computed(() => {
    const term = search.value.trim().toLowerCase();

    const matches = props.allMatches.filter((match) => {
        if (selectedCountry.value !== 'all' && match.country !== selectedCountry.value) return false;
        if (selectedLeague.value !== 'all' && match.league !== selectedLeague.value) return false;
        if (selectedDate.value !== 'all' && match.matchDate !== selectedDate.value) return false;
        if (selectedStatus.value !== 'all' && match.status !== selectedStatus.value) return false;
        if (requireForms.value && !matchHasForms(match)) return false;
        if (requireDominantRank.value && !matchHasDominantRank(match)) return false;
        if (!matchHasMotivation(match)) return false;
        if (!matchHasMinPlayed(match)) return false;
        if (!matchTimeInRange(match.matchTime)) return false;

        if (!term) return true;

        return [
            match.matchId,
            match.country,
            match.league,
            match.status,
            match.homeTeam.name,
            match.awayTeam.name,
            match.venue ?? '',
            match.referee ?? '',
            match.recommendedMarket ?? '',
            match.featureSummary ?? '',
            match.rationale ?? '',
        ]
            .join(' ')
            .toLowerCase()
            .includes(term);
    });

    return [...matches].sort((left, right) => {
        if (sortBy.value === 'kickoff')
            return `${left.matchDate ?? ''} ${left.matchTime ?? ''}`.localeCompare(`${right.matchDate ?? ''} ${right.matchTime ?? ''}`);
        if (sortBy.value === 'league') return `${left.country} ${left.league}`.localeCompare(`${right.country} ${right.league}`);
        return confidenceFor(right) - confidenceFor(left);
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredMatches.value.length / pageSize.value)));
const pageStart = computed(() => (currentPage.value - 1) * pageSize.value);
const pageEnd = computed(() => Math.min(pageStart.value + pageSize.value, filteredMatches.value.length));
const visibleMatches = computed(() => filteredMatches.value.slice(pageStart.value, pageEnd.value));

const filteredPredictions = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.allPredictions
        .filter((prediction) => {
            if (selectedCountry.value !== 'all' && prediction.country !== selectedCountry.value) return false;
            if (selectedLeague.value !== 'all' && prediction.league !== selectedLeague.value) return false;
            if (selectedDate.value !== 'all' && prediction.matchDate !== selectedDate.value) return false;
            if (requireForms.value && !predictionHasForms(prediction)) return false;
            if (requireDominantRank.value && !predictionHasDominantRank(prediction)) return false;
            if (!predictionHasMotivation(prediction)) return false;
            if (!predictionHasMinPlayed(prediction)) return false;
            if (!matchTimeInRange(prediction.matchTime)) return false;
            if (!term) return true;

            return [
                prediction.matchId,
                prediction.modelName ?? '',
                prediction.predictedOutcome ?? '',
                prediction.recommendedMarket ?? '',
                prediction.homeTeam,
                prediction.awayTeam,
                prediction.featureSummary ?? '',
                prediction.rationale ?? '',
            ]
                .join(' ')
                .toLowerCase()
                .includes(term);
        })
        .slice(0, 80);
});

const visibleStats = computed(() => ({
    fixtures: filteredMatches.value.length,
    predicted: filteredMatches.value.filter((match) => match.hasPrediction).length,
    odds: filteredMatches.value.filter((match) => match.odds.home !== null || match.odds.draw !== null || match.odds.away !== null).length,
    errors: props.summary.scrapeErrors,
}));

const summaryCards = computed(() => [
    { label: 'Matches', value: formatNumber(props.summary.totalMatches), note: `${visibleStats.value.fixtures} visibles`, icon: CalendarDays },
    {
        label: 'Predictions',
        value: formatNumber(props.summary.totalPredictions),
        note: `${formatPercent(props.summary.avgConfidence, 1)} avg confidence`,
        icon: Sparkles,
    },
    { label: 'Teams', value: formatNumber(props.summary.trackedTeams), note: `${props.summary.activeLeagues} leagues`, icon: Trophy },
    {
        label: 'Data health',
        value: formatNumber(props.summary.scrapeErrors),
        note: `Latest ${formatTimestamp(props.summary.latestScrape)}`,
        icon: Database,
    },
]);

const resetFilters = () => {
    search.value = '';
    selectedCountry.value = 'all';
    selectedLeague.value = 'all';
    selectedDate.value = defaultDate();
    selectedStatus.value = 'all';
    requireForms.value = false;
    requireDominantRank.value = false;
    selectedMotivation.value = 'all';
    minPlayed.value = null;
    timeFrom.value = '';
    timeTo.value = '';
    sortBy.value = 'confidence';
    currentPage.value = 1;
};

watch(
    [
        search,
        selectedCountry,
        selectedLeague,
        selectedDate,
        selectedStatus,
        requireForms,
        requireDominantRank,
        selectedMotivation,
        minPlayed,
        timeFrom,
        timeTo,
        sortBy,
        pageSize,
    ],
    () => {
        currentPage.value = 1;
    },
);

watch(totalPages, () => {
    if (currentPage.value > totalPages.value) currentPage.value = totalPages.value;
});

const formTone = (value: string | null | undefined) => {
    if (value === 'W') return 'bg-emerald-100 text-emerald-700 ring-emerald-200';
    if (value === 'D') return 'bg-amber-100 text-amber-700 ring-amber-200';
    return 'bg-rose-100 text-rose-700 ring-rose-200';
};

const statusTone = (value: string) => {
    const normalized = value.toLowerCase();
    if (normalized.includes('live')) return 'bg-rose-100 text-rose-700 ring-rose-200';
    if (normalized.includes('ft') || normalized.includes('finish')) return 'bg-emerald-100 text-emerald-700 ring-emerald-200';
    return 'bg-slate-100 text-slate-700 ring-slate-200';
};

const outcomeTone = (value: string | null | undefined) => {
    if (value === '1') return 'bg-emerald-600 text-white';
    if (value === '2') return 'bg-sky-600 text-white';
    return 'bg-amber-500 text-white';
};
</script>

<template>
    <Head title="Flashscore Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="">
            <div class="">
                <div class="">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700"
                            >
                                <Activity class="h-3.5 w-3.5" />
                                flashscore_scraper
                            </div>
                            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 md:text-4xl">
                                Modern betting intelligence dashboard
                            </h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                Full database view for fixtures, predictions, odds, rankings, H2H history, recent team form, and scraper health.
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[680px] xl:grid-cols-4">
                            <article v-for="card in summaryCards" :key="card.label" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ card.label }}</p>
                                    <component :is="card.icon" class="h-4 w-4 text-slate-500" />
                                </div>
                                <p class="mt-3 text-2xl font-semibold text-slate-950">{{ card.value }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ card.note }}</p>
                            </article>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 md:grid-cols-[minmax(240px,1fr)_180px_180px_160px_160px_auto] xl:grid-cols-[minmax(240px,1fr)_135px_135px_120px_120px_110px_130px_160px_90px_105px_105px_auto]"
                    >
                        <label class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="search"
                                class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-10 pr-3 text-sm outline-none ring-0 transition focus:border-slate-400"
                                placeholder="Search team, league, market, model..."
                            />
                        </label>

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

                        <select
                            v-model="selectedDate"
                            class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-slate-400"
                        >
                            <option value="all">All dates</option>
                            <option v-for="date in filters.dates" :key="date" :value="date">{{ formatDate(date) }}</option>
                        </select>

                        <select
                            v-model="selectedStatus"
                            class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-slate-400"
                        >
                            <option value="all">All statuses</option>
                            <option v-for="status in filters.statuses" :key="status" :value="status">{{ status }}</option>
                        </select>

                        <label
                            class="flex h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"
                        >
                            <input v-model="requireForms" type="checkbox" class="rounded border-slate-300 text-slate-950" />
                            Has forms
                        </label>

                        <label
                            class="flex h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"
                        >
                            <input v-model="requireDominantRank" type="checkbox" class="rounded border-slate-300 text-slate-950" />
                            Dominant rank
                        </label>

                        <select
                            v-model="selectedMotivation"
                            class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-slate-400"
                        >
                            <option value="all">All motivation</option>
                            <option value="title">Need win for #1</option>
                            <option value="goodPlace">Need win good place</option>
                            <option value="survival">Need win out bottom</option>
                        </select>

                        <label class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">MP</span>
                            <input
                                v-model.number="minPlayed"
                                type="number"
                                min="0"
                                step="1"
                                class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-10 pr-3 text-sm outline-none focus:border-slate-400"
                                placeholder=">="
                            />
                        </label>

                        <label class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">From</span>
                            <input
                                v-model="timeFrom"
                                type="time"
                                class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-12 pr-2 text-sm outline-none focus:border-slate-400"
                            />
                        </label>

                        <label class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">To</span>
                            <input
                                v-model="timeTo"
                                type="time"
                                class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-2 text-sm outline-none focus:border-slate-400"
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
            </div>

            <main class="">
                <section class="">
                    <div class="">
                        <div class="flex flex-col gap-4 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Command board</p>
                                <h2 class="mt-1 text-xl font-semibold text-slate-950">Fixtures, odds and predictions</h2>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="view in ['fixtures', 'predictions', 'schema']"
                                    :key="view"
                                    class="rounded-md px-3 py-2 text-sm font-semibold capitalize transition"
                                    :class="activeView === view ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                    @click="activeView = view as 'fixtures' | 'predictions' | 'schema'"
                                >
                                    {{ view }}
                                </button>
                                <select
                                    v-if="activeView === 'fixtures'"
                                    v-model="sortBy"
                                    class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm outline-none"
                                >
                                    <option value="confidence">Confidence</option>
                                    <option value="kickoff">Kickoff</option>
                                    <option value="league">League</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="activeView === 'fixtures'" class="overflow-x-auto">
                            <table class="w-full min-w-[1280px] border-collapse text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Match</th>
                                        <th class="px-4 py-3 text-left">Context</th>
                                        <th class="px-4 py-3 text-center">Prob 1X2</th>
                                        <th class="px-4 py-3 text-center">Pick</th>
                                        <th class="px-4 py-3 text-center">Odds</th>
                                        <th class="px-4 py-3 text-left">Model details</th>
                                        <th class="px-4 py-3 text-left">More</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!visibleMatches.length">
                                        <td colspan="7" class="px-4 py-16 text-center text-slate-500">No fixture rows match the current filters.</td>
                                    </tr>
                                    <tr
                                        v-for="match in visibleMatches"
                                        :key="match.matchId"
                                        class="border-t border-slate-100 align-top hover:bg-slate-50/70"
                                    >
                                        <td class="w-[340px] px-4 py-4">
                                            <div class="flex items-start gap-3">
                                                <div
                                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-950 text-xs font-bold text-white"
                                                >
                                                    {{ match.country.slice(0, 2).toUpperCase() }}
                                                </div>
                                                <div class="min-w-0">
                                                    <a
                                                        :href="matchExternalUrl(match)"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="font-semibold text-rose-700 hover:text-rose-800"
                                                    >
                                                        {{ match.homeTeam.name }}
                                                    </a>
                                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                                                            Rank {{ formatNumber(match.homeTeam.rank) }}
                                                        </span>
                                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                                                            Pts {{ formatNumber(match.homeTeam.points) }}
                                                        </span>
                                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                                                            MP {{ formatNumber(match.homeTeam.played) }}
                                                        </span>
                                                        <span
                                                            v-for="(form, index) in match.homeTeam.form.slice(-5)"
                                                            :key="`${match.matchId}-row-home-form-${index}`"
                                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold ring-1 ring-inset"
                                                            :class="formTone(form)"
                                                        >
                                                            {{ form }}
                                                        </span>
                                                        <span v-if="!match.homeTeam.form.length" class="text-[11px] text-slate-400">No form</span>
                                                    </div>
                                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">vs</p>
                                                    <p class="font-semibold text-slate-950">{{ match.awayTeam.name }}</p>
                                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                                                            Rank {{ formatNumber(match.awayTeam.rank) }}
                                                        </span>
                                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                                                            Pts {{ formatNumber(match.awayTeam.points) }}
                                                        </span>
                                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                                                            MP {{ formatNumber(match.awayTeam.played) }}
                                                        </span>
                                                        <span
                                                            v-for="(form, index) in match.awayTeam.form.slice(-5)"
                                                            :key="`${match.matchId}-row-away-form-${index}`"
                                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold ring-1 ring-inset"
                                                            :class="formTone(form)"
                                                        >
                                                            {{ form }}
                                                        </span>
                                                        <span v-if="!match.awayTeam.form.length" class="text-[11px] text-slate-400">No form</span>
                                                    </div>
                                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600"
                                                            >Score {{ scoreLabel(match) }}</span
                                                        >
                                                        <span
                                                            class="rounded-md px-2 py-1 text-xs ring-1 ring-inset"
                                                            :class="statusTone(match.status)"
                                                            >{{ match.status }}</span
                                                        >
                                                        <span
                                                            class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-100"
                                                        >
                                                            {{ motivationLabel(match) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="w-[220px] px-4 py-4 text-slate-600">
                                            <p class="font-semibold text-slate-950">{{ match.country }}</p>
                                            <p class="mt-1">{{ match.league }}</p>
                                            <p class="mt-2 flex items-center gap-1.5 text-xs">
                                                <Timer class="h-3.5 w-3.5" /> {{ formatDate(match.matchDate) }}
                                                {{ match.matchTime?.slice(0, 5) ?? 'TBD' }}
                                            </p>
                                            <p v-if="match.venue" class="mt-1 text-xs">Venue: {{ match.venue }}</p>
                                            <p v-if="match.referee" class="mt-1 text-xs">Referee: {{ match.referee }}</p>
                                        </td>

                                        <td class="px-4 py-4">
                                            <div class="grid grid-cols-3 gap-2 text-center">
                                                <div class="rounded-lg bg-emerald-50 p-2">
                                                    <p class="text-xs font-semibold text-emerald-700">1</p>
                                                    <p class="mt-1 font-semibold">{{ formatPercent(deriveProbabilities(match).home) }}</p>
                                                </div>
                                                <div class="rounded-lg bg-amber-50 p-2">
                                                    <p class="text-xs font-semibold text-amber-700">X</p>
                                                    <p class="mt-1 font-semibold">{{ formatPercent(deriveProbabilities(match).draw) }}</p>
                                                </div>
                                                <div class="rounded-lg bg-sky-50 p-2">
                                                    <p class="text-xs font-semibold text-sky-700">2</p>
                                                    <p class="mt-1 font-semibold">{{ formatPercent(deriveProbabilities(match).away) }}</p>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4 text-center">
                                            <span
                                                class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-lg font-bold"
                                                :class="outcomeTone(bestOutcome(match))"
                                            >
                                                {{ bestOutcome(match) }}
                                            </span>
                                            <p class="mt-2 text-xs text-slate-500">{{ match.recommendedMarket ?? '1X2' }}</p>
                                        </td>

                                        <td class="px-4 py-4">
                                            <div class="grid grid-cols-3 gap-1.5 text-center text-xs">
                                                <div class="rounded-md border border-slate-200 p-2">
                                                    <p class="text-slate-400">1</p>
                                                    <p class="font-semibold">{{ formatNumber(match.odds.home, 2) }}</p>
                                                </div>
                                                <div class="rounded-md border border-slate-200 p-2">
                                                    <p class="text-slate-400">X</p>
                                                    <p class="font-semibold">{{ formatNumber(match.odds.draw, 2) }}</p>
                                                </div>
                                                <div class="rounded-md border border-slate-200 p-2">
                                                    <p class="text-slate-400">2</p>
                                                    <p class="font-semibold">{{ formatNumber(match.odds.away, 2) }}</p>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="w-[260px] px-4 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"
                                                    >Conf {{ formatPercent(confidenceFor(match), 1) }}</span
                                                >
                                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"
                                                    >Value {{ formatPercent(match.valueEdge, 1) }}</span
                                                >
                                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"
                                                    >Fire {{ formatNumber(match.statFireScore, 1) }}</span
                                                >
                                            </div>
                                            <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-600">
                                                {{ match.featureSummary ?? match.rationale ?? 'No model narrative yet.' }}
                                            </p>
                                        </td>

                                        <td class="w-[300px] px-4 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <a
                                                    :href="matchExternalUrl(match)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                                >
                                                    <ExternalLink class="h-4 w-4" />
                                                    Open
                                                </a>
                                                <button
                                                    class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                                                    @click="selectedMatch = match"
                                                >
                                                    <Eye class="h-4 w-4" />
                                                    Details
                                                </button>
                                            </div>
                                            <p class="mt-3 text-xs leading-5 text-slate-500">
                                                Open the Flashscore source, or view the full match analysis inside the dashboard.
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div
                                class="flex min-w-[1280px] flex-col gap-3 border-t border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <p class="text-sm text-slate-500">
                                    Showing {{ filteredMatches.length ? pageStart + 1 : 0 }}-{{ pageEnd }} of {{ filteredMatches.length }} matches
                                </p>

                                <div class="flex flex-wrap items-center gap-2">
                                    <select
                                        v-model.number="pageSize"
                                        class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm outline-none"
                                    >
                                        <option :value="10">10 / page</option>
                                        <option :value="25">25 / page</option>
                                        <option :value="50">50 / page</option>
                                        <option :value="100">100 / page</option>
                                    </select>
                                    <button
                                        class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-45"
                                        :disabled="currentPage === 1"
                                        @click="currentPage = Math.max(1, currentPage - 1)"
                                    >
                                        Previous
                                    </button>
                                    <span class="rounded-md bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">
                                        Page {{ currentPage }} / {{ totalPages }}
                                    </span>
                                    <button
                                        class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-45"
                                        :disabled="currentPage === totalPages"
                                        @click="currentPage = Math.min(totalPages, currentPage + 1)"
                                    >
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="activeView === 'predictions'" class="overflow-x-auto">
                            <table class="w-full min-w-[1100px] text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Fixture</th>
                                        <th class="px-4 py-3 text-left">Model</th>
                                        <th class="px-4 py-3 text-center">Outcome</th>
                                        <th class="px-4 py-3 text-center">Probabilities</th>
                                        <th class="px-4 py-3 text-left">Rationale</th>
                                        <th class="px-4 py-3 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!filteredPredictions.length">
                                        <td colspan="6" class="px-4 py-16 text-center text-slate-500">
                                            No prediction rows match the current filters.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="prediction in filteredPredictions"
                                        :key="`${prediction.matchId}-${prediction.generatedAt}`"
                                        class="border-t border-slate-100 align-top hover:bg-slate-50/70"
                                    >
                                        <td class="px-4 py-4">
                                            <p class="font-semibold text-slate-950">{{ prediction.homeTeam }} vs {{ prediction.awayTeam }}</p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ prediction.country }} / {{ prediction.league }} / {{ formatDate(prediction.matchDate) }}
                                                {{ prediction.matchTime?.slice(0, 5) ?? 'TBD' }}
                                            </p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="font-semibold text-slate-950">{{ prediction.modelName ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ formatTimestamp(prediction.generatedAt) }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <span
                                                class="inline-flex h-10 min-w-10 items-center justify-center rounded-lg px-3 font-bold"
                                                :class="outcomeTone(prediction.predictedOutcome)"
                                            >
                                                {{ prediction.predictedOutcome ?? '-' }}
                                            </span>
                                            <p class="mt-2 text-xs text-slate-500">{{ prediction.recommendedMarket ?? '-' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="grid grid-cols-3 gap-2 text-center">
                                                <span class="rounded-md bg-emerald-50 p-2 text-xs font-semibold text-emerald-700"
                                                    >1 {{ formatPercent(prediction.homeWinProbability) }}</span
                                                >
                                                <span class="rounded-md bg-amber-50 p-2 text-xs font-semibold text-amber-700"
                                                    >X {{ formatPercent(prediction.drawProbability) }}</span
                                                >
                                                <span class="rounded-md bg-sky-50 p-2 text-xs font-semibold text-sky-700"
                                                    >2 {{ formatPercent(prediction.awayWinProbability) }}</span
                                                >
                                            </div>
                                            <p class="mt-2 text-center text-xs text-slate-500">
                                                Conf {{ formatPercent(prediction.confidence, 1) }} / Edge {{ formatPercent(prediction.valueEdge, 1) }}
                                            </p>
                                        </td>
                                        <td class="max-w-[420px] px-4 py-4 text-xs leading-5 text-slate-600">
                                            {{ prediction.rationale ?? prediction.featureSummary ?? 'No rationale stored.' }}
                                        </td>
                                        <td class="w-[220px] px-4 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <a
                                                    :href="predictionExternalUrl(prediction)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                                >
                                                    <ExternalLink class="h-4 w-4" />
                                                    Open
                                                </a>
                                                <button
                                                    class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                                                    @click="selectedPrediction = prediction"
                                                >
                                                    <Eye class="h-4 w-4" />
                                                    Details
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="grid gap-4 p-4 lg:grid-cols-2 xl:grid-cols-3">
                            <article v-for="table in tableStats" :key="table.name" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-950">{{ table.name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ table.columns }} columns</p>
                                    </div>
                                    <div class="rounded-lg bg-white p-3 text-slate-500">
                                        <Table2 class="h-5 w-5" />
                                    </div>
                                </div>
                                <p class="mt-5 text-3xl font-semibold text-slate-950">{{ formatNumber(table.rows) }}</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">rows</p>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="grid gap-4 xl:grid-cols-[1fr_420px]">
                    <article class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Standings</p>
                                <h2 class="mt-1 text-xl font-semibold text-slate-950">Best ranked teams</h2>
                            </div>
                            <Trophy class="h-5 w-5 text-amber-500" />
                        </div>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full min-w-[760px] text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Team</th>
                                        <th class="px-3 py-2 text-center">P</th>
                                        <th class="px-3 py-2 text-center">W</th>
                                        <th class="px-3 py-2 text-center">D</th>
                                        <th class="px-3 py-2 text-center">L</th>
                                        <th class="px-3 py-2 text-center">GD</th>
                                        <th class="px-3 py-2 text-center">Pts</th>
                                        <th class="px-3 py-2 text-left">Form</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="team in rankings.slice(0, 12)" :key="team.team" class="border-t border-slate-100">
                                        <td class="px-3 py-3">
                                            <p class="font-semibold text-slate-950">{{ team.rank }}. {{ team.team }}</p>
                                            <p class="text-xs text-slate-500">{{ formatPercent(team.winRate, 1) }} win rate</p>
                                        </td>
                                        <td class="px-3 py-3 text-center">{{ team.played }}</td>
                                        <td class="px-3 py-3 text-center">{{ team.wins }}</td>
                                        <td class="px-3 py-3 text-center">{{ team.draws }}</td>
                                        <td class="px-3 py-3 text-center">{{ team.losses }}</td>
                                        <td class="px-3 py-3 text-center">{{ team.goalDifference }}</td>
                                        <td class="px-3 py-3 text-center font-semibold">{{ team.points }}</td>
                                        <td class="px-3 py-3">
                                            <div class="flex gap-1">
                                                <span
                                                    v-for="(form, index) in team.form"
                                                    :key="`${team.team}-${index}`"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold ring-1 ring-inset"
                                                    :class="formTone(form)"
                                                >
                                                    {{ form }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!rankings.length">
                                        <td colspan="8" class="px-3 py-10 text-center text-slate-500">No standings rows yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>
            </main>

            <div
                v-if="selectedMatch"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"
                @click.self="selectedMatch = null"
            >
                <section class="max-h-[92vh] w-full max-w-6xl overflow-y-auto rounded-lg bg-white shadow-2xl">
                    <header class="sticky top-0 z-10 border-b border-slate-200 bg-white p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                    {{ selectedMatch.country }} / {{ selectedMatch.league }}
                                </p>
                                <h2 class="mt-2 text-2xl font-semibold text-slate-950">
                                    {{ selectedMatch.homeTeam.name }} vs {{ selectedMatch.awayTeam.name }}
                                </h2>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                                    <span class="rounded-md px-2 py-1 ring-1 ring-inset" :class="statusTone(selectedMatch.status)">
                                        {{ selectedMatch.status }}
                                    </span>
                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-slate-700">
                                        {{ formatDate(selectedMatch.matchDate) }} {{ selectedMatch.matchTime?.slice(0, 5) ?? 'TBD' }}
                                    </span>
                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-slate-700">Score {{ scoreLabel(selectedMatch) }}</span>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <a
                                    :href="matchExternalUrl(selectedMatch)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                    Open source
                                </a>
                                <button
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-slate-950 text-white transition hover:bg-slate-800"
                                    @click="selectedMatch = null"
                                >
                                    <X class="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    </header>

                    <div class="grid gap-4 p-5 lg:grid-cols-[1fr_340px]">
                        <div class="space-y-4">
                            <article class="rounded-lg border border-slate-200 p-4">
                                <div class="grid gap-3 md:grid-cols-3">
                                    <div class="rounded-lg bg-emerald-50 p-4 text-center text-emerald-800">
                                        <p class="text-xs font-semibold uppercase tracking-[0.14em]">Home</p>
                                        <p class="mt-2 text-2xl font-semibold">{{ formatPercent(deriveProbabilities(selectedMatch).home, 1) }}</p>
                                        <p class="mt-1 text-xs">Odd {{ formatNumber(selectedMatch.odds.home, 2) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-amber-50 p-4 text-center text-amber-800">
                                        <p class="text-xs font-semibold uppercase tracking-[0.14em]">Draw</p>
                                        <p class="mt-2 text-2xl font-semibold">{{ formatPercent(deriveProbabilities(selectedMatch).draw, 1) }}</p>
                                        <p class="mt-1 text-xs">Odd {{ formatNumber(selectedMatch.odds.draw, 2) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-sky-50 p-4 text-center text-sky-800">
                                        <p class="text-xs font-semibold uppercase tracking-[0.14em]">Away</p>
                                        <p class="mt-2 text-2xl font-semibold">{{ formatPercent(deriveProbabilities(selectedMatch).away, 1) }}</p>
                                        <p class="mt-1 text-xs">Odd {{ formatNumber(selectedMatch.odds.away, 2) }}</p>
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-lg border border-slate-200 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Model analysis</p>
                                        <h3 class="mt-1 text-lg font-semibold text-slate-950">{{ selectedMatch.recommendedMarket ?? '1X2' }}</h3>
                                    </div>
                                    <span
                                        class="inline-flex h-12 w-12 items-center justify-center rounded-lg text-xl font-bold"
                                        :class="outcomeTone(bestOutcome(selectedMatch))"
                                    >
                                        {{ bestOutcome(selectedMatch) }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-2 sm:grid-cols-4">
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Confidence</p>
                                        <p class="mt-1 font-semibold">{{ formatPercent(confidenceFor(selectedMatch), 1) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Value edge</p>
                                        <p class="mt-1 font-semibold">{{ formatPercent(selectedMatch.valueEdge, 1) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Stat fire</p>
                                        <p class="mt-1 font-semibold">{{ formatNumber(selectedMatch.statFireScore, 1) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs text-slate-500">Draw tension</p>
                                        <p class="mt-1 font-semibold">{{ formatPercent(selectedMatch.drawTensionScore, 1) }}</p>
                                    </div>
                                </div>

                                <p class="mt-4 text-sm leading-6 text-slate-600">
                                    {{
                                        selectedMatch.rationale ??
                                        selectedMatch.featureSummary ??
                                        'No prediction narrative is stored for this match yet.'
                                    }}
                                </p>
                            </article>

                            <article class="rounded-lg border border-slate-200 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">H2H and recent form</p>
                                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                    <div>
                                        <h3 class="font-semibold text-slate-950">H2H</h3>
                                        <div class="mt-2 space-y-2">
                                            <p v-if="!selectedMatch.h2hSnippets.length" class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">
                                                No H2H rows stored.
                                            </p>
                                            <p
                                                v-for="(h2h, index) in selectedMatch.h2hSnippets"
                                                :key="`${selectedMatch.matchId}-modal-h2h-${index}`"
                                                class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600"
                                            >
                                                {{ formatDate(h2h.date) }} - {{ h2h.homeTeam }} {{ h2h.score ?? '-' }} {{ h2h.awayTeam }}
                                            </p>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-slate-950">Recent results</h3>
                                        <div class="mt-2 space-y-2">
                                            <p v-if="!selectedMatch.recentResults.length" class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">
                                                No recent result rows stored.
                                            </p>
                                            <p
                                                v-for="(result, index) in selectedMatch.recentResults"
                                                :key="`${selectedMatch.matchId}-modal-recent-${index}`"
                                                class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600"
                                            >
                                                {{ result.sectionTeam }}: {{ result.homeTeam }} {{ result.score ?? '-' }} {{ result.awayTeam }}
                                                {{ result.resultFlag ? `(${result.resultFlag})` : '' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <aside class="space-y-4">
                            <article class="rounded-lg border border-slate-200 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Match info</p>
                                <div class="mt-3 space-y-2 text-sm text-slate-600">
                                    <p><span class="font-semibold text-slate-950">Sport:</span> {{ selectedMatch.sport }}</p>
                                    <p><span class="font-semibold text-slate-950">Season:</span> {{ selectedMatch.season ?? '-' }}</p>
                                    <p><span class="font-semibold text-slate-950">Venue:</span> {{ selectedMatch.venue ?? '-' }}</p>
                                    <p><span class="font-semibold text-slate-950">Referee:</span> {{ selectedMatch.referee ?? '-' }}</p>
                                    <p><span class="font-semibold text-slate-950">Scraped:</span> {{ formatTimestamp(selectedMatch.scrapedAt) }}</p>
                                </div>
                            </article>

                            <article class="rounded-lg border border-slate-200 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Teams</p>
                                <div class="mt-3 grid gap-3">
                                    <div
                                        v-for="team in [selectedMatch.homeTeam, selectedMatch.awayTeam]"
                                        :key="team.name"
                                        class="rounded-lg bg-slate-50 p-3"
                                    >
                                        <p class="font-semibold text-slate-950">{{ team.name }}</p>
                                        <p class="mt-1 text-sm text-slate-600">
                                            Rank {{ formatNumber(team.rank) }} / Pts {{ formatNumber(team.points) }} / GD
                                            {{ formatNumber(team.goalDifference) }}
                                        </p>
                                        <p class="mt-1 text-sm text-slate-600">
                                            W/D/L {{ formatNumber(team.wins) }}/{{ formatNumber(team.draws) }}/{{ formatNumber(team.losses) }}
                                        </p>
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            <span
                                                v-for="(form, index) in team.form"
                                                :key="`${team.name}-modal-form-${index}`"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold ring-1 ring-inset"
                                                :class="formTone(form)"
                                            >
                                                {{ form }}
                                            </span>
                                            <span v-if="!team.form.length" class="text-xs text-slate-500">No form</span>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </aside>
                    </div>
                </section>
            </div>

            <div
                v-if="selectedPrediction"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"
                @click.self="selectedPrediction = null"
            >
                <section class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white shadow-2xl">
                    <header class="sticky top-0 z-10 border-b border-slate-200 bg-white p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                    {{ selectedPrediction.country }} / {{ selectedPrediction.league }}
                                </p>
                                <h2 class="mt-2 text-2xl font-semibold text-slate-950">
                                    {{ selectedPrediction.homeTeam }} vs {{ selectedPrediction.awayTeam }}
                                </h2>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-slate-700">
                                        {{ formatDate(selectedPrediction.matchDate) }} {{ selectedPrediction.matchTime?.slice(0, 5) ?? 'TBD' }}
                                    </span>
                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-slate-700">
                                        {{ selectedPrediction.modelName ?? 'No model name' }}
                                    </span>
                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-slate-700">
                                        Generated {{ formatTimestamp(selectedPrediction.generatedAt) }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <a
                                    :href="predictionExternalUrl(selectedPrediction)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                    Open source
                                </a>
                                <button
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-slate-950 text-white transition hover:bg-slate-800"
                                    @click="selectedPrediction = null"
                                >
                                    <X class="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    </header>

                    <div class="grid gap-4 p-5">
                        <article class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Prediction pick</p>
                                    <h3 class="mt-1 text-xl font-semibold text-slate-950">{{ selectedPrediction.recommendedMarket ?? '-' }}</h3>
                                </div>
                                <span
                                    class="inline-flex h-14 min-w-14 items-center justify-center rounded-lg px-4 text-2xl font-bold"
                                    :class="outcomeTone(selectedPrediction.predictedOutcome)"
                                >
                                    {{ selectedPrediction.predictedOutcome ?? '-' }}
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3 md:grid-cols-3">
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <p class="text-xs text-slate-500">Confidence</p>
                                    <p class="mt-1 font-semibold">{{ formatPercent(selectedPrediction.confidence, 1) }}</p>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <p class="text-xs text-slate-500">Value edge</p>
                                    <p class="mt-1 font-semibold">{{ formatPercent(selectedPrediction.valueEdge, 1) }}</p>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <p class="text-xs text-slate-500">Stat fire</p>
                                    <p class="mt-1 font-semibold">{{ formatNumber(selectedPrediction.statFireScore, 1) }}</p>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-lg border border-slate-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Probabilities</p>
                            <div class="mt-4 grid gap-3 md:grid-cols-3">
                                <div class="rounded-lg bg-emerald-50 p-4 text-center text-emerald-800">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em]">Home</p>
                                    <p class="mt-2 text-2xl font-semibold">{{ formatPercent(selectedPrediction.homeWinProbability, 1) }}</p>
                                </div>
                                <div class="rounded-lg bg-amber-50 p-4 text-center text-amber-800">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em]">Draw</p>
                                    <p class="mt-2 text-2xl font-semibold">{{ formatPercent(selectedPrediction.drawProbability, 1) }}</p>
                                </div>
                                <div class="rounded-lg bg-sky-50 p-4 text-center text-sky-800">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em]">Away</p>
                                    <p class="mt-2 text-2xl font-semibold">{{ formatPercent(selectedPrediction.awayWinProbability, 1) }}</p>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-lg border border-slate-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Why / rationale</p>
                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                {{ selectedPrediction.rationale ?? selectedPrediction.featureSummary ?? 'No rationale stored for this prediction.' }}
                            </p>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
