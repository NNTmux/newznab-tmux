@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-user-shield">
        <x-slot:actions>
            <a href="{{ url('admin/role-add') }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800">
                <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add New Role
            </a>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        @if(count($userroles) > 0)
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th>ID</x-admin.th>
                    <x-admin.th>Role Name</x-admin.th>
                    <x-admin.th>API Requests</x-admin.th>
                    <x-admin.th>Download Requests</x-admin.th>
                    <x-admin.th>Default Invites</x-admin.th>
                    <x-admin.th>Rate Limit</x-admin.th>
                    <x-admin.th>Default</x-admin.th>
                    <x-admin.th>Actions</x-admin.th>
                </x-slot:head>

                @foreach($userroles as $role)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $role->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $role->name }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $role->apirequests ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $role->downloadrequests ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $role->defaultinvites ?? 0 }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $role->rate_limit ?? 60 }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($role->isdefault)
                                <x-admin.badge tone="success" icon="fas fa-check">Yes</x-admin.badge>
                            @else
                                <x-admin.badge tone="neutral">No</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex gap-2">
                                <a aria-label="Edit" href="{{ url('admin/role-edit?id=' . $role->id) }}"
                                   class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                   title="Edit">
                                    <i aria-hidden="true" class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.role-delete') }}" method="post" class="inline"
                                      x-data="confirmForm"
                                      data-message="Are you sure you want to delete role '{{ $role->name }}'?"
                                      data-title="Delete Role"
                                      data-type="danger"
                                      data-confirm-text="Delete"
                                      @submit.prevent="submit()">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $role->id }}">
                                    <button type="submit" aria-label="Delete" title="Delete"
                                            class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300">
                                        <i aria-hidden="true" class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-admin.data-table>
        @else
            <x-empty-state
                icon="fas fa-user-shield"
                title="No roles found"
                message="Create your first role to get started."
                :actionUrl="url('admin/role-add')"
                actionLabel="Add New Role"
                actionIcon="fas fa-plus"
            />
        @endif
    </x-admin.card>
</div>
@endsection

