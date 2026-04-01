@extends('layouts.admin')

@section('content')
@php
    use App\Enums\ServiceStatusEnum;
    use App\Enums\IncidentImpactEnum;
    use App\Enums\IncidentStatusEnum;

    $serviceBadge = static function (ServiceStatusEnum $s): string {
        $base = 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full';
        return $base.' '.match ($s) {
            ServiceStatusEnum::Operational => 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200',
            ServiceStatusEnum::Degraded => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200',
            ServiceStatusEnum::Maintenance => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
            ServiceStatusEnum::PartialOutage => 'bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200',
            ServiceStatusEnum::MajorOutage => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
        };
    };

    $impactBadge = static function (IncidentImpactEnum $i): string {
        $base = 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full';
        return $base.' '.match ($i) {
            IncidentImpactEnum::None => 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200',
            IncidentImpactEnum::Minor => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200',
            IncidentImpactEnum::Major => 'bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200',
            IncidentImpactEnum::Critical => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
        };
    };

    $incidentStatusBadge = static function (IncidentStatusEnum $st): string {
        $base = 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full';
        return $base.' '.match ($st) {
            IncidentStatusEnum::Investigating => 'bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200',
            IncidentStatusEnum::Identified => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
            IncidentStatusEnum::Monitoring => 'bg-cyan-100 dark:bg-cyan-900 text-cyan-800 dark:text-cyan-200',
            IncidentStatusEnum::Resolved => 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200',
        };
    };
@endphp

<div class="space-y-6">
    <x-admin.card>
        <x-admin.page-header :title="$title" icon="fas fa-signal">
            <x-slot:actions>
                <a href="{{ route('admin.status.create') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                    <i class="fas fa-plus mr-2"></i>Create incident
                </a>
                <a href="{{ route('status') }}" target="_blank" rel="noopener" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600">
                    <i class="fas fa-external-link-alt mr-2"></i>Public page
                </a>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="px-6 py-6 border-t border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Service health</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($services as $svc)
                    <div class="p-4 rounded-lg border border-gray-200 bg-gray-50 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $svc->name }}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">Slug: {{ $svc->slug }}</div>
                            </div>
                            <span class="{{ $serviceBadge($svc->status) }} shrink-0">{{ $svc->status->label() }}</span>
                        </div>
                        <form action="{{ route('admin.status.update-service', $svc) }}" method="post" class="space-y-2">
                            @csrf
                            <label for="status-{{ $svc->id }}" class="sr-only">Update status</label>
                            <select id="status-{{ $svc->id }}" name="status" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                @foreach(ServiceStatusEnum::cases() as $opt)
                                    <option value="{{ $opt->value }}" @selected($svc->status === $opt)>{{ $opt->label() }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="w-full py-2 px-3 text-sm font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700 dark:hover:bg-primary-600">
                                Update
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </x-admin.card>

    <x-admin.card>
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Incidents</h2>
        </div>
        <div class="overflow-x-auto">
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th>ID</x-admin.th>
                    <x-admin.th>Title</x-admin.th>
                    <x-admin.th>Services</x-admin.th>
                    <x-admin.th>Impact</x-admin.th>
                    <x-admin.th>Status</x-admin.th>
                    <x-admin.th>Started</x-admin.th>
                    <x-admin.th class="text-right">Actions</x-admin.th>
                </x-slot:head>
                @forelse($incidents as $incident)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $incident->id }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                            {{ Str::limit($incident->title, 48) }}
                            @if($incident->is_auto)
                                <span class="ml-1 px-1.5 py-0.5 inline-flex text-[10px] leading-4 font-semibold rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200">Auto</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $incident->services->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="{{ $impactBadge($incident->impact) }}">{{ $incident->impact->label() }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="{{ $incidentStatusBadge($incident->status) }}">{{ $incident->status->label() }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{{ $incident->started_at->format('Y-m-d H:i') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                            <a href="{{ route('admin.status.edit', $incident) }}" class="text-blue-600 dark:text-blue-400 hover:underline">Edit</a>
                            @if($incident->status !== IncidentStatusEnum::Resolved)
                                <form action="{{ route('admin.status.resolve', $incident) }}" method="post" class="inline">
                                    @csrf
                                    <button type="submit" class="text-emerald-600 dark:text-emerald-400 hover:underline">Resolve</button>
                                </form>
                            @endif
                            <form action="{{ route('admin.status.destroy', $incident) }}" method="post" class="inline" onsubmit="return confirm('Delete this incident?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No incidents yet.</td>
                    </tr>
                @endforelse
            </x-admin.data-table>
        </div>
        @if($incidents->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $incidents->links() }}
            </div>
        @endif
    </x-admin.card>
</div>
@endsection
