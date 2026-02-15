<x-mail::message>
# Monitor {{ $event === 'down' ? 'Down' : 'Up' }}: {{ $payload['monitor']['name'] }}

**URL:** {{ $payload['monitor']['url'] }}

@if ($event === 'down')
**Cause:** {{ $payload['incident']['cause'] }}
**Status Code:** {{ $payload['check']['status_code'] }}
**Response Time:** {{ $payload['check']['response_time_ms'] }}ms
@else
The monitor is back online.

**Incident started at:** {{ $payload['incident']['started_at'] }}
@endif

<x-mail::button :url="$payload['monitor']['url']">
View Monitor
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
