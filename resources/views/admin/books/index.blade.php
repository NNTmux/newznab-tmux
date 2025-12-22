@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
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
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cover</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Author</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Publisher</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Published</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($bookList as $book)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($book->cover == 1)
                                    <img src="{{ asset('storage/covers/book/' . $book->id . '.jpg') }}"
                                         alt="{{ $book->title }}"
                                         class="h-16 w-12 object-cover rounded shadow"
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
                                {{ $book->created_at ? $book->created_at->format('Y-m-d') : '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ url('admin/book-edit?id=' . $book->id) }}"
                                   class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if($book->url)
                                    <a href="{{ $book->url }}"
                                       target="_blank"
                                       class="ml-3 text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300"
                                       title="View on Amazon">
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
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            {{ $bookList->links() }}
        </div>
    </div>
</div>
@endsection

