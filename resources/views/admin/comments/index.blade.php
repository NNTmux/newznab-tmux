@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <x-admin.page-header :title="$title" icon="fas fa-comments">
            <x-slot:actions>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Total: {{ $commentsList->total() }} comments
                </span>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.data-table>
            <x-slot:head>
                <x-admin.th>ID</x-admin.th>
                <x-admin.th>User</x-admin.th>
                <x-admin.th>Comment</x-admin.th>
                <x-admin.th>Release</x-admin.th>
                <x-admin.th>Status</x-admin.th>
                <x-admin.th>Created</x-admin.th>
                <x-admin.th>Actions</x-admin.th>
            </x-slot:head>

            @forelse($commentsList as $comment)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 font-mono">
                        #{{ $comment->id }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
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
                        <div class="text-sm text-gray-900 dark:text-gray-100 max-w-md">
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
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                    <i class="fas fa-eye mr-1"></i>Visible
                                </span>
                            @else
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                    <i class="fas fa-eye-slash mr-1"></i>Hidden
                                </span>
                            @endif
                            @if($comment->shared)
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                    <i class="fas fa-share-alt mr-1"></i>Shared
                                </span>
                            @endif
                            @if($comment->issynced)
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200">
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
                        <a href="{{ url('admin/comment-delete?id=' . $comment->id) }}"
                           class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 transition"
                           title="Delete"
                           data-confirm-delete>
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                        <i class="fas fa-comments text-gray-400 dark:text-gray-500 text-4xl mb-3 block"></i>
                        No comments found.
                    </td>
                </tr>
            @endforelse
        </x-admin.data-table>

        <x-admin.pagination :paginator="$commentsList" />
    </x-admin.card>
</div>
@endsection

