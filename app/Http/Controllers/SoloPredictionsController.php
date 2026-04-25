<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SoloPredictionsController extends Controller
{
    public function __invoke(): Response
    {
        $rows = Schema::hasTable('prediction_solo')
            ? DB::table('prediction_solo as ps')
                ->leftJoin('matches as m', 'm.match_id', '=', 'ps.match_id')
                ->orderByDesc('ps.recommended')
                ->orderByDesc('ps.confidence')
                ->orderByDesc('ps.edge')
                ->get([
                    'ps.id_key',
                    'ps.match_id',
                    'ps.source_match_url',
                    'ps.model_name',
                    'ps.generated_at',
                    'ps.solo_market',
                    'ps.advice',
                    'ps.selected_odd',
                    'ps.probability',
                    'ps.implied_probability',
                    'ps.edge',
                    'ps.confidence',
                    'ps.solo_score',
                    'ps.recommended',
                    'ps.feature_summary',
                    'ps.rationale',
                    'ps.features_json',
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
                'soloMarket' => $row->solo_market,
                'advice' => $row->advice,
                'selectedOdd' => $row->selected_odd !== null ? round((float) $row->selected_odd, 2) : null,
                'probability' => $this->percent($row->probability),
                'impliedProbability' => $this->percent($row->implied_probability),
                'edge' => $this->percent($row->edge),
                'confidence' => $this->percent($row->confidence),
                'soloScore' => $row->solo_score !== null ? round((float) $row->solo_score, 2) : null,
                'recommended' => (bool) $row->recommended,
                'featureSummary' => $row->feature_summary,
                'rationale' => $row->rationale,
                'featuresJson' => $row->features_json,
                'country' => $row->country ?? 'Unknown',
                'league' => $row->league ?? 'Unknown',
                'matchDate' => $row->match_date,
                'matchTime' => $row->match_time,
                'status' => $row->status ?? 'Unknown',
                'homeTeam' => $row->home_team,
                'awayTeam' => $row->away_team,
                'fixtureLabel' => $this->fixtureLabel($row->feature_summary, $row->source_match_url, $row->home_team, $row->away_team),
            ])
            ->values();

        return Inertia::render('SoloPredictions', [
            'summary' => [
                'total' => $items->count(),
                'recommended' => $items->where('recommended', true)->count(),
                'avgProbability' => round((float) $items->avg('probability'), 1),
                'avgEdge' => round((float) $items->avg('edge'), 1),
                'latestGeneratedAt' => $rows->max('generated_at'),
            ],
            'filters' => [
                'dates' => $items->pluck('matchDate')->filter()->unique()->sort()->values()->all(),
                'markets' => $items->pluck('soloMarket')->filter()->unique()->sort()->values()->all(),
                'advice' => $items->pluck('advice')->filter()->unique()->sort()->values()->all(),
                'models' => $items->pluck('modelName')->filter()->unique()->sort()->values()->all(),
                'countries' => $items->pluck('country')->filter()->unique()->sort()->values()->all(),
                'leagues' => $items->pluck('league')->filter()->unique()->sort()->values()->all(),
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
