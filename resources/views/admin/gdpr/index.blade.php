@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                    <i class="fas fa-shield-alt mr-2"></i>{{ $title }}
                </h1>
            </div>
        </div>

        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-800 dark:text-green-200">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-800 dark:text-red-200">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            </div>
        @endif

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
                    <x-button type="submit" icon="fas fa-filter">
                        Filter
                    </x-button>
                    <x-button-link href="{{ route('admin.gdpr-requests.index') }}" variant="muted">Clear</x-button-link>
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
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">{{ ucfirst($gdprRequest->status) }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDateTime($gdprRequest->completed_at) ?: '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ route('admin.gdpr-requests.show', $gdprRequest) }}" class="text-primary-600 dark:text-primary-400 hover:underline">
                                    <i class="fas fa-eye mr-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No GDPR requests found.</td>
                        </tr>
                    @endforelse
        </x-admin.data-table>

        <div class="px-6 py-4">
            {{ $requests->links() }}
        </div>
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

