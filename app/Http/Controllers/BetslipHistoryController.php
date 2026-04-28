<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class BetslipHistoryController extends Controller
{
    public function __invoke(): Response
    {
        $rows = Schema::hasTable('prediction_daily_betslip')
            ? DB::table('prediction_daily_betslip as p')
                ->leftJoin('matches as m', 'm.match_id', '=', 'p.match_id')
                ->orderByDesc('p.slip_date')
                ->orderBy('p.slip_position')
                ->get([
                    'p.id_key',
                    'p.slip_date',
                    'p.slip_position',
                    'p.match_id',
                    'p.source_match_url',
                    'p.generated_at',
                    'p.country',
                    'p.league',
                    'p.home_team',
                    'p.away_team',
                    'p.market',
                    'p.advice',
                    'p.selected_odd',
                    'p.probability',
                    'p.confidence',
                    'p.edge',
                    'p.daily_score',
                    'p.total_slip_odd',
                    'p.rationale',
                    'm.status as match_status',
                    'm.score_home',
                    'm.score_away',
                    'm.match_date',
                    'm.match_time',
                    'm.match_url',
                    'm.canonical_url',
                ])
            : collect();

        $items = $rows
            ->map(function (object $row) {
                $result = $this->betResult(
                    $row->market,
                    $row->advice,
                    $row->score_home,
                    $row->score_away,
                    $row->match_status,
                );

                return [
                    'idKey' => $row->id_key,
                    'slipDate' => $row->slip_date,
                    'slipPosition' => (int) $row->slip_position,
                    'matchId' => $row->match_id,
                    'sourceMatchUrl' => $row->match_url ?? $row->canonical_url ?? $row->source_match_url,
                    'generatedAt' => $row->generated_at,
                    'country' => $row->country ?? 'Unknown',
                    'league' => $row->league ?? 'Unknown',
                    'homeTeam' => $row->home_team ?? 'Unknown',
                    'awayTeam' => $row->away_team ?? 'Unknown',
                    'market' => $row->market,
                    'advice' => $row->advice,
                    'selectedOdd' => $this->decimal($row->selected_odd, 2),
                    'probability' => $this->percent($row->probability),
                    'confidence' => $this->percent($row->confidence),
                    'edge' => $this->percent($row->edge),
                    'dailyScore' => $this->decimal($row->daily_score, 2),
                    'totalSlipOdd' => $this->decimal($row->total_slip_odd, 2),
                    'rationale' => $row->rationale,
                    'matchStatus' => $row->match_status ?? 'Unknown',
                    'matchDate' => $row->match_date ?? $row->slip_date,
                    'matchTime' => $row->match_time,
                    'scoreHome' => $row->score_home,
                    'scoreAway' => $row->score_away,
                    'result' => $result['status'],
                    'resultLabel' => $result['label'],
                ];
            })
            ->values();

        return Inertia::render('BetslipHistory', [
            'summary' => [
                'total' => $items->count(),
                'won' => $items->where('result', 'won')->count(),
                'lost' => $items->where('result', 'lost')->count(),
                'pending' => $items->where('result', 'pending')->count(),
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

    private function betResult(mixed $market, mixed $advice, mixed $scoreHome, mixed $scoreAway, mixed $status): array
    {
        if (! $this->isFinished($status) || $scoreHome === null || $scoreAway === null || $scoreHome === '' || $scoreAway === '') {
            return ['status' => 'pending', 'label' => 'Pending'];
        }

        $home = (int) $scoreHome;
        $away = (int) $scoreAway;
        $total = $home + $away;
        $pick = strtoupper((string) ($advice ?: $market));
        $marketText = strtoupper((string) ($market ?: $advice));
        $won = null;

        if (str_contains($pick, 'HOME') || $pick === '1') {
            $won = $home > $away;
        } elseif (str_contains($pick, 'AWAY') || $pick === '2') {
            $won = $away > $home;
        } elseif (str_contains($pick, 'DRAW') || $pick === 'X') {
            $won = $home === $away;
        }

        if (str_contains($marketText, 'OVER_2_5') || str_contains($marketText, 'OVER 2.5')) {
            $won = $total > 2.5;
        } elseif (str_contains($marketText, 'OVER_1_5') || str_contains($marketText, 'OVER 1.5')) {
            $won = $total > 1.5;
        } elseif (str_contains($marketText, 'BTTS') || str_contains($marketText, 'GG')) {
            $won = $home > 0 && $away > 0;
        } elseif (str_contains($marketText, 'HOME') && str_contains($marketText, '0.5')) {
            $won = $home > 0;
        } elseif (str_contains($marketText, 'AWAY') && str_contains($marketText, '0.5')) {
            $won = $away > 0;
        }

        if ($won === null) {
            return ['status' => 'pending', 'label' => 'Pending'];
        }

        return $won ? ['status' => 'won', 'label' => 'Won'] : ['status' => 'lost', 'label' => 'Lost'];
    }

    private function isFinished(mixed $status): bool
    {
        $value = strtolower((string) $status);

        return str_contains($value, 'ft')
            || str_contains($value, 'finished')
            || str_contains($value, 'after')
            || str_contains($value, 'aet')
            || str_contains($value, 'pen');
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
