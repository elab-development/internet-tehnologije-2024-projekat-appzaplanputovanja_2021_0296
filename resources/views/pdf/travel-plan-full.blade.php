<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Travel plan #{{ $plan->id }}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#111; margin: 24px; }
  h1,h2,h3 { margin: 0 0 8px; }
  .muted { color:#666; }
  .grid { width:100%; border-collapse: collapse; margin-top:8px; }
  .grid th, .grid td { border:1px solid #ddd; padding:6px 8px; vertical-align: top; }
  .grid th { background:#f6f6f6; text-align:left; }
  .right { text-align:right; }
  .mb6 { margin-bottom:6px; }
  .mb12 { margin-bottom:12px; }
  .pill { display:inline-block; padding:2px 6px; border:1px solid #ddd; border-radius:10px; margin-right:4px; margin-bottom:2px;}
</style>
</head>
<body>
  <h1>Travel Plan #{{ $plan->id }}</h1>
  <div class="mb12 muted">
    Created by: {{ optional($plan->user)->name ?? '—' }} |
    Created at: {{ $plan->created_at?->format('Y-m-d H:i') }}
  </div>

  <h2>Basic Information</h2>
  <table class="grid">
    <tr><th>Start location</th><td>{{ $plan->start_location }}</td></tr>
    <tr><th>Destination</th><td>{{ $plan->destination }}</td></tr>
    <tr>
      <th>Dates</th>
      <td>{{ \Carbon\Carbon::parse($plan->start_date)->format('Y-m-d') }}
          → {{ \Carbon\Carbon::parse($plan->end_date)->format('Y-m-d') }}</td>
    </tr>
    <tr><th>Number of passengers</th><td>{{ $plan->passenger_count }}</td></tr>
    <tr><th>Budget</th><td class="right">{{ number_format($plan->budget, 2) }}</td></tr>
    <tr><th>Total cost</th><td class="right">{{ number_format($plan->total_cost, 2) }}</td></tr>
    <tr><th>Transport mode</th><td>{{ $plan->transport_mode }}</td></tr>
    <tr><th>Accommodation class</th><td>{{ $plan->accommodation_class }}</td></tr>
    <tr>
      <th>Preferences</th>
      <td>
        @forelse(($plan->preferences ?? []) as $p)
          <span class="pill">{{ $p }}</span>
        @empty — @endforelse
      </td>
    </tr>
  </table>

  <h2 class="mb6">Plan Items</h2>
  <table class="grid">
    <thead>
      <tr>
        <th>#</th>
        <th>Name</th>
        <th>Type</th>
        <th>From</th>
        <th>To</th>
        <th class="right">Duration (min)</th>
        <th class="right">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $i => $item)
        @php
          $from = \Carbon\Carbon::parse($item->time_from);
          $to   = \Carbon\Carbon::parse($item->time_to);
          $dur  = $from->diffInMinutes($to);
        @endphp
        <tr>
          <td>{{ $i+1 }}</td>
          <td>{{ $item->name }}</td>
          <td>{{ $item->activity->type ?? '' }}</td>
          <td>{{ $from->format('Y-m-d H:i') }}</td>
          <td>{{ $to->format('Y-m-d H:i') }}</td>
          <td class="right">{{ $dur }}</td>
          <td class="right">{{ number_format($item->amount, 2) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
