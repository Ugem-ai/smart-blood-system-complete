<?php

namespace Database\Seeders;

use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\DonorAlertLog;
use App\Models\DonorRequestResponse;
use App\Models\Hospital;
use App\Models\NotificationDelivery;
use App\Models\RequestMatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AnalyticsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $demoHospitalUser = $this->seedUser('hospital@example.com', 'Demo Hospital', 'hospital');
        $opsHospitalUser = $this->seedUser('ops-hospital@example.com', 'Operations Hospital', 'hospital');
        $metroHospitalUser = $this->seedUser('metro-hospital@example.com', 'Metro Hospital', 'hospital');

        $demoHospital = $this->seedHospital($demoHospitalUser, [
            'hospital_name' => 'City General Hospital',
            'address' => '123 Health Avenue',
            'location' => 'Metro City',
            'contact_person' => 'Demo Hospital',
            'contact_number' => '09170000002',
            'email' => 'hospital@example.com',
            'password' => 'password',
            'status' => 'approved',
        ]);

        $opsHospital = $this->seedHospital($opsHospitalUser, [
            'hospital_name' => 'Operations Hospital',
            'address' => 'Cavite Provincial Health Center',
            'location' => 'Cavite',
            'contact_person' => 'Dr Ops',
            'contact_number' => '09170000020',
            'email' => 'ops-hospital@example.com',
            'password' => 'password',
            'status' => 'approved',
        ]);

        $metroHospital = $this->seedHospital($metroHospitalUser, [
            'hospital_name' => 'Metro Response Hospital',
            'address' => 'Manila Central District',
            'location' => 'Manila',
            'contact_person' => 'Dr Metro',
            'contact_number' => '09170000021',
            'email' => 'metro-hospital@example.com',
            'password' => 'password',
            'status' => 'approved',
        ]);

        $demoDonor = $this->seedDonor('donor@example.com', 'Demo Donor', 'O+', 'Metro City', 95);
        $donorA = $this->seedDonor('analytics-donor-a@example.com', 'A+ Donor Cavite', 'A+', 'Cavite City', 94);
        $donorB = $this->seedDonor('analytics-donor-b@example.com', 'O- Donor Cavite', 'O-', 'Cavite City', 61);
        $donorC = $this->seedDonor('analytics-donor-c@example.com', 'O+ Donor Manila', 'O+', 'Manila', 88);
        $donorD = $this->seedDonor('analytics-donor-d@example.com', 'AB- Donor Laguna', 'AB-', 'Santa Rosa', 79);
        $donorE = $this->seedDonor('analytics-donor-e@example.com', 'B+ Donor Cavite', 'B+', 'Cavite City', 67);

        $requestDemo = $this->seedRequest('BR-DEMO-AN-1000', $demoHospital, [
            'blood_type' => 'O+',
            'component' => 'Whole Blood',
            'units_required' => 2,
            'urgency_level' => 'critical',
            'city' => 'Metro City',
            'province' => 'Metro Manila',
            'status' => 'matching',
            'notifications_sent' => 2,
            'responses_received' => 0,
            'accepted_donors' => 0,
            'fulfilled_units' => 0,
            'matched_donors_count' => 1,
            'distance_limit_km' => 25,
            'is_emergency' => true,
            'reason' => 'Seeder-linked active request for the default demo hospital and donor accounts.',
            'required_on' => now()->addHours(3),
            'expiry_time' => now()->addHours(8),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subMinutes(20),
        ]);

        $requestA = $this->seedRequest('BR-DEMO-AN-1001', $opsHospital, [
            'blood_type' => 'A+',
            'component' => 'PRBC',
            'units_required' => 2,
            'urgency_level' => 'high',
            'city' => 'Cavite City',
            'province' => 'Cavite',
            'status' => 'completed',
            'notifications_sent' => 4,
            'responses_received' => 2,
            'accepted_donors' => 1,
            'fulfilled_units' => 2,
            'matched_donors_count' => 2,
            'distance_limit_km' => 45,
            'required_on' => now()->subDays(2)->addHours(6),
            'expiry_time' => now()->subDays(2)->addHours(10),
            'created_at' => now()->subDays(2)->setTime(18, 12),
            'updated_at' => now()->subDays(2)->setTime(18, 30),
        ]);

        $requestB = $this->seedRequest('BR-DEMO-AN-1002', $opsHospital, [
            'blood_type' => 'O-',
            'component' => 'Whole Blood',
            'units_required' => 3,
            'urgency_level' => 'high',
            'city' => 'Cavite City',
            'province' => 'Cavite',
            'status' => 'matching',
            'notifications_sent' => 5,
            'responses_received' => 0,
            'accepted_donors' => 0,
            'fulfilled_units' => 0,
            'matched_donors_count' => 2,
            'distance_limit_km' => 60,
            'required_on' => now()->addHours(5),
            'expiry_time' => now()->addHours(12),
            'created_at' => now()->subDay()->setTime(19, 5),
            'updated_at' => now()->subDay()->setTime(19, 50),
        ]);

        $requestC = $this->seedRequest('BR-DEMO-AN-1003', $metroHospital, [
            'blood_type' => 'O+',
            'component' => 'Platelets',
            'units_required' => 1,
            'urgency_level' => 'high',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'status' => 'completed',
            'notifications_sent' => 3,
            'responses_received' => 2,
            'accepted_donors' => 1,
            'fulfilled_units' => 1,
            'matched_donors_count' => 2,
            'distance_limit_km' => 35,
            'required_on' => now()->subDays(5)->addHours(3),
            'expiry_time' => now()->subDays(5)->addHours(6),
            'created_at' => now()->subDays(5)->setTime(20, 10),
            'updated_at' => now()->subDays(5)->setTime(20, 35),
        ]);

        $requestD = $this->seedRequest('BR-DEMO-AN-1004', $metroHospital, [
            'blood_type' => 'AB-',
            'component' => 'Plasma',
            'units_required' => 2,
            'urgency_level' => 'medium',
            'city' => 'Santa Rosa',
            'province' => 'Laguna',
            'status' => 'completed',
            'notifications_sent' => 2,
            'responses_received' => 1,
            'accepted_donors' => 1,
            'fulfilled_units' => 2,
            'matched_donors_count' => 1,
            'distance_limit_km' => 50,
            'required_on' => now()->subDays(11)->addHours(4),
            'expiry_time' => now()->subDays(11)->addHours(8),
            'created_at' => now()->subDays(11)->setTime(17, 45),
            'updated_at' => now()->subDays(11)->setTime(18, 20),
        ]);

        $this->seedMatch($requestDemo, $demoDonor, 92.4, 'pending', 1, $requestDemo->created_at->copy()->addSeconds(15));
        $this->seedMatch($requestA, $donorA, 95.2, 'accepted', 1, $requestA->created_at->copy()->addSeconds(18));
        $this->seedMatch($requestA, $donorE, 71.4, 'declined', 2, $requestA->created_at->copy()->addSeconds(43));
        $this->seedMatch($requestC, $donorC, 91.3, 'accepted', 1, $requestC->created_at->copy()->addSeconds(42));
        $this->seedMatch($requestD, $donorD, 83.5, 'accepted', 1, $requestD->created_at->copy()->addSeconds(65));

        $this->seedAlert($requestDemo, $demoDonor, 'sms', 1, $requestDemo->created_at->copy()->addSeconds(10));
        $this->seedResponse($requestA, $donorA, 'accepted', $requestA->created_at->copy()->addSeconds(55));
        $this->seedResponse($requestA, $donorE, 'declined', $requestA->created_at->copy()->addMinutes(2));
        $this->seedResponse($requestC, $donorC, 'accepted', $requestC->created_at->copy()->addSeconds(88));
        $this->seedResponse($requestC, $donorA, 'declined', $requestC->created_at->copy()->addMinutes(3));
        $this->seedResponse($requestD, $donorD, 'accepted', $requestD->created_at->copy()->addSeconds(74));

        $this->seedAlert($requestA, $donorA, 'sms', 1, $requestA->created_at->copy()->addSeconds(8));
        $this->seedAlert($requestA, $donorE, 'push', 1, $requestA->created_at->copy()->addSeconds(14));
        $this->seedAlert($requestB, $donorB, 'sms', 2, $requestB->created_at->copy()->addSeconds(10));
        $this->seedAlert($requestB, $donorA, 'push', 2, $requestB->created_at->copy()->addSeconds(16));
        $this->seedAlert($requestC, $donorC, 'sms', 1, $requestC->created_at->copy()->addSeconds(11));

        $this->seedDelivery($demoDonor->user, $requestDemo, 'sms', 'sent', 1, $requestDemo->created_at->copy()->addSeconds(10));
        $this->seedDelivery($donorA->user, $requestA, 'sms', 'sent', 2, $requestA->created_at->copy()->addSeconds(8));
        $this->seedDelivery($donorE->user, $requestA, 'push', 'failed', 1, $requestA->created_at->copy()->addSeconds(14), 'device_unreachable');
        $this->seedDelivery($donorB->user, $requestB, 'sms', 'failed', 1, $requestB->created_at->copy()->addSeconds(10), 'gateway_timeout');
        $this->seedDelivery($donorC->user, $requestC, 'sms', 'sent', 1, $requestC->created_at->copy()->addSeconds(11));
    }

    private function seedUser(string $email, string $name, string $role): User
    {
        return User::query()->updateOrCreate([
            'email' => $email,
        ], [
            'name' => $name,
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    private function seedHospital(User $user, array $attributes): Hospital
    {
        return Hospital::query()->updateOrCreate([
            'user_id' => $user->id,
        ], $attributes);
    }

    private function seedDonor(string $email, string $name, string $bloodType, string $city, int $reliabilityScore): Donor
    {
        $user = $this->seedUser($email, $name, 'donor');

        return Donor::query()->updateOrCreate([
            'user_id' => $user->id,
        ], [
            'name' => $name,
            'blood_type' => $bloodType,
            'city' => $city,
            'contact_number' => '09'.str_pad((string) $user->id, 9, '0', STR_PAD_LEFT),
            'phone' => '09'.str_pad((string) $user->id, 9, '0', STR_PAD_LEFT),
            'email' => $email,
            'password' => 'password',
            'availability' => true,
            'reliability_score' => $reliabilityScore,
            'last_donation_date' => now()->subDays(70 + $user->id)->toDateString(),
            'privacy_consent_at' => now(),
        ]);
    }

    private function seedRequest(string $caseId, Hospital $hospital, array $attributes): BloodRequest
    {
        $request = BloodRequest::query()->firstOrNew(['case_id' => $caseId]);

        $request->fill([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->hospital_name,
            'blood_type' => $attributes['blood_type'],
            'component' => $attributes['component'],
            'reason' => $attributes['reason'] ?? null,
            'contact_person' => $attributes['contact_person'] ?? $hospital->contact_person,
            'contact_number' => $attributes['contact_number'] ?? $hospital->contact_number,
            'units_required' => $attributes['units_required'],
            'urgency_level' => $attributes['urgency_level'],
            'city' => $attributes['city'],
            'province' => $attributes['province'],
            'required_on' => $attributes['required_on'] ?? null,
            'expiry_time' => $attributes['expiry_time'] ?? null,
            'status' => $attributes['status'],
            'is_emergency' => $attributes['is_emergency'] ?? ($attributes['urgency_level'] === 'critical'),
            'matched_donors_count' => $attributes['matched_donors_count'] ?? 0,
            'notifications_sent' => $attributes['notifications_sent'],
            'responses_received' => $attributes['responses_received'],
            'accepted_donors' => $attributes['accepted_donors'],
            'fulfilled_units' => $attributes['fulfilled_units'],
            'distance_limit_km' => $attributes['distance_limit_km'],
        ]);
        $request->save();

        DB::table('blood_requests')
            ->where('id', $request->id)
            ->update([
                'created_at' => $attributes['created_at'],
                'updated_at' => $attributes['updated_at'],
            ]);

        return $request->fresh();
    }

    private function seedMatch(BloodRequest $request, Donor $donor, float $score, string $responseStatus, int $rank, $timestamp): void
    {
        $match = RequestMatch::query()->updateOrCreate([
            'blood_request_id' => $request->id,
            'donor_id' => $donor->id,
        ], [
            'request_id' => $request->id,
            'score' => $score,
            'response_status' => $responseStatus,
            'rank' => $rank,
        ]);

        DB::table('matches')
            ->where('id', $match->id)
            ->update([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
    }

    private function seedResponse(BloodRequest $request, Donor $donor, string $response, $timestamp): void
    {
        $donorResponse = DonorRequestResponse::query()->updateOrCreate([
            'blood_request_id' => $request->id,
            'donor_id' => $donor->id,
        ], [
            'response' => $response,
            'responded_at' => $timestamp,
        ]);

        DB::table('donor_request_responses')
            ->where('id', $donorResponse->id)
            ->update([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
    }

    private function seedAlert(BloodRequest $request, Donor $donor, string $channel, int $escalationLevel, $timestamp): void
    {
        $alert = DonorAlertLog::query()->updateOrCreate([
            'blood_request_id' => $request->id,
            'donor_id' => $donor->id,
            'channel' => $channel,
        ], [
            'escalation_level' => $escalationLevel,
            'sent_at' => $timestamp,
        ]);

        DB::table('donor_alert_logs')
            ->where('id', $alert->id)
            ->update([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
    }

    private function seedDelivery(User $user, BloodRequest $request, string $channel, string $status, int $attempt, $timestamp, ?string $reason = null): void
    {
        $delivery = NotificationDelivery::query()->updateOrCreate([
            'user_id' => $user->id,
            'type' => 'emergency_blood_request',
            'channel' => $channel,
            'sent_at' => $timestamp,
        ], [
            'status' => $status,
            'response' => [
                'title' => 'Emergency Blood Request',
                'message' => sprintf('Urgent %s request for %s.', $request->blood_type, $request->hospital_name),
                'payload' => [
                    'blood_request_id' => $request->id,
                ],
                'attempt' => $attempt,
                'reason' => $reason,
            ],
        ]);

        DB::table('notifications')
            ->where('id', $delivery->id)
            ->update([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
    }
}