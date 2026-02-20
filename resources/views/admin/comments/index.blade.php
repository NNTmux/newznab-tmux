@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Flash Messages -->
    @if(session('success'))
        <div x-data="dismissible" x-show="show" class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-3 text-xl"></i>
                <p class="text-sm text-green-800 dark:text-green-200 font-medium">{{ session('success') }}</p>
            </div>
            <button x-on:click="dismiss" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div x-data="dismissible" x-show="show" class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mr-3 text-xl"></i>
                <p class="text-sm text-red-800 dark:text-red-200 font-medium">{{ session('error') }}</p>
            </div>
            <button x-on:click="dismiss" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-comments mr-2"></i>{{ $title }}
                </h1>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Total: {{ $commentsList->total() }} comments
                </div>
            </div>
        </div>

        <!-- Comments Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Comment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Release</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($commentsList as $comment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200 font-mono">
                                #{{ $comment->id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                            {{ $comment->username }}
                                        </div>
                                        @if($comment->host)
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $comment->host }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-gray-200 max-w-md">
                                    <div class="line-clamp-2" title="{{ $comment->text }}">
                                        {{ Str::limit($comment->text, 150) }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($comment->guid)
                                    <a href="{{ url('details/' . $comment->guid) }}"
                                       target="_blank"
                                       class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                        <i class="fas fa-external-link-alt mr-1"></i>View Release
                                    </a>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col space-y-1">
                                    @if($comment->isvisible)
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <i class="fas fa-eye mr-1"></i>Visible
                                        </span>
                                    @else
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            <i class="fas fa-eye-slash mr-1"></i>Hidden
                                        </span>
                                    @endif
                                    @if($comment->shared)
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            <i class="fas fa-share-alt mr-1"></i>Shared
                                        </span>
                                    @endif
                                    @if($comment->issynced)
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                            <i class="fas fa-sync mr-1"></i>Synced
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <div>{{ $comment->created_at ? $comment->created_at->format('Y-m-d') : '—' }}</div>
                                <div class="text-xs">{{ $comment->created_at ? $comment->created_at->format('H:i:s') : '' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button type="button"
                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 transition"
                                        title="Delete"
                                        data-comment-id="{{ $comment->id }}"
                                        data-comment-text="{{ addslashes(Str::limit($comment->text, 50)) }}"
                                        x-on:click="$dispatch('open-delete-modal', { id: $el.dataset.commentId, text: $el.dataset.commentText })">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                No comments found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            {{ $commentsList->links() }}
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div x-data="commentDeleteModal"
     data-delete-url="{{ url('admin/comment-delete') }}"
     x-on:open-delete-modal.window="openModal($event.detail.id, $event.detail.text)"
     x-show="open"
     x-cloak
     class="fixed inset-0 bg-gray-900/50 flex items-center justify-center z-50 transition-opacity">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 mr-2"></i>
                    Confirm Deletion
                </h3>
                <button type="button" x-on:click="close" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div class="px-6 py-4">
            <p class="text-gray-700 dark:text-gray-300 mb-2">Are you sure you want to delete this comment?</p>
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 mt-3">
                <p class="text-sm text-gray-600 dark:text-gray-400 italic" x-text="commentText"></p>
            </div>
            <p class="text-sm text-red-600 dark:text-red-400 mt-3 font-medium">This action cannot be undone.</p>
        </div>
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
            <button type="button"
                    x-on:click="close"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition font-medium">
                <i class="fas fa-times mr-2"></i>Cancel
            </button>
            <form x-ref="deleteForm" method="POST" action="" class="inline">
                @csrf
                <button type="submit"
                        class="px-4 py-2 bg-red-600 dark:bg-red-700 text-white rounded-lg hover:bg-red-700 dark:hover:bg-red-800 transition font-medium">
                    <i class="fas fa-trash mr-2"></i>Delete Comment
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Styles moved to resources/css/csp-safe.css --}}

{{-- Scripts moved to resources/js/csp-safe.js --}}
@endsection

