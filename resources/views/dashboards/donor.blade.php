<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donor Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div id="app">

{{-- Route Assistance Section --}}
@if($routeAssistanceData)
<section class="route-assistance">
    <h2>Route Assistance</h2>
    <p>Estimated Arrival: {{ $routeAssistanceData['estimated_arrival_minutes'] }} min</p>
    <p>Traffic: {{ $routeAssistanceData['traffic_condition'] }}</p>
    @if($routeAssistanceData['distance_km'])
        <p>Distance: {{ $routeAssistanceData['distance_km'] }} km</p>
    @endif
    <a href="{{ $routeAssistanceData['navigation_url'] }}" target="_blank" rel="noopener noreferrer">
        Open Navigation in Google Maps
    </a>
    <iframe
        src="{{ $routeAssistanceData['map_embed_url'] }}"
        width="400" height="300"
        style="border:0;"
        allowfullscreen=""
        loading="lazy">
    </iframe>
</section>
@endif

{{-- Achievements Section --}}
@if(!empty($achievements))
<section class="achievements">
    <h2>Achievements &amp; Engagement</h2>

    @php
        $unlockedCount = collect($achievements)->where('unlocked', true)->count();
        $totalCount = count($achievements);
    @endphp

    <p>{{ $unlockedCount }}/{{ $totalCount }} unlocked</p>

    @foreach($achievements as $achievement)
    <div class="achievement {{ $achievement['unlocked'] ? 'unlocked' : 'locked' }}">
        <strong>{{ $achievement['title'] }}</strong>
        <span>Progress: {{ $achievement['progress_percent'] }}%</span>
    </div>
    @endforeach

    @if(!empty($engagementSummary['retention_message']))
    <p>{{ $engagementSummary['retention_message'] }}</p>
    @endif
</section>
@endif

</div>
</body>
</html>
