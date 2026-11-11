@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-comments">
        <x-slot:actions>
            <span class="text-sm text-gray-600 dark:text-gray-400">
                Total: {{ $commentsList->total() }} comments
            </span>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>
        <x-admin.list-search :action="route('admin.comments-list')" :value="$lastSearch ?? ''" placeholder="Search by comment text or username" label="Search comments" />

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
                               target="_blank" rel="noopener noreferrer"
                               class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300">
                                <i aria-hidden="true" class="fas fa-external-link-alt mr-1"></i>View Release
                            </a>
                        @else
                            <span class="text-gray-500 dark:text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col space-y-1">
                            @if($comment->isvisible)
                                <x-admin.badge tone="success" icon="fas fa-eye">Visible</x-admin.badge>
                            @else
                                <x-admin.badge tone="danger" icon="fas fa-eye-slash">Hidden</x-admin.badge>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        <div>{{ $comment->created_at ? formatDate($comment->created_at) : '—' }}</div>
                        <div class="text-xs">{{ $comment->created_at ? $comment->created_at->format('H:i:s') : '' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <form action="{{ route('admin.comments-delete', $comment->id) }}" method="post" class="inline"
                              x-data="confirmForm"
                              data-message="Are you sure you want to delete this comment?"
                              data-title="Delete Comment"
                              data-type="danger"
                              data-confirm-text="Delete"
                              @submit.prevent="submit()">
                            @csrf
                            <button type="submit" aria-label="Delete" title="Delete"
                                    class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300 transition">
                                <i aria-hidden="true" class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                        <i aria-hidden="true" class="fas fa-comments text-gray-400 dark:text-gray-500 text-4xl mb-3 block"></i>
                        No comments found.
                    </td>
                </tr>
            @endforelse
        </x-admin.data-table>

        <x-admin.pagination :paginator="$commentsList" />
    </x-admin.card>
</div>
@endsection

