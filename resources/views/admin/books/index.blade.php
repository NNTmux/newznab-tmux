@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-book">
        <x-slot:actions>
            <span class="inline-flex min-h-11 items-center rounded-xl border border-gray-200 bg-white/70 px-4 text-sm font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-800/70 dark:text-gray-200">
                {{ number_format($bookList->total()) }} books
            </span>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>
        <x-admin.list-search :action="route('admin.book-list')" :value="$lastSearch ?? ''" placeholder="Search by title or author" label="Search books" />

        <!-- Books Table -->
        <x-admin.data-table>
            <x-slot:head>
                <x-admin.th>Cover</x-admin.th>
                <x-admin.th>Title</x-admin.th>
                <x-admin.th>Author</x-admin.th>
                <x-admin.th>Publisher</x-admin.th>
                <x-admin.th>Published</x-admin.th>
                <x-admin.th>Created</x-admin.th>
                <x-admin.th>Actions</x-admin.th>
            </x-slot:head>

                    @forelse($bookList as $book)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($book->cover == 1)
                                    <img width="48" height="64" src="{{ asset('storage/covers/book/' . $book->id . (file_exists(storage_path('covers/book/' . $book->id . '.webp')) ? '.webp' : '.jpg')) }}"
                                         alt="{{ $book->title }}"
                                         class="h-16 w-12 object-cover rounded shadow"
                                         loading="lazy"
                                         data-fallback-src="{{ asset('images/no-cover.png') }}">
                                @else
                                    <div class="h-16 w-12 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                        <i aria-hidden="true" class="fas fa-book text-gray-400"></i>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                    {{ $book->title ?? 'N/A' }}
                                </div>
                                @if($book->asin)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        ASIN: {{ $book->asin }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                {{ $book->author ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                {{ $book->publisher ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                @if($book->publishdate)
                                    {{ \Carbon\Carbon::parse($book->publishdate)->format('Y-m-d') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $book->created_at ? formatDate($book->created_at) : '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a aria-label="Edit" href="{{ url('admin/book-edit?id=' . $book->id) }}"
                                   class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                   title="Edit">
                                    <i aria-hidden="true" class="fas fa-edit"></i>
                                </a>
                                @if($book->url)
                                    <a aria-label="View Source" href="{{ $book->url }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="ml-3 text-success-600 dark:text-success-400 hover:text-success-900 dark:hover:text-success-300"
                                       title="View Source">
                                        <i aria-hidden="true" class="fas fa-external-link-alt"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                No books found.
                            </td>
                        </tr>
                    @endforelse
        </x-admin.data-table>

        <!-- Pagination -->
        <x-admin.pagination :paginator="$bookList" />
    </x-admin.card>
</div>
@endsection
