@extends('emails.email_layout')

@section('title')
    {{ $resolved ? 'Incident Resolved' : 'Incident Detected' }} — {{ $services }}
@endsection

@section('site_name', $site)

@section('content')
    @if($resolved)
        <div class="alert-box alert-success">
            <strong>✅ Resolved</strong> — The following incident has been automatically resolved.
        </div>
    @else
        <div class="alert-box alert-{{ $incident->impact->value === 'critical' ? 'danger' : ($incident->impact->value === 'major' ? 'warning' : 'info') }}">
            <strong>⚠️ {{ ucfirst($incident->impact->value) }} Impact</strong> — An automated health check has detected a service issue.
        </div>
    @endif

    <table class="status-table">
        <tr>
            <td class="status-label">Incident</td>
            <td>{{ $incident->title }}</td>
        </tr>
        <tr>
            <td class="status-label">Affected Services</td>
            <td>{{ $services }}</td>
        </tr>
        <tr>
            <td class="status-label">Impact</td>
            <td>{{ ucfirst($incident->impact->value) }}</td>
        </tr>
        <tr>
            <td class="status-label">Status</td>
            <td>{{ ucfirst($incident->status->value) }}</td>
        </tr>
        <tr>
            <td class="status-label">Started</td>
            <td>{{ $incident->started_at->format('M j, Y g:i A T') }}</td>
        </tr>
        @if($resolved && $incident->resolved_at)
        <tr>
            <td class="status-label">Resolved</td>
            <td>{{ $incident->resolved_at->format('M j, Y g:i A T') }}</td>
        </tr>
        <tr>
            <td class="status-label">Duration</td>
            <td>{{ $incident->started_at->diffForHumans($incident->resolved_at, true) }}</td>
        </tr>
        @endif
    </table>

    @if($incident->description)
        <div class="info-box">
            <strong>Details:</strong><br>
            {!! nl2br(e($incident->description)) !!}
        </div>
    @endif

    <p style="text-align: center; margin-top: 24px;">
        <a href="{{ $statusUrl }}" class="button">View Status Dashboard</a>
    </p>

    <div class="signature">
        <p>— {{ $site }} Monitoring</p>
    </div>
@endsection
