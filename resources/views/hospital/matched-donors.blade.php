<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Matched Donors – {{ $bloodRequest->hospital_name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div id="app">
<section class="matched-donors">
    <h1>Matched Donors for Blood Request #{{ $bloodRequest->id }}</h1>
    <p>Blood Type: {{ $bloodRequest->blood_type }} | City: {{ $bloodRequest->city }}</p>

    @forelse($matchData as $match)
    <div class="donor-card">
        <h3>{{ $match['donor']->name }}</h3>
        <p>Blood Type: {{ $match['donor']->blood_type }}</p>
        <p>City: {{ $match['donor']->city }}</p>
        <p>Contact: {{ $match['donor']->contact_number }}</p>
        <p>Email: {{ $match['donor']->email }}</p>
        <p>Rank: {{ $match['rank'] }} | Score: {{ $match['score'] }}</p>
        <p>Coordination: {{ $match['coordination_label'] }}</p>

        @if($match['distance_km'] !== null)
        <p>Distance: {{ $match['distance_km'] }} km</p>
        @endif

        <div class="travel-info">
            <p>Traffic Condition: {{ $match['travel']['traffic_condition'] }}</p>
            <p>Estimated Travel: {{ $match['travel']['estimated_travel_minutes'] }} min</p>
        </div>

        <p>Response: {{ $match['response_status'] }}</p>
    </div>
    @empty
    <p>No matched donors found.</p>
    @endforelse

    <div class="actions">
        <form method="POST" action="{{ route('hospital.requests.update-status', $bloodRequest) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="completed">
            <button type="submit">Confirm Accepted Donor</button>
        </form>

        <form method="POST" action="{{ route('hospital.requests.update-status', $bloodRequest) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="cancelled">
            <button type="submit">Cancel Request</button>
        </form>
    </div>
</section>
</div>
</body>
</html>
