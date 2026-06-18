<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $payload['title'] ?? 'Notification' }}</title>
  </head>
  <body>
    <h2>{{ $payload['title'] ?? 'Notification' }}</h2>
    <p>{{ $payload['message'] ?? 'This is a test email from SmartBlood.' }}</p>
    @if(!empty($payload['link']))
      <p><a href="{{ $payload['link'] }}">View details</a></p>
    @endif
    <hr>
    <small>Sent by SmartBlood</small>
  </body>
</html>
