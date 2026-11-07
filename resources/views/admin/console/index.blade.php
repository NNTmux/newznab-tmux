@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-gamepad">
        <x-slot:actions>
            <span class="inline-flex min-h-11 items-center rounded-xl border border-gray-200 bg-white/70 px-4 text-sm font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-800/70 dark:text-gray-200">
                {{ number_format($consoleList->total()) }} games
            </span>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Console Table -->
        <x-admin.data-table>
            <x-slot:head>
                <x-admin.th>Cover</x-admin.th>
                <x-admin.th>Title</x-admin.th>
                <x-admin.th>Platform</x-admin.th>
                <x-admin.th>Publisher</x-admin.th>
                <x-admin.th>Release Date</x-admin.th>
                <x-admin.th>ESRB</x-admin.th>
                <x-admin.th>Actions</x-admin.th>
            </x-slot:head>

                    @forelse($consoleList as $console)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $coverPath = public_path('covers/console/' . $console->id . '.webp');
                                    $legacyCoverPath = public_path('covers/console/' . $console->id . '.jpg');
                                    $hasCover = file_exists($coverPath) || file_exists($legacyCoverPath);
                                @endphp
                                @if($hasCover)
                                    <img width="48" height="64" src="{{ asset('covers/console/' . $console->id . (file_exists($coverPath) ? '.webp' : '.jpg')) }}"
                                         alt="{{ $console->title }}"
                                         class="h-16 w-12 object-cover rounded shadow"
                                         loading="lazy">
                                @else
                                    <div class="h-16 w-12 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                        <i aria-hidden="true" class="fas fa-gamepad text-gray-400 text-sm"></i>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                    {{ $console->title }}
                                </div>
                                @if($console->asin)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        ASIN: {{ $console->asin }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($console->platform)
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                        {{ $console->platform }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                {{ $console->publisher ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                @if($console->releasedate)
                                    {{ date('Y-m-d', $console->releasedate) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($console->esrb)
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                        @if($console->esrb == 'E') bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200
                                        @elseif($console->esrb == 'E10+') bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200
                                        @elseif($console->esrb == 'T') bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200
                                        @elseif($console->esrb == 'M') bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200
                                        @elseif($console->esrb == 'AO') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200
                                        @endif">
                                        {{ $console->esrb }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <a aria-label="Edit" href="{{ url('admin/console-edit?id=' . $console->id) }}"
                                   class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                   title="Edit">
                                    <i aria-hidden="true" class="fas fa-edit"></i>
                                </a>
                                @if($console->url)
                                    <a aria-label="View Source" href="{{ $console->url }}"
                                       target="_blank"
                                       class="text-success-600 dark:text-success-400 hover:text-success-900 dark:hover:text-success-300"
                                       title="View Source">
                                        <i aria-hidden="true" class="fas fa-external-link-alt"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center">
                                    <i aria-hidden="true" class="fas fa-gamepad text-4xl mb-3"></i>
                                    <p class="text-lg">No console games available</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
        </x-admin.data-table>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            {{ $consoleList->links() }}
        </div>
    </x-admin.card>
</div>
@endsection
