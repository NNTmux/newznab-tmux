@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header title="Role Promotions" icon="fas fa-gift">
        <x-slot:actions>
            <x-admin.button :href="route('admin.promotions.statistics')" tone="gray" icon="fas fa-chart-bar">View Statistics</x-admin.button>
            <x-admin.button :href="route('admin.promotions.create')" icon="fas fa-plus">Add New Promotion</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Promotions Table -->
        @if(count($promotions) > 0)
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th>Name</x-admin.th>
                    <x-admin.th>Applicable Roles</x-admin.th>
                    <x-admin.th>Additional Days</x-admin.th>
                    <x-admin.th>Start Date</x-admin.th>
                    <x-admin.th>End Date</x-admin.th>
                    <x-admin.th>Status</x-admin.th>
                    <x-admin.th>Actions</x-admin.th>
                </x-slot:head>

                        @foreach($promotions as $promotion)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $promotion->name }}</div>
                                    @if($promotion->description)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($promotion->description, 50) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    @php
                                        $roles = $promotion->getApplicableRolesModels();
                                    @endphp
                                    @if($roles->isEmpty())
                                        <span class="italic">All Custom Roles</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($roles as $role)
                                                <span class="px-2 py-1 text-xs bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200 rounded">
                                                    {{ $role->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <span class="font-semibold">{{ $promotion->additional_days }}</span> days
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $promotion->start_date ? formatDate($promotion->start_date) : 'No limit' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $promotion->end_date ? formatDate($promotion->end_date) : 'No limit' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $isExpired = $promotion->end_date && \Carbon\Carbon::now()->gt($promotion->end_date);
                                    @endphp
                                    @if($isExpired)
                                        <x-admin.badge tone="danger" icon="fas fa-times-circle">Expired</x-admin.badge>
                                    @elseif($promotion->is_active && $promotion->isCurrentlyActive())
                                        <x-admin.badge tone="success" icon="fas fa-check">Active</x-admin.badge>
                                    @elseif($promotion->is_active)
                                        <x-admin.badge tone="warning" icon="fas fa-clock">Scheduled</x-admin.badge>
                                    @else
                                        <x-admin.badge tone="neutral" icon="fas fa-pause">Inactive</x-admin.badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex gap-2">
                                        <a aria-label="View Statistics" href="{{ route('admin.promotions.show-statistics', $promotion->id) }}"
                                           class="text-purple-600 dark:text-purple-400 hover:text-purple-900 dark:hover:text-purple-300"
                                           title="View Statistics">
                                            <i aria-hidden="true" class="fas fa-chart-line"></i>
                                        </a>
                                        <form action="{{ route('admin.promotions.toggle', $promotion->id) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button aria-label="{{ $promotion->is_active ? 'Deactivate' : 'Activate' }}" type="submit"
                                                class="{{ $promotion->is_active ? 'text-orange-600 dark:text-orange-400 hover:text-orange-900 dark:hover:text-orange-300' : 'text-success-600 dark:text-success-400 hover:text-success-900 dark:hover:text-success-300' }}"
                                                title="{{ $promotion->is_active ? 'Deactivate' : 'Activate' }}"
                                                data-confirm="Are you sure you want to {{ $promotion->is_active ? 'deactivate' : 'activate' }} the promotion &quot;{{ $promotion->name }}&quot;?" data-confirm-type="warning">
                                                <i aria-hidden="true" class="fas fa-{{ $promotion->is_active ? 'pause' : 'play' }}"></i>
                                            </button>
                                        </form>
                                        <a aria-label="Edit" href="{{ route('admin.promotions.edit', $promotion->id) }}"
                                           class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                           title="Edit">
                                            <i aria-hidden="true" class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.promotions.destroy', $promotion->id) }}" method="POST" class="inline"
                                              x-data="confirmForm"
                                              data-message="Are you sure you want to delete the promotion &quot;{{ $promotion->name }}&quot;? This action cannot be undone."
                                              data-title="Delete Promotion"
                                              data-type="danger"
                                              data-confirm-text="Delete"
                                              @submit.prevent="submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button aria-label="Delete" type="submit"
                                                    class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300"
                                                    title="Delete">
                                                <i aria-hidden="true" class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
            </x-admin.data-table>
        @else
            <x-admin.empty-state icon="fas fa-gift" title="No promotions found" message="Create your first promotion to get started." />
        @endif
    </x-admin.card>
</div>
@endsection
