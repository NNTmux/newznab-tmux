@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-shield-alt" />
    <x-admin.card>


        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="get" action="{{ route('admin.gdpr-requests.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                    <select id="type" name="type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md">
                        <option value="">All</option>
                        @foreach($types as $requestType)
                            <option value="{{ $requestType }}" {{ $type === $requestType ? 'selected' : '' }}>{{ ucfirst($requestType) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md">
                        <option value="">All</option>
                        @foreach($statuses as $requestStatus)
                            <option value="{{ $requestStatus }}" {{ $status === $requestStatus ? 'selected' : '' }}>{{ ucfirst($requestStatus) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-md hover:bg-accent-hover">
                        <i aria-hidden="true" class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="{{ route('admin.gdpr-requests.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Clear</a>
                </div>
            </form>
        </div>

        <x-admin.data-table>
            <x-slot:head>
                <x-admin.th>ID</x-admin.th>
                <x-admin.th>Requested</x-admin.th>
                <x-admin.th>User</x-admin.th>
                <x-admin.th>Type</x-admin.th>
                <x-admin.th>Status</x-admin.th>
                <x-admin.th>Completed</x-admin.th>
                <x-admin.th>Actions</x-admin.th>
            </x-slot:head>

                    @forelse($requests as $gdprRequest)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">#{{ $gdprRequest->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDateTime($gdprRequest->created_at) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                <div>{{ $gdprRequest->requester_username ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $gdprRequest->requester_email ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ ucfirst($gdprRequest->type) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-admin.badge tone="neutral">{{ ucfirst($gdprRequest->status) }}</x-admin.badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDateTime($gdprRequest->completed_at) ?: '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ route('admin.gdpr-requests.show', $gdprRequest) }}" class="text-link dark:text-primary-400 hover:underline">
                                    <i aria-hidden="true" class="fas fa-eye mr-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No GDPR requests found.</td>
                        </tr>
                    @endforelse
        </x-admin.data-table>

        <x-admin.pagination :paginator="$requests" />
    </x-admin.card>

    <x-admin.card class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3">Retention Summary</h2>
        <ul class="list-disc pl-6 text-sm text-gray-700 dark:text-gray-300 space-y-1">
            @foreach($retentionPolicy['retained_records'] as $record)
                <li><strong>{{ $record['table'] }}:</strong> {{ $record['reason'] }} <span class="text-gray-500">({{ str_replace('_', ' ', $record['erasure_action']) }})</span></li>
            @endforeach
        </ul>
    </x-admin.card>
</div>
@endsection
