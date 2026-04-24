<?php

namespace App\Services;

use App\Models\BloodRequest;
use App\Models\DonationHistory;
use Carbon\Carbon;

class BloodSupplyForecastService
{
    /**
     * @return array<string, mixed>
     */
    public function buildForecast(int $months = 6): array
    {
        $months = max(3, min(12, $months));

        $demandTrends = $this->bloodTypeDemandTrends($months);
        $donationPatterns = $this->monthlyDonationPatterns($months);
        $shortage = $this->shortagePrediction();

        return [
            'blood_type_demand_trends' => $demandTrends,
            'monthly_donation_patterns' => $donationPatterns,
            'shortage_prediction' => $shortage,
            'forecast_summary' => $this->summary($demandTrends, $shortage),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function bloodTypeDemandTrends(int $months = 6): array
    {
        $start = now()->copy()->startOfMonth()->subMonths($months - 1);
        $requests = BloodRequest::query()
            ->where('created_at', '>=', $start)
            ->get(['blood_type', 'requested_units', 'units_required', 'quantity', 'created_at']);

        $series = [];

        foreach ($requests as $request) {
            $type = strtoupper((string) $request->blood_type);
            $month = Carbon::parse($request->created_at)->format('Y-m');
            $units = (int) ($request->requested_units ?? $request->units_required ?? $request->quantity ?? 1);

            if (! isset($series[$type])) {
                $series[$type] = [];
            }

            if (! isset($series[$type][$month])) {
                $series[$type][$month] = 0;
            }

            $series[$type][$month] += max(1, $units);
        }

        $result = [];

        foreach ($series as $bloodType => $monthly) {
            ksort($monthly);
            $values = array_values($monthly);
            $first = $values[0] ?? 0;
            $last = $values[count($values) - 1] ?? 0;

            $changePercent = $first > 0
                ? round((($last - $first) / $first) * 100, 2)
                : ($last > 0 ? 100.0 : 0.0);

            $direction = 'stable';
            if ($changePercent > 10) {
                $direction = 'increasing';
            } elseif ($changePercent < -10) {
                $direction = 'decreasing';
            }

            $result[] = [
                'blood_type' => $bloodType,
                'trend_direction' => $direction,
                'change_percent' => $changePercent,
                'monthly_units' => $monthly,
                'latest_month_units' => $last,
            ];
        }

        usort($result, fn (array $a, array $b) => $b['latest_month_units'] <=> $a['latest_month_units']);

        return $result;
    }

    /**
     * @return array<int, array{month: string, total_units: int}>
     */
    public function monthlyDonationPatterns(int $months = 6): array
    {
        $start = now()->copy()->startOfMonth()->subMonths($months - 1);

        $donations = DonationHistory::query()
            ->whereDate('donation_date', '>=', $start->toDateString())
            ->get(['donation_date', 'units']);

        $monthTotals = [];

        foreach ($donations as $donation) {
            $month = Carbon::parse($donation->donation_date ?? $donation->created_at)->format('Y-m');
            if (! isset($monthTotals[$month])) {
                $monthTotals[$month] = 0;
            }
            $monthTotals[$month] += (int) ($donation->units ?? 1);
        }

        ksort($monthTotals);

        $result = [];
        foreach ($monthTotals as $month => $units) {
            $result[] = [
                'month' => $month,
                'total_units' => (int) $units,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function shortagePrediction(): array
    {
        $now = now();
        $recentStart = $now->copy()->subDays(30);
        $previousStart = $now->copy()->subDays(60);

        $requestsRecent = BloodRequest::query()
            ->where('created_at', '>=', $recentStart)
            ->get(['id', 'blood_type', 'requested_units', 'units_required', 'quantity']);

        $requestsPrev = BloodRequest::query()
            ->whereBetween('created_at', [$previousStart, $recentStart])
            ->get(['id', 'blood_type', 'requested_units', 'units_required', 'quantity']);

        $requestIdsRecent = $requestsRecent->pluck('id')->all();
        $requestIdsPrev = $requestsPrev->pluck('id')->all();

        $donationsRecent = DonationHistory::query()
            ->whereIn('request_id', $requestIdsRecent)
            ->get(['request_id', 'units']);

        $donationsPrev = DonationHistory::query()
            ->whereIn('request_id', $requestIdsPrev)
            ->get(['request_id', 'units']);

        $demandByTypeRecent = [];
        foreach ($requestsRecent as $request) {
            $type = strtoupper((string) $request->blood_type);
            $units = (int) ($request->requested_units ?? $request->units_required ?? $request->quantity ?? 1);
            $demandByTypeRecent[$type] = ($demandByTypeRecent[$type] ?? 0) + max(1, $units);
        }

        $demandByTypePrev = [];
        foreach ($requestsPrev as $request) {
            $type = strtoupper((string) $request->blood_type);
            $units = (int) ($request->requested_units ?? $request->units_required ?? $request->quantity ?? 1);
            $demandByTypePrev[$type] = ($demandByTypePrev[$type] ?? 0) + max(1, $units);
        }

        $requestTypeRecent = $requestsRecent->keyBy('id')->map(fn ($r) => strtoupper((string) $r->blood_type));
        $requestTypePrev = $requestsPrev->keyBy('id')->map(fn ($r) => strtoupper((string) $r->blood_type));

        $supplyByTypeRecent = [];
        foreach ($donationsRecent as $donation) {
            $type = $requestTypeRecent[$donation->request_id] ?? null;
            if (! $type) {
                continue;
            }
            $supplyByTypeRecent[$type] = ($supplyByTypeRecent[$type] ?? 0) + (int) ($donation->units ?? 1);
        }

        $supplyByTypePrev = [];
        foreach ($donationsPrev as $donation) {
            $type = $requestTypePrev[$donation->request_id] ?? null;
            if (! $type) {
                continue;
            }
            $supplyByTypePrev[$type] = ($supplyByTypePrev[$type] ?? 0) + (int) ($donation->units ?? 1);
        }

        $types = array_values(array_unique(array_merge(
            array_keys($demandByTypeRecent),
            array_keys($supplyByTypeRecent),
            array_keys($demandByTypePrev),
            array_keys($supplyByTypePrev)
        )));

        sort($types);

        $predictions = [];

        foreach ($types as $type) {
            $demandRecent = (int) ($demandByTypeRecent[$type] ?? 0);
            $supplyRecent = (int) ($supplyByTypeRecent[$type] ?? 0);
            $supplyPrev = (int) ($supplyByTypePrev[$type] ?? 0);

            $coverageRatio = $demandRecent > 0 ? round($supplyRecent / $demandRecent, 2) : 1.0;
            $shortageUnits = max(0, $demandRecent - $supplyRecent);

            $status = 'stable';
            if ($coverageRatio < 0.60) {
                $status = 'critical';
            } elseif ($coverageRatio < 0.90) {
                $status = 'warning';
            }

            $supplyTrend = $supplyRecent < $supplyPrev ? 'decreasing' : ($supplyRecent > $supplyPrev ? 'increasing' : 'stable');

            $predictions[] = [
                'blood_type' => $type,
                'status' => $status,
                'demand_units_30d' => $demandRecent,
                'supply_units_30d' => $supplyRecent,
                'coverage_ratio' => $coverageRatio,
                'predicted_shortage_units' => $shortageUnits,
                'supply_trend' => $supplyTrend,
                'message' => $this->messageFor($type, $status, $supplyTrend),
            ];
        }

        usort($predictions, function (array $a, array $b) {
            $weight = ['critical' => 3, 'warning' => 2, 'stable' => 1];
            $cmp = ($weight[$b['status']] ?? 0) <=> ($weight[$a['status']] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }

            return $b['predicted_shortage_units'] <=> $a['predicted_shortage_units'];
        });

        return $predictions;
    }

    /**
     * @param array<int, array<string, mixed>> $demandTrends
     * @param array<int, array<string, mixed>> $shortagePrediction
     * @return array<string, mixed>
     */
    private function summary(array $demandTrends, array $shortagePrediction): array
    {
        $alerts = [];

        foreach ($shortagePrediction as $row) {
            if (($row['status'] ?? 'stable') === 'critical') {
                $alerts[] = ($row['blood_type'] ?? 'Unknown').' critical shortage';
            }

            if (($row['supply_trend'] ?? 'stable') === 'decreasing') {
                $alerts[] = ($row['blood_type'] ?? 'Unknown').' supply decreasing';
            }
        }

        return [
            'critical_shortages' => count(array_filter($shortagePrediction, fn (array $row) => ($row['status'] ?? null) === 'critical')),
            'warning_shortages' => count(array_filter($shortagePrediction, fn (array $row) => ($row['status'] ?? null) === 'warning')),
            'demand_types_tracked' => count($demandTrends),
            'alerts' => array_values(array_unique($alerts)),
        ];
    }

    private function messageFor(string $bloodType, string $status, string $supplyTrend): string
    {
        if ($status === 'critical') {
            return $bloodType.' critical shortage';
        }

        if ($status === 'warning') {
            return $bloodType.' supply under pressure';
        }

        if ($supplyTrend === 'decreasing') {
            return $bloodType.' supply decreasing';
        }

        return $bloodType.' supply stable';
    }
}
