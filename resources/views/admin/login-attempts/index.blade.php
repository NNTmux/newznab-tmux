@extends('layouts.admin')

@section('title', $title ?? 'Login Attempts')

@use('App\Models\User')

@section('content')
@php
    $listUrl = route('admin.login-attempts.index');
    $field = 'w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-500 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-400 dark:focus:border-primary-400 dark:focus:ring-primary-400';
    $fieldLabel = 'mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300';
    $link = 'text-link hover:underline dark:text-primary-400';
    $riskTones = ['low' => 'neutral', 'medium' => 'warning', 'high' => 'danger'];
    $riskLabels = ['suspicious' => 'Suspicious (medium or high)'];
    $hasFilters = array_filter($filters, static fn (string $value): bool => $value !== '') !== [];
@endphp
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-right-to-bracket" subtitle="Successful and failed sign-ins recorded for user accounts." />

    {{-- Last 24 hours, regardless of the filters below --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.stat-card label="Attempts (24h)" :value="number_format($summary['total'])" icon="fas fa-right-to-bracket" color="primary"
                           footer="Every sign-in attempt in the last 24 hours" />
        <x-admin.stat-card label="Failed (24h)" :value="number_format($summary['failed'])" icon="fas fa-circle-xmark" :color="$summary['failed'] > 0 ? 'danger' : 'success'"
                           footer="Wrong password or unknown account" />
        <x-admin.stat-card label="Suspicious (24h)" :value="number_format($summary['suspicious'])" icon="fas fa-triangle-exclamation" :color="$summary['suspicious'] > 0 ? 'warning' : 'success'"
                           footer="Sign-ins rated medium or high risk" />
    </div>

    <x-admin.card>
        @unless($recordingEnabled)
            <x-admin.info-alert>
                Login attempt recording is turned off (<code>SUSPICIOUS_LOGIN_ENABLED=false</code>), so no new attempts are being added.
            </x-admin.info-alert>
        @endunless

        <x-admin.filter-panel>
            <form method="get" action="{{ $listUrl }}">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label for="status" class="{{ $fieldLabel }}">Result</label>
                        <select id="status" name="status" class="{{ $field }}">
                            <option value="">All results</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="user" class="{{ $fieldLabel }}">User</label>
                        <input type="text" id="user" name="user" value="{{ $filters['user'] }}" placeholder="Username, email or submitted login" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="user_id" class="{{ $fieldLabel }}">User ID</label>
                        <input type="number" id="user_id" name="user_id" min="1" value="{{ $filters['user_id'] }}" placeholder="Exact account ID" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="ip" class="{{ $fieldLabel }}">IP address</label>
                        <input type="text" id="ip" name="ip" value="{{ $filters['ip'] }}" placeholder="Full address or prefix, e.g. 203.0.113." class="{{ $field }}">
                    </div>
                    <div>
                        <label for="risk" class="{{ $fieldLabel }}">Risk</label>
                        <select id="risk" name="risk" class="{{ $field }}">
                            <option value="">Any risk</option>
                            @foreach($riskOptions as $risk)
                                <option value="{{ $risk }}" @selected($filters['risk'] === $risk)>{{ $riskLabels[$risk] ?? ucfirst($risk) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="date_from" class="{{ $fieldLabel }}">From</label>
                        <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="date_to" class="{{ $fieldLabel }}">To</label>
                        <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}" class="{{ $field }}">
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <x-admin.button type="submit" icon="fas fa-search">Filter</x-admin.button>
                    <x-admin.button :href="$listUrl" tone="gray" icon="fas fa-times">Clear</x-admin.button>
                </div>
            </form>
        </x-admin.filter-panel>

        @if($errors->any())
            <div class="mx-6 mt-4 rounded-lg border border-danger-200 bg-danger-50 p-4 dark:border-danger-700 dark:bg-danger-900">
                <p class="text-danger-800 dark:text-danger-200"><i aria-hidden="true" class="fas fa-exclamation-circle mr-2"></i>{{ $errors->first() }}</p>
            </div>
        @endif

        @if($attempts->isNotEmpty())
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th>Time</x-admin.th>
                    <x-admin.th>User</x-admin.th>
                    <x-admin.th>Result</x-admin.th>
                    <x-admin.th>IP address</x-admin.th>
                    <x-admin.th>Location</x-admin.th>
                    <x-admin.th>Risk</x-admin.th>
                    <x-admin.th>User agent</x-admin.th>
                </x-slot:head>
                @foreach($attempts as $attempt)
                    @php
                        $account = $attempt->authenticatable;
                        $riskLevel = $attempt->risk_level?->value;
                        $reasons = $attempt->reasons ?? [];
                    @endphp
                    <tr class="align-top hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="whitespace-nowrap text-gray-900 dark:text-gray-100">
                            {{ formatDateTimeSeconds($attempt->created_at) }}
                            <div class="text-xs text-fg-muted">{{ $attempt->created_at?->diffForHumans() }}</div>
                        </td>
                        <td>
                            @if($account instanceof User)
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.login-attempts.index', ['user_id' => $account->id]) }}" class="font-medium {{ $link }}" title="Show this user's login attempts">{{ $account->username }}</a>
                                    @if($account->trashed())
                                        <x-admin.badge tone="neutral">Deleted account</x-admin.badge>
                                    @else
                                        <a href="{{ route('admin.user-edit', ['id' => $account->id]) }}" class="text-fg-muted hover:text-link" aria-label="Edit {{ $account->username }}" title="Edit user"><i aria-hidden="true" class="fas fa-user-pen"></i></a>
                                    @endif
                                </div>
                                <div class="text-xs text-fg-muted">{{ $account->email }}</div>
                            @elseif($attempt->authenticatable_id !== null)
                                <a href="{{ route('admin.login-attempts.index', ['user_id' => $attempt->authenticatable_id]) }}" class="font-medium {{ $link }}">User #{{ $attempt->authenticatable_id }}</a>
                                <div class="text-xs text-fg-muted">Account no longer exists</div>
                            @elseif(filled($attempt->identifier))
                                <span class="break-all font-medium text-gray-900 dark:text-gray-100">{{ $attempt->identifier }}</span>
                                <div class="text-xs text-fg-muted">No matching account</div>
                            @else
                                <span class="text-fg-muted">&mdash;</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if($attempt->blocked)
                                <x-admin.badge tone="danger" icon="fas fa-ban">Blocked</x-admin.badge>
                            @elseif($attempt->successful)
                                <x-admin.badge tone="success" icon="fas fa-check">Successful</x-admin.badge>
                            @else
                                <x-admin.badge tone="danger" icon="fas fa-xmark">Failed</x-admin.badge>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if(filled($attempt->ip_address))
                                <a href="{{ route('admin.login-attempts.index', ['ip' => $attempt->ip_address]) }}" class="font-mono {{ $link }}" title="Show attempts from this address">{{ $attempt->ip_address }}</a>
                            @else
                                <span class="text-fg-muted">&mdash;</span>
                            @endif
                        </td>
                        <td class="text-gray-700 dark:text-gray-300">
                            @if(filled($attempt->country_code))
                                {{ $attempt->country_code }}@if(filled($attempt->city)), {{ $attempt->city }}@endif
                            @else
                                <span class="text-fg-muted">&mdash;</span>
                            @endif
                        </td>
                        <td>
                            @if($riskLevel !== null)
                                <x-admin.badge :tone="$riskTones[$riskLevel] ?? 'neutral'">{{ ucfirst($riskLevel) }}@if($attempt->risk_score !== null) &middot; {{ $attempt->risk_score }}@endif</x-admin.badge>
                                @if($reasons !== [])
                                    <details class="mt-1 text-xs text-fg-muted">
                                        <summary class="cursor-pointer">{{ count($reasons) }} {{ Str::plural('reason', count($reasons)) }}</summary>
                                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                            @foreach($reasons as $reason)
                                                <li>{{ $reason['reason'] ?? '' }} (+{{ $reason['score'] ?? 0 }})</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            @else
                                <span class="text-fg-muted">&mdash;</span>
                            @endif
                        </td>
                        <td class="max-w-xs text-gray-700 dark:text-gray-300">
                            @if(filled($attempt->user_agent))
                                <span class="block truncate" title="{{ $attempt->user_agent }}">{{ Str::limit($attempt->user_agent, 80) }}</span>
                            @else
                                <span class="text-fg-muted">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-admin.data-table>
            <x-admin.pagination :paginator="$attempts" />
        @else
            <x-admin.empty-state icon="fas fa-right-to-bracket" title="No login attempts found"
                                 :message="$hasFilters ? 'Try adjusting or clearing the filters.' : 'Sign-ins appear here as they are recorded.'" />
        @endif
    </x-admin.card>
</div>
@endsection
