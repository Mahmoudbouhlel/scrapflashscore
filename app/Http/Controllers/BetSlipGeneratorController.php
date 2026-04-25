<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BetSlipGeneratorController extends Controller
{
    public function __invoke(): Response
    {
        $predictions = DB::table('predictions')
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

        $matches = DB::table('matches')
            ->orderBy('match_date')
            ->orderBy('match_time')
            ->get([
                'match_id',
                'match_url',
                'canonical_url',
                'country',
                'league',
                'match_date',
                'match_time',
                'status',
                'home_team',
                'away_team',
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
            ]);

        $candidates = $matches
            ->flatMap(fn (object $match) => $this->candidateMarkets($match, $predictions->get($match->match_id)))
            ->sortByDesc('score')
            ->values();

        return Inertia::render('BetSlipGenerator', [
            'summary' => [
                'matches' => $matches->count(),
                'candidates' => $candidates->count(),
                'withPredictions' => $matches->filter(fn (object $match) => $predictions->has($match->match_id))->count(),
                'dates' => $matches->pluck('match_date')->filter()->unique()->sort()->values()->all(),
            ],
            'filters' => [
                'dates' => $matches->pluck('match_date')->filter()->unique()->sort()->values()->all(),
                'countries' => $matches->pluck('country')->filter()->unique()->sort()->values()->all(),
                'leagues' => $matches->pluck('league')->filter()->unique()->sort()->values()->all(),
                'markets' => ['Home Win', 'Away Win', 'Over 1.5', 'Over 2.5', 'GG / BTTS', 'Home Team Over 0.5', 'Away Team Over 0.5'],
            ],
            'candidates' => $candidates,
        ]);
    }

    private function candidateMarkets(object $match, ?object $prediction): array
    {
        $homePlayed = max((int) ($match->home_played ?? 0), 1);
        $awayPlayed = max((int) ($match->away_played ?? 0), 1);
        $homeFor = $this->perGame($match->home_goals_for, $homePlayed);
        $homeAgainst = $this->perGame($match->home_goals_against, $homePlayed);
        $awayFor = $this->perGame($match->away_goals_for, $awayPlayed);
        $awayAgainst = $this->perGame($match->away_goals_against, $awayPlayed);
        $expectedHome = $this->clamp(($homeFor + $awayAgainst) / 2, 0.1, 4);
        $expectedAway = $this->clamp(($awayFor + $homeAgainst) / 2, 0.1, 4);
        $expectedGoals = $expectedHome + $expectedAway;

        [$homeProb, $drawProb, $awayProb] = $this->predictionOrOddsProbabilities($prediction, $match);
        $statFire = $prediction?->stat_fire_score !== null ? (float) $prediction->stat_fire_score : $expectedGoals;
        $homeStrength = $prediction?->home_strength_score !== null ? (float) $prediction->home_strength_score : $this->tableStrength($match, 'home');
        $awayStrength = $prediction?->away_strength_score !== null ? (float) $prediction->away_strength_score : $this->tableStrength($match, 'away');
        $homeForm = $this->formScore($match->home_standings_form ?? $match->home_form ?? $match->home_recent_form);
        $awayForm = $this->formScore($match->away_standings_form ?? $match->away_form ?? $match->away_recent_form);

        $base = [
            'matchId' => $match->match_id,
            'matchUrl' => $match->match_url ?? $match->canonical_url,
            'country' => $match->country ?? 'Unknown',
            'league' => $match->league ?? 'Unknown',
            'matchDate' => $match->match_date,
            'matchTime' => $match->match_time,
            'status' => $match->status ?? 'Unknown',
            'homeTeam' => $match->home_team ?? 'Unknown',
            'awayTeam' => $match->away_team ?? 'Unknown',
            'homeRank' => $match->home_rank,
            'awayRank' => $match->away_rank,
            'homePoints' => $match->home_points,
            'awayPoints' => $match->away_points,
            'hasRanks' => $match->home_rank !== null && $match->away_rank !== null,
            'hasPrediction' => (bool) $prediction,
            'modelName' => $prediction?->model_name,
            'modelMarket' => $prediction?->recommended_market,
            'modelConfidence' => $this->percent($prediction?->confidence),
            'expectedGoals' => round($expectedGoals, 2),
            'expectedHomeGoals' => round($expectedHome, 2),
            'expectedAwayGoals' => round($expectedAway, 2),
            'statFireScore' => round($statFire, 2),
            'homeStrength' => round($homeStrength, 2),
            'awayStrength' => round($awayStrength, 2),
            'homeFormScore' => round($homeForm, 2),
            'awayFormScore' => round($awayForm, 2),
            'hasForms' => $this->hasForm($match->home_standings_form ?? $match->home_form ?? $match->home_recent_form)
                && $this->hasForm($match->away_standings_form ?? $match->away_form ?? $match->away_recent_form),
            'hasDominantRank' => $this->hasDominantRank($match->home_rank, $match->away_rank),
            'odds' => [
                'home' => $match->odds_home ? round((float) $match->odds_home, 2) : null,
                'draw' => $match->odds_draw ? round((float) $match->odds_draw, 2) : null,
                'away' => $match->odds_away ? round((float) $match->odds_away, 2) : null,
            ],
        ];

        return [
            $this->candidate($base, 'Home Win', $match->odds_home, $this->clamp(($homeProb ?? 0) * 0.75 + ($homeStrength - $awayStrength) * 7 + ($homeForm - $awayForm) * 2.5, 1, 99), [
                "Home probability {$this->showPercent($homeProb)} with home strength {$base['homeStrength']}.",
                "Rank data: home {$this->show($match->home_rank)} vs away {$this->show($match->away_rank)}.",
                'Selected when the model/odds signal and table strength lean toward the home team.',
            ]),
            $this->candidate($base, 'Away Win', $match->odds_away, $this->clamp(($awayProb ?? 0) * 0.75 + ($awayStrength - $homeStrength) * 7 + ($awayForm - $homeForm) * 2.5, 1, 99), [
                "Away probability {$this->showPercent($awayProb)} with away strength {$base['awayStrength']}.",
                "Rank data: home {$this->show($match->home_rank)} vs away {$this->show($match->away_rank)}.",
                'Selected when the model/odds signal and table strength lean toward the away team.',
            ]),
            $this->candidate($base, 'Over 1.5', null, $this->clamp(($expectedGoals / 2.2) * 52 + ($statFire / 3) * 26 + ($homeFor + $awayFor) * 5, 1, 99), [
                "Expected goals is {$base['expectedGoals']}, enough for a safer goals line.",
                "Home xG {$base['expectedHomeGoals']} plus away xG {$base['expectedAwayGoals']} supports at least two goals.",
                'Selected as a lower-risk goals market when scoring profile is solid.',
            ]),
            $this->candidate($base, 'Over 2.5', null, $this->clamp(($expectedGoals / 3.1) * 48 + ($statFire / 4) * 28 + ($homeForm + $awayForm) * 2, 1, 99), [
                "Expected goals is {$base['expectedGoals']}, with Stat Fire {$base['statFireScore']}.",
                'Both teams have enough attacking/defensive profile to support a higher goal line.',
                'Selected when the goal ceiling is stronger than the safer Over 1.5 line.',
            ]),
            $this->candidate($base, 'GG / BTTS', null, $this->clamp(($expectedHome + $expectedAway) * 16 + min($expectedHome, $expectedAway) * 26 + ($homeFor + $awayFor) * 6, 1, 99), [
                "Goal split is {$base['expectedHomeGoals']} home and {$base['expectedAwayGoals']} away.",
                'Both sides project with enough scoring chance to support GG.',
                'Selected when neither team looks like a strong clean-sheet candidate.',
            ]),
            $this->candidate($base, 'Home Team Over 0.5', null, $this->clamp($expectedHome * 38 + $homeFor * 12 + $awayAgainst * 8 + $homeForm * 3, 1, 99), [
                "{$base['homeTeam']} projects for {$base['expectedHomeGoals']} goals.",
                'Home attack and away defensive record support at least one home goal.',
                'Selected as a team-goal market when full-match result is riskier.',
            ]),
            $this->candidate($base, 'Away Team Over 0.5', null, $this->clamp($expectedAway * 38 + $awayFor * 12 + $homeAgainst * 8 + $awayForm * 3, 1, 99), [
                "{$base['awayTeam']} projects for {$base['expectedAwayGoals']} goals.",
                'Away attack and home defensive record support at least one away goal.',
                'Selected as a team-goal market when full-match result is riskier.',
            ]),
        ];
    }

    private function candidate(array $base, string $market, mixed $marketOdd, float $score, array $why): array
    {
        return [
            ...$base,
            'market' => $market,
            'marketOdd' => $marketOdd ? round((float) $marketOdd, 2) : null,
            'score' => round($score, 1),
            'why' => $why,
        ];
    }

    private function predictionOrOddsProbabilities(?object $prediction, object $match): array
    {
        if ($prediction?->home_win_probability !== null && $prediction?->draw_probability !== null && $prediction?->away_win_probability !== null) {
            return [$this->percent($prediction->home_win_probability), $this->percent($prediction->draw_probability), $this->percent($prediction->away_win_probability)];
        }

        $home = $this->positive($match->odds_home);
        $draw = $this->positive($match->odds_draw);
        $away = $this->positive($match->odds_away);

        if (! $home || ! $draw || ! $away) {
            return [null, null, null];
        }

        $homeImplied = 1 / $home;
        $drawImplied = 1 / $draw;
        $awayImplied = 1 / $away;
        $total = $homeImplied + $drawImplied + $awayImplied;

        return [round(($homeImplied / $total) * 100, 1), round(($drawImplied / $total) * 100, 1), round(($awayImplied / $total) * 100, 1)];
    }

    private function percent(mixed $value): ?float
    {
        $value = $this->positive($value);

        if ($value === null) {
            return null;
        }

        return round($value <= 1 ? $value * 100 : $value, 1);
    }

    private function positive(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $float = (float) $value;

        return $float > 0 ? $float : null;
    }

    private function perGame(mixed $value, int $played): float
    {
        return ((float) ($value ?? 0)) / max($played, 1);
    }

    private function tableStrength(object $match, string $side): float
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

        return collect(array_slice($matches[0], -5))->sum(fn (string $result) => match ($result) {
            'W' => 1,
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

    private function show(mixed $value): string
    {
        return $value === null || $value === '' ? '-' : (string) $value;
    }

    private function showPercent(?float $value): string
    {
        return $value === null ? '-' : $value.'%';
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
