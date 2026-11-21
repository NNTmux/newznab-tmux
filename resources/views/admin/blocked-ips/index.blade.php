@extends('layouts.admin')

@section('title', $title ?? 'Blocked IPs')

@use('App\Enums\IpBlockSource')

@section('content')
@php
    $field = 'w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-500 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-400 dark:focus:border-primary-400 dark:focus:ring-primary-400';
    $fieldLabel = 'mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300';
    $link = 'text-link hover:underline dark:text-primary-400';
    $durations = ['24' => '24 hours', '168' => '7 days', '720' => '30 days', '' => 'Until lifted'];
@endphp
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-ban"
                         subtitle="Addresses refused by the whole site, before the login page is served." />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.stat-card label="Blocked now" :value="number_format($activeCount)" icon="fas fa-ban" :color="$activeCount > 0 ? 'danger' : 'success'"
                           footer="Addresses currently refused" />
        <x-admin.stat-card label="Automatic rule" :value="$threshold.' accounts / '.$windowHours.'h'" icon="fas fa-user-secret" color="primary"
                           footer="Different non-existent accounts tried from one address or device" />
        <x-admin.stat-card label="First block" :value="$blockHours.'h'" icon="fas fa-hourglass-half" color="primary"
                           footer="Doubles each time the same address is blocked again" />
    </div>

    <x-admin.card>
        @unless($enforcementEnabled)
            <x-admin.info-alert>
                Blocking is turned off (<code>IP_BLOCKING_ENABLED=false</code>), so the addresses below are not being refused.
            </x-admin.info-alert>
        @endunless
        @unless($autoBlockEnabled)
            <x-admin.info-alert>
                Automatic blocking is turned off (it needs <code>LOGIN_ABUSE_AUTO_BLOCK=true</code> and <code>SUSPICIOUS_LOGIN_ENABLED=true</code>). Addresses can still be blocked by hand.
            </x-admin.info-alert>
        @endunless

        <x-admin.filter-panel>
            <form method="post" action="{{ route('admin.blocked-ips.store') }}">
                @csrf
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label for="ip" class="{{ $fieldLabel }}">IP address</label>
                        <input type="text" id="ip" name="ip" value="{{ old('ip') }}" required maxlength="45" placeholder="203.0.113.10" class="{{ $field }} font-mono">
                    </div>
                    <div>
                        <label for="hours" class="{{ $fieldLabel }}">Block for</label>
                        <select id="hours" name="hours" class="{{ $field }}">
                            @foreach($durations as $hours => $label)
                                <option value="{{ $hours }}" @selected((string) old('hours', '24') === (string) $hours)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="reason" class="{{ $fieldLabel }}">Reason (optional)</label>
                        <input type="text" id="reason" name="reason" value="{{ old('reason') }}" maxlength="255" class="{{ $field }}">
                    </div>
                </div>
                <div class="mt-4">
                    <x-admin.button type="submit" tone="danger" icon="fas fa-ban">Block address</x-admin.button>
                </div>
            </form>
        </x-admin.filter-panel>

        @if($errors->any())
            <div class="mx-6 mt-4 rounded-lg border border-danger-200 bg-danger-50 p-4 dark:border-danger-700 dark:bg-danger-900">
                <p class="text-danger-800 dark:text-danger-200"><i aria-hidden="true" class="fas fa-exclamation-circle mr-2"></i>{{ $errors->first() }}</p>
            </div>
        @endif

        @if($blocks->isNotEmpty())
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th>IP address</x-admin.th>
                    <x-admin.th>Status</x-admin.th>
                    <x-admin.th>Blocked</x-admin.th>
                    <x-admin.th>Ends</x-admin.th>
                    <x-admin.th>Source</x-admin.th>
                    <x-admin.th>Reason</x-admin.th>
                    <x-admin.th>Actions</x-admin.th>
                </x-slot:head>
                @foreach($blocks as $block)
                    <tr class="align-top hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="whitespace-nowrap">
                            <a href="{{ route('admin.login-attempts.index', ['ip' => $block->ip_address]) }}" class="font-mono {{ $link }}" title="Show login attempts from this address">{{ $block->ip_address }}</a>
                        </td>
                        <td class="whitespace-nowrap">
                            @if($block->isActive())
                                <x-admin.badge tone="danger" icon="fas fa-ban">Blocked</x-admin.badge>
                            @else
                                <x-admin.badge tone="neutral">Ended</x-admin.badge>
                            @endif
                            @if($block->block_count > 1)
                                <div class="mt-1 text-xs text-fg-muted">Blocked {{ $block->block_count }} times</div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-gray-900 dark:text-gray-100">
                            {{ formatDateTimeSeconds($block->blocked_at) }}
                            <div class="text-xs text-fg-muted">{{ $block->blocked_at?->diffForHumans() }}</div>
                        </td>
                        <td class="whitespace-nowrap text-gray-900 dark:text-gray-100">
                            @if($block->expires_at === null)
                                Until lifted
                            @else
                                {{ formatDateTimeSeconds($block->expires_at) }}
                                <div class="text-xs text-fg-muted">{{ $block->expires_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            <x-admin.badge :tone="$block->source === IpBlockSource::Auto ? 'warning' : 'primary'">{{ $block->source->label() }}</x-admin.badge>
                        </td>
                        <td class="max-w-sm text-gray-700 dark:text-gray-300">
                            @if(filled($block->reason))
                                {{ $block->reason }}
                            @else
                                <span class="text-fg-muted">&mdash;</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if($block->isActive())
                                <form method="post" action="{{ route('admin.blocked-ips.destroy', $block) }}" class="inline"
                                      x-data="confirmForm"
                                      data-message="Lift the block on {{ $block->ip_address }}? The address can reach the site again straight away."
                                      data-title="Lift block"
                                      data-type="warning"
                                      data-confirm-text="Lift block"
                                      @submit.prevent="submit()">
                                    @csrf
                                    @method('DELETE')
                                    <x-admin.button type="submit" tone="gray" icon="fas fa-unlock">Lift</x-admin.button>
                                </form>
                            @else
                                <span class="text-fg-muted">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-admin.data-table>
            <x-admin.pagination :paginator="$blocks" />
        @else
            <x-admin.empty-state icon="fas fa-ban" title="No addresses have been blocked"
                                 message="Addresses appear here when they are blocked automatically or by hand." />
        @endif
    </x-admin.card>
</div>
@endsection
