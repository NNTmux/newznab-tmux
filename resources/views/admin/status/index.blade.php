@extends('layouts.admin')

@section('content')
@php
    use App\Enums\ServiceStatusEnum;
    use App\Enums\IncidentImpactEnum;
    use App\Enums\IncidentStatusEnum;

    $serviceBadge = static function (ServiceStatusEnum $s): string {
        $base = 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full';

        return $base.' '.$s->badgeClasses();
    };

    $impactBadge = static function (IncidentImpactEnum $i): string {
        $base = 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full';

        return $base.' '.$i->badgeClasses();
    };

    $incidentStatusBadge = static function (IncidentStatusEnum $st): string {
        $base = 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full';

        return $base.' '.$st->badgeClasses();
    };
@endphp

<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-signal">
        <x-slot:actions>
            <a href="{{ route('admin.status.create') }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800">
                <i aria-hidden="true" class="fas fa-plus mr-2"></i>Create incident
            </a>
            <a href="{{ route('status') }}" target="_blank" rel="noopener" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600">
                <i aria-hidden="true" class="fas fa-external-link-alt mr-2"></i>Public page
            </a>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

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
                            <button type="submit" class="w-full py-2 px-3 text-sm font-medium rounded-lg bg-accent text-white hover:bg-accent-hover dark:hover:bg-accent">
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{{ formatDateTime($incident->started_at) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                            <a href="{{ route('admin.status.edit', $incident) }}" class="text-link dark:text-primary-400 hover:underline">Edit</a>
                            @if($incident->status !== IncidentStatusEnum::Resolved)
                                <form action="{{ route('admin.status.resolve', $incident) }}" method="post" class="inline">
                                    @csrf
                                    <button type="submit" class="text-emerald-600 dark:text-emerald-400 hover:underline">Resolve</button>
                                </form>
                            @endif
                            <form action="{{ route('admin.status.destroy', $incident) }}" method="post" class="inline"
                                  x-data="confirmForm"
                                  data-message="Are you sure you want to delete this incident?"
                                  data-title="Delete Incident"
                                  data-type="danger"
                                  data-confirm-text="Delete"
                                  @submit.prevent="submit()">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger-600 dark:text-danger-400 hover:underline">Delete</button>
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
        <x-admin.pagination :paginator="$incidents" />
    </x-admin.card>
</div>
@endsection
