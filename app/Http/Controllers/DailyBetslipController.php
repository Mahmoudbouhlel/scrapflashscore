<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DailyBetslipController extends Controller
{
    public function __invoke(): Response
    {
        $rows = Schema::hasTable('prediction_daily_betslip')
            ? DB::table('prediction_daily_betslip')
                ->orderByDesc('slip_date')
                ->orderBy('slip_position')
                ->get([
                    'id_key',
                    'slip_date',
                    'slip_position',
                    'match_id',
                    'source_match_url',
                    'model_name',
                    'generated_at',
                    'country',
                    'league',
                    'home_team',
                    'away_team',
                    'market',
                    'advice',
                    'selected_odd',
                    'min_odd',
                    'max_odd',
                    'probability',
                    'implied_probability',
                    'edge',
                    'confidence',
                    'daily_score',
                    'total_slip_odd',
                    'feature_summary',
                    'rationale',
                    'features_json',
                ])
            : collect();

        $items = $rows
            ->map(fn (object $row) => [
                'idKey' => $row->id_key,
                'slipDate' => $row->slip_date,
                'slipPosition' => (int) $row->slip_position,
                'matchId' => $row->match_id,
                'sourceMatchUrl' => $row->source_match_url,
                'modelName' => $row->model_name,
                'generatedAt' => $row->generated_at,
                'country' => $row->country ?? 'Unknown',
                'league' => $row->league ?? 'Unknown',
                'homeTeam' => $row->home_team ?? 'Unknown',
                'awayTeam' => $row->away_team ?? 'Unknown',
                'market' => $row->market,
                'advice' => $row->advice,
                'selectedOdd' => $this->decimal($row->selected_odd, 2),
                'minOdd' => $this->decimal($row->min_odd, 2),
                'maxOdd' => $this->decimal($row->max_odd, 2),
                'probability' => $this->percent($row->probability),
                'impliedProbability' => $this->percent($row->implied_probability),
                'edge' => $this->percent($row->edge),
                'confidence' => $this->percent($row->confidence),
                'dailyScore' => $this->decimal($row->daily_score, 2),
                'totalSlipOdd' => $this->decimal($row->total_slip_odd, 2),
                'featureSummary' => $row->feature_summary,
                'rationale' => $row->rationale,
                'featuresJson' => $row->features_json,
            ])
            ->values();

        $slips = $items
            ->groupBy('slipDate')
            ->map(fn ($dateItems, string $date) => [
                'date' => $date,
                'count' => $dateItems->count(),
                'totalOdd' => $dateItems->max('totalSlipOdd'),
                'avgConfidence' => round((float) $dateItems->avg('confidence'), 1),
                'avgEdge' => round((float) $dateItems->avg('edge'), 1),
                'items' => $dateItems->sortBy('slipPosition')->values(),
            ])
            ->sortByDesc('date')
            ->values();

        return Inertia::render('DailyBetslip', [
            'summary' => [
                'totalRows' => $items->count(),
                'slipCount' => $slips->count(),
                'latestDate' => $items->max('slipDate'),
                'latestTotalOdd' => $slips->first()['totalOdd'] ?? null,
                'avgConfidence' => round((float) $items->avg('confidence'), 1),
                'avgEdge' => round((float) $items->avg('edge'), 1),
            ],
            'filters' => [
                'dates' => $items->pluck('slipDate')->filter()->unique()->sort()->values()->all(),
                'markets' => $items->pluck('market')->filter()->unique()->sort()->values()->all(),
                'advice' => $items->pluck('advice')->filter()->unique()->sort()->values()->all(),
                'countries' => $items->pluck('country')->filter()->unique()->sort()->values()->all(),
                'leagues' => $items->pluck('league')->filter()->unique()->sort()->values()->all(),
            ],
            'slips' => $slips,
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

    private function decimal(mixed $value, int $digits): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, $digits);
    }
}
