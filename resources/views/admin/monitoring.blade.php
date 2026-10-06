@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <x-admin.page-header :title="$title" icon="fas fa-chart-area" subtitle="Host, service and processing metrics collected by Prometheus and rendered by Grafana." />

        @if($enabled)
            <div x-data="adminMonitoring"
                 data-token-url="{{ route('admin.monitoring.token') }}"
                 data-default-tab="{{ array_key_first($dashboards) }}"
                 data-default-range="{{ $defaultRange }}"
                 data-default-refresh="{{ $defaultRefresh }}"
                 class="space-y-4 px-6 pb-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <nav class="flex flex-wrap gap-2" aria-label="Dashboards">
                        @foreach($dashboards as $key => $dashboard)
                            <button type="button"
                                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
                                    :class="tabClass('{{ $key }}')"
                                    :aria-pressed="isActive('{{ $key }}')"
                                    @click="selectTab('{{ $key }}')">
                                <i class="{{ $dashboard['icon'] }}" aria-hidden="true"></i>{{ $dashboard['title'] }}
                            </button>
                        @endforeach
                    </nav>

                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <label class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                            <span>Range</span>
                            <select x-model="range" @change="applyRange()" class="rounded-lg border border-gray-300 bg-white py-1.5 pl-2 pr-8 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                <option value="now-1h">Last hour</option>
                                <option value="now-6h">Last 6 hours</option>
                                <option value="now-24h">Last 24 hours</option>
                                <option value="now-7d">Last 7 days</option>
                                <option value="now-30d">Last 30 days</option>
                            </select>
                        </label>
                        <label class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                            <span>Refresh</span>
                            <select x-model="refresh" @change="applyRange()" class="rounded-lg border border-gray-300 bg-white py-1.5 pl-2 pr-8 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">Off</option>
                                <option value="30s">30s</option>
                                <option value="1m">1m</option>
                                <option value="5m">5m</option>
                            </select>
                        </label>
                        <button type="button" @click="openInGrafana()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-1.5 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                            <i class="fas fa-up-right-from-square" aria-hidden="true"></i>Open in Grafana
                        </button>
                    </div>
                </div>

                <p x-show="error" x-cloak x-text="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200"></p>

                {{-- Alpine's CSP build refuses directives on <iframe>, so visibility lives on a wrapper. --}}
                @foreach($dashboards as $key => $dashboard)
                    <div x-show="isActive('{{ $key }}')" x-cloak>
                        <iframe data-dashboard="{{ $key }}"
                                data-src-base="{{ $dashboard['url'] }}"
                                data-open-url="{{ $dashboard['open_url'] }}"
                                title="Grafana: {{ $dashboard['title'] }} dashboard"
                                referrerpolicy="same-origin"
                                class="h-[75vh] min-h-[700px] w-full rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"></iframe>
                    </div>
                @endforeach
            </div>
        @else
            <x-admin.empty-state icon="fas fa-chart-area"
                                 title="Monitoring is not set up"
                                 :message="$monitoringConfigured ? 'MONITORING_ENABLED is set, but the Grafana JWT private key (GRAFANA_JWT_PRIVATE_KEY_PATH) is missing or unreadable.' : 'Install Prometheus and Grafana to see host, service and processing dashboards here.'">
                <div class="mx-auto max-w-2xl space-y-3 text-left text-sm text-gray-600 dark:text-gray-400">
                    <p><strong>Ubuntu server:</strong> run <code>php artisan monitoring:install</code>, then the <code>sudo scripts/install-monitoring.sh</code> command it prints. An existing node_exporter is detected and reused.</p>
                    <p><strong>Sail:</strong> run <code>php artisan monitoring:install --sail</code>, then <code>make build &amp;&amp; make monitoring-up</code>.</p>
                    <p class="pt-2">
                        <a href="{{ url(config('pulse.path', 'pulse')) }}" class="font-semibold text-blue-600 hover:underline dark:text-blue-400">Pulse</a>
                        and
                        <a href="{{ url(config('horizon.path', 'horizon')) }}" class="font-semibold text-blue-600 hover:underline dark:text-blue-400">Horizon</a>
                        remain available in the meantime.
                    </p>
                </div>
            </x-admin.empty-state>
        @endif
    </x-admin.card>
</div>
@endsection
