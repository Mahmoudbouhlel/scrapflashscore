<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class GoalPredictionsController extends Controller
{
    public function __invoke(): Response
    {
        $rows = Schema::hasTable('prediction_goals')
            ? DB::table('prediction_goals as pg')
                ->leftJoin('matches as m', 'm.match_id', '=', 'pg.match_id')
                ->orderByDesc('pg.recommended')
                ->orderByDesc('pg.confidence')
                ->orderByDesc('pg.probability')
                ->get([
                    'pg.id_key',
                    'pg.match_id',
                    'pg.source_match_url',
                    'pg.model_name',
                    'pg.generated_at',
                    'pg.goal_market',
                    'pg.advice',
                    'pg.probability',
                    'pg.confidence',
                    'pg.goal_score',
                    'pg.home_xg',
                    'pg.away_xg',
                    'pg.total_xg',
                    'pg.btts_yes_probability',
                    'pg.over_2_5_probability',
                    'pg.recommended',
                    'pg.feature_summary',
                    'pg.rationale',
                    'pg.features_json',
                    'm.country',
                    'm.league',
                    'm.match_date',
                    'm.match_time',
                    'm.home_team',
                    'm.away_team',
                    'm.status',
                ])
            : collect();

        $items = $rows
            ->map(fn (object $row) => [
                'idKey' => $row->id_key,
                'matchId' => $row->match_id,
                'sourceMatchUrl' => $row->source_match_url,
                'modelName' => $row->model_name,
                'generatedAt' => $row->generated_at,
                'goalMarket' => $row->goal_market,
                'advice' => $row->advice,
                'probability' => $this->percent($row->probability),
                'confidence' => $this->percent($row->confidence),
                'goalScore' => $row->goal_score !== null ? round((float) $row->goal_score, 2) : null,
                'homeXg' => $row->home_xg !== null ? round((float) $row->home_xg, 2) : null,
                'awayXg' => $row->away_xg !== null ? round((float) $row->away_xg, 2) : null,
                'totalXg' => $row->total_xg !== null ? round((float) $row->total_xg, 2) : null,
                'bttsYesProbability' => $this->percent($row->btts_yes_probability),
                'over25Probability' => $this->percent($row->over_2_5_probability),
                'recommended' => (bool) $row->recommended,
                'featureSummary' => $row->feature_summary,
                'rationale' => $row->rationale,
                'featuresJson' => $row->features_json,
                'country' => $row->country ?? 'Unknown',
                'league' => $row->league ?? 'Unknown',
                'matchDate' => $row->match_date,
                'matchTime' => $row->match_time,
                'status' => $row->status ?? 'Unknown',
                'fixtureLabel' => $this->fixtureLabel($row->feature_summary, $row->source_match_url, $row->home_team, $row->away_team),
            ])
            ->values();

        return Inertia::render('GoalPredictions', [
            'summary' => [
                'total' => $items->count(),
                'recommended' => $items->where('recommended', true)->count(),
                'avgProbability' => round((float) $items->avg('probability'), 1),
                'avgTotalXg' => round((float) $items->avg('totalXg'), 2),
                'latestGeneratedAt' => $rows->max('generated_at'),
            ],
            'filters' => [
                'markets' => $items->pluck('goalMarket')->filter()->unique()->sort()->values()->all(),
                'dates' => $items->pluck('matchDate')->filter()->unique()->sort()->values()->all(),
                'advice' => $items->pluck('advice')->filter()->unique()->sort()->values()->all(),
                'models' => $items->pluck('modelName')->filter()->unique()->sort()->values()->all(),
                'generatedAts' => $items->pluck('generatedAt')->filter()->unique()->sort()->values()->all(),
            ],
            'items' => $items,
        ]);
    }

    private function percent(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $float = (float) $value;

        return round($float <= 1 ? $float * 100 : $float, 1);
    }

    private function fixtureLabel(?string $summary, ?string $url, ?string $homeTeam, ?string $awayTeam): string
    {
        if ($homeTeam && $awayTeam) {
            return "{$homeTeam} vs {$awayTeam}";
        }

        if ($summary && str_contains($summary, ' | ')) {
            return trim(explode(' | ', $summary)[0]);
        }

        if (! $url) {
            return 'Unknown fixture';
        }

        $path = parse_url($url, PHP_URL_PATH);
        $segments = is_string($path) ? array_values(array_filter(explode('/', trim($path, '/')))) : [];

        if (count($segments) < 4) {
            return 'Unknown fixture';
        }

        $away = $this->humanizeSlug($segments[count($segments) - 2]);
        $home = $this->humanizeSlug($segments[count($segments) - 1]);

        return "{$home} vs {$away}";
    }

    private function humanizeSlug(string $slug): string
    {
        $withoutId = preg_replace('/-[A-Za-z0-9]+$/', '', $slug) ?? $slug;

        return ucwords(str_replace('-', ' ', $withoutId));
    }
}
