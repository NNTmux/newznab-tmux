@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-book mr-2"></i>{{ $title }}
                </h1>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Total: {{ $bookList->total() }} books
                </div>
            </div>
        </div>

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
                                    <img src="{{ asset('storage/covers/book/' . $book->id . (file_exists(storage_path('covers/book/' . $book->id . '.webp')) ? '.webp' : '.jpg')) }}"
                                         alt="{{ $book->title }}"
                                         class="h-16 w-12 object-cover rounded shadow"
                                         loading="lazy"
                                         data-fallback-src="{{ asset('images/no-cover.png') }}">
                                @else
                                    <div class="h-16 w-12 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                        <i class="fas fa-book text-gray-400"></i>
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
                                <a href="{{ url('admin/book-edit?id=' . $book->id) }}"
                                   class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if($book->url)
                                    <a href="{{ $book->url }}"
                                       target="_blank"
                                       class="ml-3 text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300"
                                       title="View Source">
                                        <i class="fas fa-external-link-alt"></i>
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
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            {{ $bookList->links() }}
        </div>
    </x-admin.card>
</div>
@endsection
