<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $matches = DB::table('matches')
            ->orderByDesc('match_date')
            ->orderByDesc('scraped_at')
            ->get([
                'match_id',
                'source_page_url',
                'match_url',
                'canonical_url',
                'sport',
                'country',
                'league',
                'season',
                'match_date',
                'match_time',
                'match_datetime_text',
                'match_datetime_iso',
                'status',
                'venue',
                'referee',
                'home_team',
                'away_team',
                'home_team_url',
                'away_team_url',
                'score_home',
                'score_away',
                'odds_home',
                'odds_draw',
                'odds_away',
                'home_rank',
                'home_points',
                'home_played',
                'home_wins',
                'home_draws',
                'home_losses',
                'home_goals_for',
                'home_goals_against',
                'home_goal_difference',
                'home_standings_form',
                'home_form',
                'home_recent_form',
                'away_rank',
                'away_points',
                'away_played',
                'away_wins',
                'away_draws',
                'away_losses',
                'away_goals_for',
                'away_goals_against',
                'away_goal_difference',
                'away_standings_form',
                'away_form',
                'away_recent_form',
                'page_title',
                'breadcrumb_country',
                'breadcrumb_league',
                'breadcrumb_items_json',
                'validation_warnings_json',
                'scraped_at',
            ]);

        $predictions = DB::table('predictions')
            ->orderByDesc('generated_at')
            ->get([
                'match_id',
                'source_match_url',
                'model_name',
                'generated_at',
                'predicted_outcome',
                'recommended_market',
                'confidence',
                'home_win_probability',
                'draw_probability',
                'away_win_probability',
                'home_strength_score',
                'away_strength_score',
                'draw_tension_score',
                'value_edge',
                'stat_fire_score',
                'feature_summary',
                'rationale',
                'probabilities_json',
                'features_json',
            ]);

        $standings = DB::table('standings_rows')
            ->where('table_type', 'overall')
            ->orderByDesc('points')
            ->orderByDesc('goal_difference')
            ->orderBy('rank')
            ->get([
                'source_match_id',
                'rank',
                'team',
                'played',
                'wins',
                'draws',
                'losses',
                'goals_for',
                'goals_against',
                'goal_difference',
                'points',
                'form',
            ]);

        $h2hRows = DB::table('h2h_matches')
            ->orderByDesc('match_date')
            ->get([
                'source_match_id',
                'section_type',
                'match_date',
                'match_datetime_iso',
                'country',
                'league',
                'home_team',
                'away_team',
                'score_home',
                'score_away',
                'result_text',
                'odds_home',
                'odds_draw',
                'odds_away',
            ]);

        $teamLastResults = DB::table('team_last_results')
            ->orderByDesc('match_date')
            ->get([
                'source_match_id',
                'section_team',
                'section_type',
                'match_date',
                'match_datetime_iso',
                'country',
                'league',
                'home_team',
                'away_team',
                'score_home',
                'score_away',
                'result_text',
                'odds_home',
                'odds_draw',
                'odds_away',
                'result_flag',
            ]);

        $scrapeErrorColumns = Schema::hasTable('scrape_errors') ? Schema::getColumnListing('scrape_errors') : [];
        $scrapeErrorOrderColumn = collect(['created_at', 'scraped_at', 'id'])
            ->first(fn (string $column) => in_array($column, $scrapeErrorColumns, true));

        $scrapeErrorQuery = DB::table('scrape_errors');

        if ($scrapeErrorOrderColumn) {
            $scrapeErrorQuery->orderByDesc($scrapeErrorOrderColumn);
        }

        $scrapeErrors = $scrapeErrorQuery
            ->limit(12)
            ->get()
            ->map(fn (object $error) => collect((array) $error)->map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value))->all())
            ->values();

        $tableStats = collect(['matches', 'predictions', 'standings_rows', 'team_last_results', 'h2h_matches', 'scrape_errors'])
            ->map(fn (string $table) => [
                'name' => $table,
                'rows' => Schema::hasTable($table) ? DB::table($table)->count() : 0,
                'columns' => Schema::hasTable($table) ? count(Schema::getColumnListing($table)) : 0,
            ])
            ->values();

        $teamStandings = $standings
            ->unique('team')
            ->values();

        $teamStandingsByNormalized = $teamStandings
            ->keyBy(fn (object $row) => $this->normalizeTeamName($row->team));

        $predictionByMatch = $predictions
            ->keyBy('match_id');

        $matchById = $matches
            ->keyBy('match_id');

        $h2hByMatch = $h2hRows
            ->groupBy('source_match_id')
            ->map(fn (Collection $rows) => $rows
                ->take(5)
                ->map(fn (object $row) => [
                    'date' => $row->match_date,
                    'country' => $row->country,
                    'league' => $row->league,
                    'homeTeam' => $row->home_team,
                    'awayTeam' => $row->away_team,
                    'score' => $this->formatScore($row->score_home, $row->score_away),
                    'resultText' => $row->result_text,
                    'sectionType' => $row->section_type,
                    'odds' => [
                        'home' => $row->odds_home ? round((float) $row->odds_home, 2) : null,
                        'draw' => $row->odds_draw ? round((float) $row->odds_draw, 2) : null,
                        'away' => $row->odds_away ? round((float) $row->odds_away, 2) : null,
                    ],
                ])
                ->values()
                ->all());

        $recentResultsByMatch = $teamLastResults
            ->groupBy('source_match_id')
            ->map(fn (Collection $rows) => $rows
                ->take(8)
                ->map(fn (object $row) => [
                    'sectionTeam' => $row->section_team,
                    'sectionType' => $row->section_type,
                    'date' => $row->match_date,
                    'country' => $row->country,
                    'league' => $row->league,
                    'homeTeam' => $row->home_team,
                    'awayTeam' => $row->away_team,
                    'score' => $this->formatScore($row->score_home, $row->score_away),
                    'resultText' => $row->result_text,
                    'resultFlag' => $row->result_flag,
                ])
                ->values()
                ->all());

        $leagueCards = $matches
            ->groupBy(fn (object $match) => sprintf('%s||%s', $match->country ?? 'Unknown', $match->league ?? 'Unknown'))
            ->map(function (Collection $leagueMatches, string $key) use ($predictions) {
                [$country, $league] = explode('||', $key);
                $matchIds = $leagueMatches->pluck('match_id');
                $leaguePredictions = $predictions->whereIn('match_id', $matchIds);

                return [
                    'country' => $country,
                    'league' => $league,
                    'matches' => $leagueMatches->count(),
                    'predictions' => $leaguePredictions->count(),
                    'avgConfidence' => round((float) $leaguePredictions->avg('confidence') * 100, 1),
                    'topFixture' => $leagueMatches->map(fn (object $match) => $match->home_team.' vs '.$match->away_team)->first(),
                ];
            })
            ->sortByDesc('matches')
            ->take(8)
            ->values();

        $rankings = $standings
            ->unique('team')
            ->take(12)
            ->map(fn (object $team) => [
                'rank' => (int) $team->rank,
                'team' => $team->team,
                'played' => (int) $team->played,
                'wins' => (int) $team->wins,
                'draws' => (int) $team->draws,
                'losses' => (int) $team->losses,
                'goalsFor' => (int) $team->goals_for,
                'goalsAgainst' => (int) $team->goals_against,
                'goalDifference' => (int) $team->goal_difference,
                'points' => (int) $team->points,
                'winRate' => $this->safePercent((int) $team->wins, max((int) $team->played, 1)),
                'form' => $this->parseForm($team->form),
            ])
            ->values();

        $allMatches = $matches
            ->map(function (object $match) use ($predictionByMatch, $teamStandingsByNormalized, $h2hByMatch, $recentResultsByMatch) {
                $prediction = $predictionByMatch->get($match->match_id);
                $homeStanding = $this->findStandingForTeam($teamStandingsByNormalized, $match->home_team);
                $awayStanding = $this->findStandingForTeam($teamStandingsByNormalized, $match->away_team);

                return [
                    'matchId' => $match->match_id,
                    'country' => $match->country ?? 'Unknown',
                    'league' => $match->league ?? 'Unknown',
                    'sport' => $match->sport ?? 'Unknown',
                    'season' => $match->season,
                    'matchDate' => $match->match_date,
                    'matchTime' => $match->match_time,
                    'matchDatetimeText' => $match->match_datetime_text,
                    'matchDatetimeIso' => $match->match_datetime_iso,
                    'status' => $match->status ?? 'Unknown',
                    'venue' => $match->venue,
                    'referee' => $match->referee,
                    'scoreHome' => $match->score_home,
                    'scoreAway' => $match->score_away,
                    'scrapedAt' => $match->scraped_at,
                    'sourcePageUrl' => $match->source_page_url,
                    'matchUrl' => $match->match_url,
                    'canonicalUrl' => $match->canonical_url,
                    'homeTeamUrl' => $match->home_team_url,
                    'awayTeamUrl' => $match->away_team_url,
                    'pageTitle' => $match->page_title,
                    'breadcrumbCountry' => $match->breadcrumb_country,
                    'breadcrumbLeague' => $match->breadcrumb_league,
                    'breadcrumbItemsJson' => $match->breadcrumb_items_json,
                    'validationWarningsJson' => $match->validation_warnings_json,
                    'hasPrediction' => (bool) $prediction,
                    'predictedOutcome' => $prediction ? strtoupper((string) $prediction->predicted_outcome) : null,
                    'recommendedMarket' => $prediction?->recommended_market,
                    'confidence' => $prediction ? round((float) $prediction->confidence * 100, 1) : null,
                    'valueEdge' => $prediction ? round((float) $prediction->value_edge * 100, 1) : null,
                    'statFireScore' => $prediction ? round((float) $prediction->stat_fire_score, 1) : null,
                    'drawTensionScore' => $prediction ? round((float) $prediction->draw_tension_score * 100, 1) : null,
                    'featureSummary' => $prediction?->feature_summary,
                    'rationale' => $prediction?->rationale,
                    'probabilities' => [
                        'home' => $prediction ? round((float) $prediction->home_win_probability * 100, 1) : null,
                        'draw' => $prediction ? round((float) $prediction->draw_probability * 100, 1) : null,
                        'away' => $prediction ? round((float) $prediction->away_win_probability * 100, 1) : null,
                    ],
                    'homeTeam' => [
                        'name' => $match->home_team,
                        'rank' => $homeStanding?->rank ?? $match->home_rank,
                        'points' => $homeStanding?->points ?? $match->home_points,
                        'played' => $homeStanding?->played ?? $match->home_played,
                        'wins' => $homeStanding?->wins ?? $match->home_wins,
                        'draws' => $homeStanding?->draws ?? $match->home_draws,
                        'losses' => $homeStanding?->losses ?? $match->home_losses,
                        'goalsFor' => $homeStanding?->goals_for ?? $match->home_goals_for,
                        'goalsAgainst' => $homeStanding?->goals_against ?? $match->home_goals_against,
                        'goalDifference' => $homeStanding?->goal_difference ?? $match->home_goal_difference,
                        'form' => $this->parseForm($homeStanding?->form ?? $match->home_standings_form ?? $match->home_form ?? $match->home_recent_form),
                        'strengthScore' => $prediction ? round((float) $prediction->home_strength_score, 2) : null,
                    ],
                    'awayTeam' => [
                        'name' => $match->away_team,
                        'rank' => $awayStanding?->rank ?? $match->away_rank,
                        'points' => $awayStanding?->points ?? $match->away_points,
                        'played' => $awayStanding?->played ?? $match->away_played,
                        'wins' => $awayStanding?->wins ?? $match->away_wins,
                        'draws' => $awayStanding?->draws ?? $match->away_draws,
                        'losses' => $awayStanding?->losses ?? $match->away_losses,
                        'goalsFor' => $awayStanding?->goals_for ?? $match->away_goals_for,
                        'goalsAgainst' => $awayStanding?->goals_against ?? $match->away_goals_against,
                        'goalDifference' => $awayStanding?->goal_difference ?? $match->away_goal_difference,
                        'form' => $this->parseForm($awayStanding?->form ?? $match->away_standings_form ?? $match->away_form ?? $match->away_recent_form),
                        'strengthScore' => $prediction ? round((float) $prediction->away_strength_score, 2) : null,
                    ],
                    'odds' => [
                        'home' => $match->odds_home ? round((float) $match->odds_home, 2) : null,
                        'draw' => $match->odds_draw ? round((float) $match->odds_draw, 2) : null,
                        'away' => $match->odds_away ? round((float) $match->odds_away, 2) : null,
                    ],
                    'h2hSnippets' => $h2hByMatch->get($match->match_id, []),
                    'recentResults' => $recentResultsByMatch->get($match->match_id, []),
                ];
            })
            ->sortBy([
                ['hasPrediction', 'desc'],
                ['confidence', 'desc'],
                ['matchDate', 'desc'],
            ])
            ->values();

        $featuredMatches = $allMatches
            ->where('hasPrediction', true)
            ->take(6)
            ->values();

        $marketDistribution = $predictions
            ->groupBy('recommended_market')
            ->map(fn (Collection $group, string $market) => [
                'market' => $market,
                'count' => $group->count(),
                'share' => $this->safePercent($group->count(), max($predictions->count(), 1)),
            ])
            ->sortByDesc('count')
            ->take(6)
            ->values();

        $allPredictions = $predictions
            ->map(function (object $prediction) use ($matchById, $teamStandingsByNormalized) {
                $match = $matchById->get($prediction->match_id);
                [$derivedHomeTeam, $derivedAwayTeam] = $this->derivePredictionTeams(
                    $prediction->feature_summary,
                    $prediction->source_match_url,
                );
                $homeTeamName = $match?->home_team ?? $derivedHomeTeam;
                $awayTeamName = $match?->away_team ?? $derivedAwayTeam;
                $homeStanding = $this->findStandingForTeam($teamStandingsByNormalized, $homeTeamName);
                $awayStanding = $this->findStandingForTeam($teamStandingsByNormalized, $awayTeamName);

                return [
                    'matchId' => $prediction->match_id,
                    'sourceMatchUrl' => $prediction->source_match_url,
                    'modelName' => $prediction->model_name,
                    'generatedAt' => $prediction->generated_at,
                    'predictedOutcome' => strtoupper((string) $prediction->predicted_outcome),
                    'recommendedMarket' => $prediction->recommended_market,
                    'confidence' => round((float) $prediction->confidence * 100, 1),
                    'homeWinProbability' => round((float) $prediction->home_win_probability * 100, 1),
                    'drawProbability' => round((float) $prediction->draw_probability * 100, 1),
                    'awayWinProbability' => round((float) $prediction->away_win_probability * 100, 1),
                    'homeStrengthScore' => $prediction->home_strength_score !== null ? round((float) $prediction->home_strength_score, 2) : null,
                    'awayStrengthScore' => $prediction->away_strength_score !== null ? round((float) $prediction->away_strength_score, 2) : null,
                    'drawTensionScore' => $prediction->draw_tension_score !== null ? round((float) $prediction->draw_tension_score * 100, 1) : null,
                    'valueEdge' => $prediction->value_edge !== null ? round((float) $prediction->value_edge * 100, 1) : null,
                    'statFireScore' => $prediction->stat_fire_score !== null ? round((float) $prediction->stat_fire_score, 1) : null,
                    'featureSummary' => $prediction->feature_summary,
                    'rationale' => $prediction->rationale,
                    'probabilitiesJson' => $prediction->probabilities_json,
                    'featuresJson' => $prediction->features_json,
                    'country' => $match->country ?? 'Unknown',
                    'league' => $match->league ?? 'Unknown',
                    'homeTeam' => $homeTeamName,
                    'awayTeam' => $awayTeamName,
                    'homeRank' => $homeStanding?->rank,
                    'awayRank' => $awayStanding?->rank,
                    'homePoints' => $homeStanding?->points,
                    'awayPoints' => $awayStanding?->points,
                    'homeForm' => $this->parseForm($homeStanding?->form),
                    'awayForm' => $this->parseForm($awayStanding?->form),
                    'status' => $match?->status ?? 'Unknown',
                    'matchDate' => $match?->match_date,
                    'matchTime' => $match?->match_time,
                    'matchUrl' => $match?->match_url ?? $prediction->source_match_url,
                ];
            })
            ->sortByDesc('confidence')
            ->values();

        $summary = [
            'totalMatches' => $matches->count(),
            'totalPredictions' => $predictions->count(),
            'trackedTeams' => $standings->unique('team')->count(),
            'activeLeagues' => $matches->map(fn (object $match) => ($match->country ?? 'Unknown').'|'.($match->league ?? 'Unknown'))->unique()->count(),
            'avgConfidence' => round((float) $predictions->avg('confidence') * 100, 1),
            'avgStatFireScore' => round((float) $predictions->avg('stat_fire_score'), 1),
            'latestScrape' => $matches->max('scraped_at'),
            'topMarket' => $marketDistribution->first()['market'] ?? 'N/A',
            'scrapeErrors' => $scrapeErrors->count(),
        ];

        $filters = [
            'sports' => $this->stringOptions($matches->pluck('sport')),
            'countries' => $this->stringOptions($matches->pluck('country')),
            'leagues' => $this->stringOptions($matches->pluck('league')),
            'seasons' => $this->stringOptions($matches->pluck('season')),
            'statuses' => $this->stringOptions($matches->pluck('status')),
            'dates' => $this->stringOptions($matches->pluck('match_date')),
            'predictionModels' => $this->stringOptions($predictions->pluck('model_name')),
            'predictionMarkets' => $this->stringOptions($predictions->pluck('recommended_market')),
            'predictionOutcomes' => $this->stringOptions($predictions->pluck('predicted_outcome')->map(fn (?string $value) => $value ? strtoupper($value) : null)),
            'predictionGeneratedAts' => $this->stringOptions($predictions->pluck('generated_at')),
        ];

        return Inertia::render('Dashboard', [
            'summary' => $summary,
            'filters' => $filters,
            'leagueCards' => $leagueCards,
            'rankings' => $rankings,
            'featuredMatches' => $featuredMatches,
            'marketDistribution' => $marketDistribution,
            'allMatches' => $allMatches,
            'allPredictions' => $allPredictions,
            'tableStats' => $tableStats,
            'scrapeErrors' => $scrapeErrors,
        ]);
    }

    private function parseForm(?string $form): array
    {
        if (! $form) {
            return [];
        }

        preg_match_all('/[WDL]/', strtoupper($form), $matches);

        return array_slice($matches[0], -5);
    }

    private function safePercent(float|int $value, float|int $total): float
    {
        if ($total <= 0) {
            return 0;
        }

        return round(($value / $total) * 100, 1);
    }

    private function formatScore(null|int|string $homeScore, null|int|string $awayScore): ?string
    {
        if ($homeScore === null || $awayScore === null || $homeScore === '' || $awayScore === '') {
            return null;
        }

        return $homeScore.' - '.$awayScore;
    }

    private function stringOptions(Collection $values): array
    {
        return $values
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function derivePredictionTeams(?string $featureSummary, ?string $sourceMatchUrl): array
    {
        $fromSummary = $this->extractTeamsFromFeatureSummary($featureSummary);

        if ($fromSummary !== null) {
            return $fromSummary;
        }

        $fromUrl = $this->extractTeamsFromMatchUrl($sourceMatchUrl);

        if ($fromUrl !== null) {
            return $fromUrl;
        }

        return ['Unknown', 'Unknown'];
    }

    private function extractTeamsFromFeatureSummary(?string $featureSummary): ?array
    {
        if (! $featureSummary) {
            return null;
        }

        if (preg_match('/PPG\s+(.+?)\s+\d+(?:\.\d+)?\s+vs\s+(.+?)\s+\d+(?:\.\d+)?/i', $featureSummary, $matches) === 1) {
            return [trim($matches[1]), trim($matches[2])];
        }

        return null;
    }

    private function extractTeamsFromMatchUrl(?string $sourceMatchUrl): ?array
    {
        if (! $sourceMatchUrl) {
            return null;
        }

        $path = parse_url($sourceMatchUrl, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($segments) < 4) {
            return null;
        }

        $awaySlug = $segments[count($segments) - 2];
        $homeSlug = $segments[count($segments) - 1];

        return [
            $this->humanizeTeamSlug($homeSlug),
            $this->humanizeTeamSlug($awaySlug),
        ];
    }

    private function humanizeTeamSlug(string $slug): string
    {
        $withoutId = preg_replace('/-[A-Za-z0-9]+$/', '', $slug) ?? $slug;
        $normalized = str_replace('-', ' ', $withoutId);

        return ucwords(trim($normalized));
    }

    private function findStandingForTeam(Collection $teamStandingsByNormalized, ?string $teamName): ?object
    {
        if (! $teamName) {
            return null;
        }

        $normalized = $this->normalizeTeamName($teamName);

        return $teamStandingsByNormalized->get($normalized);
    }

    private function normalizeTeamName(?string $teamName): string
    {
        if (! $teamName) {
            return '';
        }

        $value = Str::of($teamName)
            ->ascii()
            ->lower()
            ->replaceMatches('/\([^)]*\)/', ' ')
            ->replaceMatches('/\b(fc|cf|ac|sc|sv|fk|if|bk|afc|am|ii|b|u\d+|women|w)\b/u', ' ')
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        return $value;
    }
}
