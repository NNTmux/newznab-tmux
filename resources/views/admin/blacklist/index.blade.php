@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <x-admin.page-header :title="$title ?? 'Binary Black/White List'" icon="fas fa-ban">
            <x-slot:actions>
                <a href="{{ url('/admin/binaryblacklist-edit') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                    <i class="fas fa-plus mr-2"></i>Add New Blacklist
                </a>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.info-alert>
            Binaries can be prevented from being added to the index if they match a regex in the blacklist.
            They can also be included only if they match a regex (whitelist).
            <strong>Click Edit or on the blacklist to enable/disable.</strong>
        </x-admin.info-alert>

        <!-- Messages -->
        <div id="message" class="px-6"></div>

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
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">Black</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">White</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($bin->msgcol == 1)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">Subject</span>
                            @elseif($bin->msgcol == 2)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300">Poster</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">MessageID</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($bin->status == 1)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Active</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">Disabled</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="max-w-[200px] wrap-break-word">
                                <a href="{{ url('/admin/binaryblacklist-edit?id=' . $bin->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300" title="{{ htmlspecialchars($bin->regex) }}">
                                    <code class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-2 py-1 rounded text-xs break-all">{{ htmlspecialchars($bin->regex) }}</code>
                                </a>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400">
                            @if($bin->last_activity)
                                <span title="{{ $bin->last_activity }}">
                                    <i class="fas fa-clock mr-1 text-gray-400"></i>{{ $bin->last_activity }}
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">Never</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <div class="flex gap-2 justify-center">
                                <a href="{{ url('/admin/binaryblacklist-edit?id=' . $bin->id) }}"
                                   class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                   title="Edit this blacklist">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button"
                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                        data-delete-blacklist="{{ $bin->id }}"
                                        title="Delete this blacklist">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-admin.data-table>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total entries: {{ count($binlist) }}</span>
                    <a href="{{ url('/admin/binaryblacklist-edit') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 text-sm">
                        <i class="fas fa-plus mr-2"></i>Add New Blacklist
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

