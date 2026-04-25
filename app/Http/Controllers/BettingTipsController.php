<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BettingTipsController extends Controller
{
    public function __invoke(): Response
    {
        $matches = DB::table('matches')
            ->orderByDesc('match_date')
            ->orderByDesc('scraped_at')
            ->get([
                'match_id',
                'match_url',
                'canonical_url',
                'sport',
                'country',
                'league',
                'season',
                'match_date',
                'match_time',
                'match_datetime_iso',
                'status',
                'venue',
                'home_team',
                'away_team',
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
                'scraped_at',
            ]);

        $predictionsByMatch = DB::table('predictions')
            ->orderByDesc('generated_at')
            ->get([
                'match_id',
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
            ])
            ->unique('match_id')
            ->keyBy('match_id');

        $tips = $matches
            ->map(fn (object $match) => $this->buildMatchAnalysis($match, $predictionsByMatch->get($match->match_id)))
            ->values();

        $sections = [
            'over25' => [
                'title' => 'Best 5 Over 2.5',
                'market' => 'Over 2.5 Goals',
                'items' => $this->topTips($tips, 'over25Score', 'Over 2.5 Goals', 5),
            ],
            'gg' => [
                'title' => 'Best 5 GG / BTTS',
                'market' => 'Both Teams To Score',
                'items' => $this->topTips($tips, 'ggScore', 'GG / BTTS Yes', 5),
            ],
            'homeWin' => [
                'title' => 'Best 5 Home Win',
                'market' => 'Home Win',
                'items' => $this->topTips($tips, 'homeWinScore', 'Home Win', 5, fn (array $tip) => ($tip['odds']['home'] ?? 0) > 1.4),
            ],
            'awayWin' => [
                'title' => 'Best 5 Away Win',
                'market' => 'Away Win',
                'items' => $this->topTips($tips, 'awayWinScore', 'Away Win', 5, fn (array $tip) => ($tip['odds']['away'] ?? 0) > 1.4),
            ],
            'over15' => [
                'title' => 'Best 5 Over 1.5',
                'market' => 'Over 1.5 Goals',
                'items' => $this->topTips($tips, 'over15Score', 'Over 1.5 Goals', 5),
            ],
            'teamOver05' => [
                'title' => 'Best 5 Team Over 0.5',
                'market' => 'Home/Away Team Over 0.5',
                'items' => $this->topTeamOver05($tips),
            ],
        ];

        $combo = $this->comboTips($sections);

        return Inertia::render('BettingTips', [
            'summary' => [
                'matchesAnalysed' => $tips->count(),
                'withPredictions' => $tips->where('hasPrediction', true)->count(),
                'withOdds' => $tips->filter(fn (array $tip) => $tip['odds']['home'] || $tip['odds']['draw'] || $tip['odds']['away'])->count(),
                'latestScrape' => $matches->max('scraped_at'),
            ],
            'sections' => $sections,
            'combo' => $combo,
        ]);
    }

    private function buildMatchAnalysis(object $match, ?object $prediction): array
    {
        $homePlayed = max((int) ($match->home_played ?? 0), 1);
        $awayPlayed = max((int) ($match->away_played ?? 0), 1);

        $homeGoalsFor = $this->perGame($match->home_goals_for, $homePlayed);
        $homeGoalsAgainst = $this->perGame($match->home_goals_against, $homePlayed);
        $awayGoalsFor = $this->perGame($match->away_goals_for, $awayPlayed);
        $awayGoalsAgainst = $this->perGame($match->away_goals_against, $awayPlayed);

        $expectedHomeGoals = $this->clamp(($homeGoalsFor + $awayGoalsAgainst) / 2, 0.15, 3.8);
        $expectedAwayGoals = $this->clamp(($awayGoalsFor + $homeGoalsAgainst) / 2, 0.15, 3.8);
        $expectedGoals = $expectedHomeGoals + $expectedAwayGoals;

        $homeWinProbability = $this->probability($prediction?->home_win_probability);
        $drawProbability = $this->probability($prediction?->draw_probability);
        $awayWinProbability = $this->probability($prediction?->away_win_probability);

        if ($homeWinProbability === null || $drawProbability === null || $awayWinProbability === null) {
            [$homeWinProbability, $drawProbability, $awayWinProbability] = $this->probabilitiesFromOdds($match->odds_home, $match->odds_draw, $match->odds_away);
        }

        $statFire = $prediction?->stat_fire_score !== null ? (float) $prediction->stat_fire_score : $expectedGoals;
        $drawTension = $this->probability($prediction?->draw_tension_score) ?? 28.0;
        $homeStrength = $prediction?->home_strength_score !== null ? (float) $prediction->home_strength_score : $this->strengthFromTable($match, 'home');
        $awayStrength = $prediction?->away_strength_score !== null ? (float) $prediction->away_strength_score : $this->strengthFromTable($match, 'away');

        $homeRecent = $this->formScore($match->home_standings_form ?? $match->home_form ?? $match->home_recent_form);
        $awayRecent = $this->formScore($match->away_standings_form ?? $match->away_form ?? $match->away_recent_form);
        $homeCleanSheetRisk = $this->clamp($awayGoalsAgainst + $homeGoalsFor, 0, 4);
        $awayCleanSheetRisk = $this->clamp($homeGoalsAgainst + $awayGoalsFor, 0, 4);

        $over25Score = $this->clamp(($expectedGoals / 3.2) * 44 + ($statFire / 4) * 28 + (100 - $drawTension) * 0.08 + ($homeRecent + $awayRecent) * 2.2, 1, 99);
        $over15Score = $this->clamp(($expectedGoals / 2.2) * 48 + ($statFire / 3) * 30 + ($homeGoalsFor + $awayGoalsFor) * 5, 1, 99);
        $ggScore = $this->clamp(($expectedHomeGoals + $expectedAwayGoals) * 17 + min($expectedHomeGoals, $expectedAwayGoals) * 22 + ($homeCleanSheetRisk + $awayCleanSheetRisk) * 5, 1, 99);
        $homeWinScore = $this->clamp(($homeWinProbability ?? 0) * 0.7 + ($homeStrength - $awayStrength) * 8 + ($homeRecent - $awayRecent) * 3 + $this->valueBoost($match->odds_home), 1, 99);
        $awayWinScore = $this->clamp(($awayWinProbability ?? 0) * 0.7 + ($awayStrength - $homeStrength) * 8 + ($awayRecent - $homeRecent) * 3 + $this->valueBoost($match->odds_away), 1, 99);
        $homeOver05Score = $this->clamp($expectedHomeGoals * 34 + ($homeGoalsFor * 12) + ($awayGoalsAgainst * 8) + $homeRecent * 3, 1, 99);
        $awayOver05Score = $this->clamp($expectedAwayGoals * 34 + ($awayGoalsFor * 12) + ($homeGoalsAgainst * 8) + $awayRecent * 3, 1, 99);

        return [
            'matchId' => $match->match_id,
            'matchUrl' => $match->match_url ?? $match->canonical_url,
            'country' => $match->country ?? 'Unknown',
            'league' => $match->league ?? 'Unknown',
            'sport' => $match->sport ?? 'Football',
            'season' => $match->season,
            'matchDate' => $match->match_date,
            'matchTime' => $match->match_time,
            'status' => $match->status ?? 'Unknown',
            'venue' => $match->venue,
            'homeTeam' => $match->home_team ?? 'Unknown',
            'awayTeam' => $match->away_team ?? 'Unknown',
            'odds' => [
                'home' => $match->odds_home ? round((float) $match->odds_home, 2) : null,
                'draw' => $match->odds_draw ? round((float) $match->odds_draw, 2) : null,
                'away' => $match->odds_away ? round((float) $match->odds_away, 2) : null,
            ],
            'probabilities' => [
                'home' => $homeWinProbability,
                'draw' => $drawProbability,
                'away' => $awayWinProbability,
            ],
            'expectedGoals' => round($expectedGoals, 2),
            'expectedHomeGoals' => round($expectedHomeGoals, 2),
            'expectedAwayGoals' => round($expectedAwayGoals, 2),
            'statFireScore' => round($statFire, 2),
            'homeStrength' => round($homeStrength, 2),
            'awayStrength' => round($awayStrength, 2),
            'homeFormScore' => round($homeRecent, 2),
            'awayFormScore' => round($awayRecent, 2),
            'homeRank' => $match->home_rank,
            'awayRank' => $match->away_rank,
            'hasForms' => $this->hasForm($match->home_standings_form ?? $match->home_form ?? $match->home_recent_form)
                && $this->hasForm($match->away_standings_form ?? $match->away_form ?? $match->away_recent_form),
            'hasDominantRank' => $this->hasDominantRank($match->home_rank, $match->away_rank),
            'hasPrediction' => (bool) $prediction,
            'modelName' => $prediction?->model_name,
            'generatedAt' => $prediction?->generated_at,
            'modelMarket' => $prediction?->recommended_market,
            'modelOutcome' => strtoupper((string) ($prediction?->predicted_outcome ?? '')),
            'modelConfidence' => $this->probability($prediction?->confidence),
            'featureSummary' => $prediction?->feature_summary,
            'rationale' => $prediction?->rationale,
            'scores' => [
                'over25Score' => round($over25Score, 1),
                'ggScore' => round($ggScore, 1),
                'homeWinScore' => round($homeWinScore, 1),
                'awayWinScore' => round($awayWinScore, 1),
                'over15Score' => round($over15Score, 1),
                'homeOver05Score' => round($homeOver05Score, 1),
                'awayOver05Score' => round($awayOver05Score, 1),
            ],
        ];
    }

    private function topTips(Collection $tips, string $scoreKey, string $market, int $limit, ?callable $filter = null): array
    {
        return $tips
            ->filter(fn (array $tip) => $filter ? $filter($tip) : true)
            ->sortByDesc(fn (array $tip) => $tip['scores'][$scoreKey])
            ->take($limit)
            ->values()
            ->map(fn (array $tip) => $this->toBetTip($tip, $market, $tip['scores'][$scoreKey], $this->whyForMarket($tip, $market)))
            ->all();
    }

    private function topTeamOver05(Collection $tips): array
    {
        return $tips
            ->flatMap(fn (array $tip) => [
                $this->toBetTip($tip, "{$tip['homeTeam']} Over 0.5 Team Goals", $tip['scores']['homeOver05Score'], $this->whyForTeamGoal($tip, 'home')),
                $this->toBetTip($tip, "{$tip['awayTeam']} Over 0.5 Team Goals", $tip['scores']['awayOver05Score'], $this->whyForTeamGoal($tip, 'away')),
            ])
            ->sortByDesc('score')
            ->unique(fn (array $tip) => $tip['matchId'].'|'.$tip['market'])
            ->take(5)
            ->values()
            ->all();
    }

    private function comboTips(array $sections): array
    {
        return collect($sections)
            ->flatMap(fn (array $section) => $section['items'])
            ->sortByDesc('score')
            ->unique('matchId')
            ->take(10)
            ->values()
            ->all();
    }

    private function toBetTip(array $tip, string $market, float $score, array $why): array
    {
        return [
            'matchId' => $tip['matchId'],
            'matchUrl' => $tip['matchUrl'],
            'country' => $tip['country'],
            'league' => $tip['league'],
            'matchDate' => $tip['matchDate'],
            'matchTime' => $tip['matchTime'],
            'status' => $tip['status'],
            'homeTeam' => $tip['homeTeam'],
            'awayTeam' => $tip['awayTeam'],
            'market' => $market,
            'score' => round($score, 1),
            'confidenceLabel' => $this->confidenceLabel($score),
            'odds' => $tip['odds'],
            'probabilities' => $tip['probabilities'],
            'expectedGoals' => $tip['expectedGoals'],
            'expectedHomeGoals' => $tip['expectedHomeGoals'],
            'expectedAwayGoals' => $tip['expectedAwayGoals'],
            'statFireScore' => $tip['statFireScore'],
            'homeStrength' => $tip['homeStrength'],
            'awayStrength' => $tip['awayStrength'],
            'homeRank' => $tip['homeRank'],
            'awayRank' => $tip['awayRank'],
            'hasForms' => $tip['hasForms'],
            'hasDominantRank' => $tip['hasDominantRank'],
            'modelName' => $tip['modelName'],
            'modelMarket' => $tip['modelMarket'],
            'modelOutcome' => $tip['modelOutcome'],
            'modelConfidence' => $tip['modelConfidence'],
            'why' => $why,
        ];
    }

    private function whyForMarket(array $tip, string $market): array
    {
        return match ($market) {
            'Over 2.5 Goals' => [
                "Expected goals is {$tip['expectedGoals']}, with both teams combining for a strong attacking profile.",
                "Stat Fire score is {$tip['statFireScore']}, so the match projects above a normal goal line.",
                'The pick is boosted when both sides show enough scoring form and the draw tension is not too dominant.',
            ],
            'Both Teams To Score' => [
                "Projected goals split is {$tip['expectedHomeGoals']} home and {$tip['expectedAwayGoals']} away.",
                'Both attacks rate high enough against the opposite defensive record.',
                'This is preferred when neither side profiles like a clean-sheet lock.',
            ],
            'Home Win' => [
                "Home win probability is {$this->displayPercent($tip['probabilities']['home'])}.",
                "Home strength {$tip['homeStrength']} is ahead of away strength {$tip['awayStrength']}.",
                "Home odd is {$this->displayOdd($tip['odds']['home'])}, above the requested 1.40 minimum.",
            ],
            'Away Win' => [
                "Away win probability is {$this->displayPercent($tip['probabilities']['away'])}.",
                "Away strength {$tip['awayStrength']} is ahead of home strength {$tip['homeStrength']}.",
                "Away odd is {$this->displayOdd($tip['odds']['away'])}, above the requested 1.40 minimum.",
            ],
            'Over 1.5 Goals' => [
                "Expected goals is {$tip['expectedGoals']}, enough for a safer goals line.",
                "Home projection {$tip['expectedHomeGoals']} plus away projection {$tip['expectedAwayGoals']} supports at least two goals.",
                'This line is selected before Over 2.5 when the goal profile is solid but not explosive.',
            ],
            default => [
                'The match ranks high on the combined score for this market.',
                'The pick uses model probabilities, odds, team strength, goals data, and recent form where available.',
            ],
        };
    }

    private function whyForTeamGoal(array $tip, string $side): array
    {
        $team = $side === 'home' ? $tip['homeTeam'] : $tip['awayTeam'];
        $expected = $side === 'home' ? $tip['expectedHomeGoals'] : $tip['expectedAwayGoals'];
        $strength = $side === 'home' ? $tip['homeStrength'] : $tip['awayStrength'];

        return [
            "{$team} projects for {$expected} expected goals.",
            "{$team} strength score is {$strength}, enough to support one goal.",
            'Team Over 0.5 is chosen when the individual scoring projection is stronger than the full-match risk.',
        ];
    }

    private function confidenceLabel(float $score): string
    {
        if ($score >= 78) {
            return 'Very strong';
        }

        if ($score >= 65) {
            return 'Strong';
        }

        if ($score >= 52) {
            return 'Good';
        }

        return 'Lean';
    }

    private function probabilitiesFromOdds(null|int|float|string $homeOdd, null|int|float|string $drawOdd, null|int|float|string $awayOdd): array
    {
        $home = $this->positiveFloat($homeOdd);
        $draw = $this->positiveFloat($drawOdd);
        $away = $this->positiveFloat($awayOdd);

        if (! $home || ! $draw || ! $away) {
            return [null, null, null];
        }

        $homeImplied = 1 / $home;
        $drawImplied = 1 / $draw;
        $awayImplied = 1 / $away;
        $total = $homeImplied + $drawImplied + $awayImplied;

        return [
            round(($homeImplied / $total) * 100, 1),
            round(($drawImplied / $total) * 100, 1),
            round(($awayImplied / $total) * 100, 1),
        ];
    }

    private function probability(null|int|float|string $value): ?float
    {
        $float = $this->positiveFloat($value);

        if ($float === null) {
            return null;
        }

        return round($float <= 1 ? $float * 100 : $float, 1);
    }

    private function positiveFloat(null|int|float|string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $float = (float) $value;

        return $float > 0 ? $float : null;
    }

    private function perGame(null|int|float|string $value, int $played): float
    {
        return ((float) ($value ?? 0)) / max($played, 1);
    }

    private function strengthFromTable(object $match, string $side): float
    {
        $points = (float) ($side === 'home' ? ($match->home_points ?? 0) : ($match->away_points ?? 0));
        $played = max((int) ($side === 'home' ? ($match->home_played ?? 0) : ($match->away_played ?? 0)), 1);
        $goalDifference = (float) ($side === 'home' ? ($match->home_goal_difference ?? 0) : ($match->away_goal_difference ?? 0));

        return $this->clamp(($points / $played) + ($goalDifference / $played) * 0.15, 0.2, 3.5);
    }

    private function formScore(?string $form): float
    {
        if (! $form) {
            return 0;
        }

        preg_match_all('/[WDL]/', strtoupper($form), $matches);

        return collect(array_slice($matches[0], -5))
            ->sum(fn (string $result) => match ($result) {
                'W' => 1.0,
                'D' => 0.35,
                default => -0.2,
            });
    }

    private function hasForm(?string $form): bool
    {
        if (! $form) {
            return false;
        }

        return preg_match('/[WDL]/i', $form) === 1;
    }

    private function hasDominantRank(mixed $homeRank, mixed $awayRank): bool
    {
        if ($homeRank === null || $awayRank === null || $homeRank === '' || $awayRank === '') {
            return false;
        }

        return abs((int) $homeRank - (int) $awayRank) >= 3;
    }

    private function valueBoost(null|int|float|string $odd): float
    {
        $odd = $this->positiveFloat($odd);

        if (! $odd) {
            return 0;
        }

        return $this->clamp(($odd - 1.4) * 5, 0, 8);
    }

    private function displayPercent(?float $value): string
    {
        return $value === null ? 'not available' : round($value, 1).'%';
    }

    private function displayOdd(null|float $value): string
    {
        return $value === null ? 'not available' : number_format($value, 2);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
