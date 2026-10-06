{{-- Grafana panels shown in place of the Chart.js System Resources widget when monitoring is enabled. --}}
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700"
     x-data="grafanaPanels"
     data-token-url="{{ route('admin.monitoring.token') }}">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 flex items-center">
            <i class="fas fa-server mr-2 text-orange-600 dark:text-orange-400"></i>
            System Resources
        </h3>
        <a href="{{ route('admin.monitoring') }}" class="text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400">
            <i class="fas fa-chart-area mr-1" aria-hidden="true"></i>Full monitoring
        </a>
    </div>

    <p x-show="error" x-cloak x-text="error" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200"></p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($grafanaPanels as $panel)
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-2">
                <iframe data-src-base="{{ $panel['url'] }}"
                        title="Grafana: {{ $panel['title'] }}"
                        referrerpolicy="same-origin"
                        loading="lazy"
                        class="h-64 w-full rounded-md"></iframe>
            </div>
        @endforeach
    </div>
</div>
