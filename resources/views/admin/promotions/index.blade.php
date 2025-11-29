@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-gift mr-2"></i>Role Promotions
                </h1>
                <div class="flex gap-2">
                    <a href="{{ route('admin.promotions.statistics') }}" class="px-4 py-2 bg-purple-600 dark:bg-purple-700 text-white rounded-lg hover:bg-purple-700">
                        <i class="fa fa-chart-bar mr-2"></i>View Statistics
                    </a>
                    <a href="{{ route('admin.promotions.create') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                        <i class="fa fa-plus mr-2"></i>Add New Promotion
                    </a>
                </div>
            </div>
        </div>


        <!-- Promotions Table -->
        @if(count($promotions) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Applicable Roles</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Additional Days</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Start Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">End Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
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
                                                <span class="px-2 py-1 text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded">
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
                                    {{ $promotion->start_date ? $promotion->start_date->format('Y-m-d') : 'No limit' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $promotion->end_date ? $promotion->end_date->format('Y-m-d') : 'No limit' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $isExpired = $promotion->end_date && \Carbon\Carbon::now()->gt($promotion->end_date);
                                    @endphp
                                    @if($isExpired)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                            <i class="fa fa-times-circle mr-1"></i>Expired
                                        </span>
                                    @elseif($promotion->is_active && $promotion->isCurrentlyActive())
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                            <i class="fa fa-check mr-1"></i>Active
                                        </span>
                                    @elseif($promotion->is_active)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                            <i class="fa fa-clock mr-1"></i>Scheduled
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                            <i class="fa fa-pause mr-1"></i>Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex gap-2">
                                        <a href="{{ route('admin.promotions.show-statistics', $promotion->id) }}"
                                           class="text-purple-600 dark:text-purple-400 hover:text-purple-900 dark:hover:text-purple-300"
                                           title="View Statistics">
                                            <i class="fa fa-chart-line"></i>
                                        </a>
                                        <a href="{{ route('admin.promotions.toggle', $promotion->id) }}"
                                           class="promotion-toggle-btn {{ $promotion->is_active ? 'text-orange-600 dark:text-orange-400 hover:text-orange-900 dark:hover:text-orange-300' : 'text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300' }}"
                                           title="{{ $promotion->is_active ? 'Deactivate' : 'Activate' }}"
                                           data-promotion-id="{{ $promotion->id }}"
                                           data-promotion-name="{{ $promotion->name }}"
                                           data-promotion-active="{{ $promotion->is_active ? '1' : '0' }}">
                                            <i class="fa fa-{{ $promotion->is_active ? 'pause' : 'play' }}"></i>
                                        </a>
                                        <a href="{{ route('admin.promotions.edit', $promotion->id) }}"
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                           title="Edit">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.promotions.destroy', $promotion->id) }}" method="POST" class="inline promotion-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button"
                                                    class="promotion-delete-btn text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                                    title="Delete"
                                                    data-promotion-id="{{ $promotion->id }}"
                                                    data-promotion-name="{{ $promotion->name }}">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fa fa-gift text-gray-400 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No promotions found</h3>
                <p class="text-gray-500 dark:text-gray-400">Create your first promotion to get started.</p>
            </div>
        @endif
    </div>
</div>
@endsection

