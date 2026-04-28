<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class HistoryDailyDController extends Controller
{
    public function __invoke(): Response
    {
        $rows = Schema::hasTable('history_daily_d')
            ? DB::table('history_daily_d')
                ->orderByDesc('slip_date')
                ->orderBy('slip_position')
                ->get([
                    'id_key',
                    'slip_date',
                    'slip_position',
                    'match_id',
                    'source_match_url',
                    'country',
                    'league',
                    'home_team',
                    'away_team',
                    'market',
                    'advice',
                    'selected_odd',
                    'probability',
                    'confidence',
                    'daily_score',
                    'total_slip_odd',
                    'match_status',
                    'score_home',
                    'score_away',
                    'actual_outcome',
                    'pick_result',
                    'profit_units',
                    'settled_at',
                    'checked_at',
                ])
            : collect();

        $items = $rows
            ->map(fn (object $row) => [
                'idKey' => $row->id_key,
                'slipDate' => $row->slip_date,
                'slipPosition' => (int) $row->slip_position,
                'matchId' => $row->match_id,
                'sourceMatchUrl' => $row->source_match_url,
                'country' => $row->country ?? 'Unknown',
                'league' => $row->league ?? 'Unknown',
                'homeTeam' => $row->home_team ?? 'Unknown',
                'awayTeam' => $row->away_team ?? 'Unknown',
                'market' => $row->market,
                'advice' => $row->advice,
                'selectedOdd' => $this->decimal($row->selected_odd, 2),
                'probability' => $this->percent($row->probability),
                'confidence' => $this->percent($row->confidence),
                'dailyScore' => $this->decimal($row->daily_score, 2),
                'totalSlipOdd' => $this->decimal($row->total_slip_odd, 2),
                'matchStatus' => $row->match_status ?? 'Unknown',
                'scoreHome' => $row->score_home,
                'scoreAway' => $row->score_away,
                'actualOutcome' => $row->actual_outcome,
                'pickResult' => $row->pick_result,
                'result' => $this->normalizeResult($row->pick_result),
                'profitUnits' => $this->decimal($row->profit_units, 2),
                'settledAt' => $row->settled_at,
                'checkedAt' => $row->checked_at,
            ])
            ->values();

        return Inertia::render('HistoryDailyD', [
            'summary' => [
                'total' => $items->count(),
                'won' => $items->where('result', 'won')->count(),
                'lost' => $items->where('result', 'lost')->count(),
                'pending' => $items->where('result', 'pending')->count(),
                'profitUnits' => round((float) $items->sum('profitUnits'), 2),
                'latestDate' => $items->max('slipDate'),
            ],
            'filters' => [
                'dates' => $items->pluck('slipDate')->filter()->unique()->sort()->values()->all(),
                'markets' => $items->pluck('market')->filter()->unique()->sort()->values()->all(),
                'countries' => $items->pluck('country')->filter()->unique()->sort()->values()->all(),
                'leagues' => $items->pluck('league')->filter()->unique()->sort()->values()->all(),
                'results' => ['won', 'lost', 'pending'],
            ],
            'items' => $items,
        ]);
    }

    private function normalizeResult(mixed $value): string
    {
        $result = strtoupper((string) $value);

        if (in_array($result, ['WON', 'WIN', 'W'], true)) {
            return 'won';
        }

        if (in_array($result, ['LOST', 'LOSE', 'LOSS', 'L'], true)) {
            return 'lost';
        }

        return 'pending';
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
