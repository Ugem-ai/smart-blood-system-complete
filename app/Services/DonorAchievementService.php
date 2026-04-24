<?php

namespace App\Services;

use App\Models\Donor;

class DonorAchievementService
{
    /**
     * @return array<int, array{key: string, title: string, threshold: int, unlocked: bool, unlocked_at_count: int|null, progress_percent: int}>
     */
    public function achievementsForDonor(Donor $donor): array
    {
        $completedDonations = $this->completedDonationCount($donor);

        return collect($this->definitions())
            ->map(function (array $definition) use ($completedDonations) {
                $unlocked = $completedDonations >= $definition['threshold'];
                $progressPercent = (int) min(100, round(($completedDonations / $definition['threshold']) * 100));

                return [
                    'key' => $definition['key'],
                    'title' => $definition['title'],
                    'threshold' => $definition['threshold'],
                    'unlocked' => $unlocked,
                    'unlocked_at_count' => $unlocked ? $definition['threshold'] : null,
                    'progress_percent' => $progressPercent,
                ];
            })
            ->all();
    }

    /**
     * @return array{completed_donations: int, unlocked_count: int, total_achievements: int, next_threshold: int|null, donations_to_next: int|null, retention_message: string}
     */
    public function engagementSummary(Donor $donor): array
    {
        $completedDonations = $this->completedDonationCount($donor);
        $definitions = $this->definitions();

        $unlockedCount = collect($definitions)
            ->filter(fn (array $definition) => $completedDonations >= $definition['threshold'])
            ->count();

        $next = collect($definitions)
            ->first(fn (array $definition) => $completedDonations < $definition['threshold']);

        $retentionMessage = $next
            ? sprintf('Only %d more donation(s) to unlock %s.', $next['threshold'] - $completedDonations, $next['title'])
            : 'All achievement milestones unlocked. You are a top lifesaver donor.';

        return [
            'completed_donations' => $completedDonations,
            'unlocked_count' => $unlockedCount,
            'total_achievements' => count($definitions),
            'next_threshold' => $next['threshold'] ?? null,
            'donations_to_next' => $next ? max(0, $next['threshold'] - $completedDonations) : null,
            'retention_message' => $retentionMessage,
        ];
    }

    private function completedDonationCount(Donor $donor): int
    {
        return $donor->donationHistories()
            ->where('status', 'completed')
            ->count();
    }

    /**
     * @return array<int, array{key: string, title: string, threshold: int}>
     */
    private function definitions(): array
    {
        return [
            ['key' => 'first_donation', 'title' => 'First Donation', 'threshold' => 1],
            ['key' => 'five_donations', 'title' => '5 Donations', 'threshold' => 5],
            ['key' => 'ten_donations', 'title' => '10 Donations', 'threshold' => 10],
            ['key' => 'lifesaver_badge', 'title' => 'Lifesaver Badge', 'threshold' => 20],
        ];
    }
}
