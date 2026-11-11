@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title ?? 'Binary Black/White List'" icon="fas fa-ban">
        <x-slot:actions>
            <a href="{{ url('/admin/binaryblacklist-edit') }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800">
                <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add New Blacklist
            </a>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <x-admin.info-alert>
            Binaries can be prevented from being added to the index if they match a regex in the blacklist.
            They can also be included only if they match a regex (whitelist).
            <strong>Click Edit or on the blacklist to enable/disable.</strong>
        </x-admin.info-alert>

        <x-admin.list-search :action="route('admin.binaryblacklist-list')" :value="$lastSearch ?? ''" placeholder="Search by group, regex or description" label="Search the blacklist" />

        @if(count($binlist) > 0)
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th align="center" width="16">ID</x-admin.th>
                    <x-admin.th>Group</x-admin.th>
                    <x-admin.th>Description</x-admin.th>
                    <x-admin.th align="center" width="20">Type</x-admin.th>
                    <x-admin.th align="center" width="24">Field</x-admin.th>
                    <x-admin.th align="center" width="20">Status</x-admin.th>
                    <x-admin.th>Regex</x-admin.th>
                    <x-admin.th align="center" width="36">Last Activity</x-admin.th>
                    <x-admin.th align="center" width="28">Actions</x-admin.th>
                </x-slot:head>

                @foreach($binlist as $bin)
                    <tr id="row-{{ $bin->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $bin->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <span class="inline-block truncate max-w-[150px]" title="{{ $bin->groupname }}">
                                {{ str_replace('alt.binaries', 'a.b', $bin->groupname) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            <span class="inline-block truncate max-w-[180px]" title="{{ $bin->description }}">
                                {{ $bin->description }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($bin->optype == 1)
                                <x-admin.badge tone="danger">Black</x-admin.badge>
                            @else
                                <x-admin.badge tone="success">White</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($bin->msgcol == 1)
                                <x-admin.badge tone="primary">Subject</x-admin.badge>
                            @elseif($bin->msgcol == 2)
                                <x-admin.badge tone="warning">Poster</x-admin.badge>
                            @else
                                <x-admin.badge tone="neutral">MessageID</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($bin->status == 1)
                                <x-admin.badge tone="success">Active</x-admin.badge>
                            @else
                                <x-admin.badge tone="danger">Disabled</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="max-w-[200px] wrap-break-word">
                                <a href="{{ url('/admin/binaryblacklist-edit?id=' . $bin->id) }}" class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300" title="{{ htmlspecialchars($bin->regex) }}">
                                    <code class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-2 py-1 rounded text-xs break-all">{{ htmlspecialchars($bin->regex) }}</code>
                                </a>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400">
                            @if($bin->last_activity)
                                <span title="{{ $bin->last_activity }}">
                                    <i aria-hidden="true" class="fas fa-clock mr-1 text-gray-400"></i>{{ $bin->last_activity }}
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">Never</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <div class="flex gap-2 justify-center">
                                <a aria-label="Edit this blacklist" href="{{ url('/admin/binaryblacklist-edit?id=' . $bin->id) }}"
                                   class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                   title="Edit this blacklist">
                                    <i aria-hidden="true" class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.binaryblacklist-delete', $bin->id) }}" method="post" class="inline"
                                      x-data="confirmForm"
                                      data-message="Are you sure you want to delete this blacklist?"
                                      data-title="Delete Blacklist"
                                      data-type="danger"
                                      data-confirm-text="Delete"
                                      @submit.prevent="submit()">
                                    @csrf
                                    <button aria-label="Delete this blacklist" type="submit" title="Delete this blacklist"
                                            class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300">
                                        <i aria-hidden="true" class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-admin.data-table>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total entries: {{ count($binlist) }}</span>
                    <a href="{{ url('/admin/binaryblacklist-edit') }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800 text-sm">
                        <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add New Blacklist
                    </a>
                </div>
            </div>
        @else
            <x-empty-state
                icon="fas fa-ban"
                title="No blacklist entries found"
                message="Get started by adding your first blacklist entry."
                :actionUrl="url('/admin/binaryblacklist-edit')"
                actionLabel="Add New Blacklist"
                actionIcon="fas fa-plus"
            />
        @endif
    </x-admin.card>
</div>
@endsection

