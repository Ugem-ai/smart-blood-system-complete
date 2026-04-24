<?php

namespace App\Services;

use App\Models\Donor;
use App\Models\DonorRequestResponse;
use App\Models\RequestMatch;
use Carbon\Carbon;

class ReliabilityScoreService
{
    // Score thresholds for labelling
    public const TIER_ELITE      = 80;
    public const TIER_RELIABLE   = 60;
    public const TIER_MODERATE   = 35;
    // < 35 = Unreliable

    // Score component weights (must sum to 100)
    private const WEIGHT_COMPLETION = 50;   // % of requests that ended in a donation
    private const WEIGHT_RESPONSE   = 30;   // % of requests that got any response (accept/decline)
    private const WEIGHT_SPEED      = 20;   // how fast they respond (within 24 h)

    private const FAST_RESPONSE_THRESHOLD_MINUTES = 1440; // 24 hours = 0 speed points

    /**
     * Compute a 0–100 reliability score for the given donor.
     *
     * Formula
     *   requests_received   = number of match rows for this donor
     *   donations_completed = confirmed donation_history records linked to this donor
     *   requests_responded  = DonorRequestResponse rows for this donor
     *   avg_response_min    = mean minutes between match created and response submitted
     *
     *   completion_rate     = donations_completed / max(1, requests_received)
     *   response_rate       = requests_responded / max(1, requests_received)
     *   speed_score         = max(0, 1 − avg_response_min / 1440)
     *
     *   score = completion_rate×50 + response_rate×30 + speed_score×20
     */
    public function compute(Donor $donor): float
    {
        $donorId = $donor->id;

        // --- requests_received (total matches ever sent) ---
        $requestsReceived = RequestMatch::where('donor_id', $donorId)->count();

        if ($requestsReceived === 0) {
            // New donor with no history gets a neutral starting score
            return 50.0;
        }

        // --- donations_completed ---
        $donationsCompleted = $donor->donationHistories()->count();

        // --- any response (accept or decline) ---
        $requestsResponded = DonorRequestResponse::where('donor_id', $donorId)->count();

        // --- average response speed in minutes (DB-agnostic) ---
        $responseRows = DonorRequestResponse::where('donor_id', $donorId)
            ->whereNotNull('responded_at')
            ->get(['blood_request_id', 'responded_at']);

        $avgResponseMinutes = null;
        if ($responseRows->isNotEmpty()) {
            $durations = [];

            foreach ($responseRows as $response) {
                $matchCreatedAt = RequestMatch::where('donor_id', $donorId)
                    ->where('blood_request_id', $response->blood_request_id)
                    ->value('created_at');

                if (! $matchCreatedAt) {
                    continue;
                }

                $minutes = Carbon::parse($matchCreatedAt)->diffInMinutes(Carbon::parse($response->responded_at));
                $durations[] = max(0, $minutes);
            }

            if (! empty($durations)) {
                $avgResponseMinutes = array_sum($durations) / count($durations);
            }
        }

        // --- compute each component ---
        $completionRate = $donationsCompleted / max(1, $requestsReceived);     // 0..1
        $responseRate   = $requestsResponded  / max(1, $requestsReceived);     // 0..1
        $speedScore     = $avgResponseMinutes !== null
            ? max(0.0, 1.0 - ((float) $avgResponseMinutes / self::FAST_RESPONSE_THRESHOLD_MINUTES))
            : 0.5;  // no response data yet → neutral speed

        $score = ($completionRate * self::WEIGHT_COMPLETION)
               + ($responseRate   * self::WEIGHT_RESPONSE)
               + ($speedScore     * self::WEIGHT_SPEED);

        return round(min(100.0, max(0.0, $score)), 2);
    }

    /**
     * Compute and persist the score for one donor.
     */
    public function update(Donor $donor): float
    {
        $score = $this->compute($donor);
        $donor->forceFill([
            'reliability_score' => round($score, 2),
        ]);
        $donor->saveQuietly();   // skip model events to avoid recursion

        return $score;
    }

    /**
     * Recalculate scores for all donors in chunks.
     * Returns the total number of donors updated.
     */
    public function bulkRecalculate(int $chunkSize = 200): int
    {
        $total = 0;

        Donor::with(['donationHistories', 'requestMatches', 'requestResponses'])
            ->chunk($chunkSize, function ($donors) use (&$total) {
                foreach ($donors as $donor) {
                    if (! $donor instanceof Donor) {
                        continue;
                    }

                    $this->update($donor);
                    $total++;
                }
            });

        return $total;
    }

    /**
     * Return a human-readable tier label for a score.
     */
    public function label(float $score): string
    {
        return match (true) {
            $score >= self::TIER_ELITE    => 'Elite',
            $score >= self::TIER_RELIABLE => 'Reliable',
            $score >= self::TIER_MODERATE => 'Moderate',
            default                       => 'Unreliable',
        };
    }
}
