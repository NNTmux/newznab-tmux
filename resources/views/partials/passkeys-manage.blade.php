@php
    $passkeyPayload = $user->passkeys->map(function ($passkey) {
        return [
            'id' => $passkey->id,
            'name' => $passkey->name,
            'created_at' => optional($passkey->created_at)?->toIso8601String(),
            'last_used_at' => optional($passkey->last_used_at)?->toIso8601String(),
        ];
    });
@endphp

<div
    x-data="passkeyManage"
    x-cloak
    data-options-url="{{ route('passkeys.register_options') }}"
    data-store-url="{{ route('passkeys.store') }}"
    data-destroy-base-url="{{ url('passkeys') }}"
    data-passkeys='@json($passkeyPayload)'
>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Register a passkey from your security key, browser, or password manager.
    </p>

    <template x-if="!supported">
        <div class="mt-4 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800 dark:border-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-200">
            Your browser does not support passkeys.
        </div>
    </template>

    <template x-if="supported">
        <div class="mt-4 space-y-4">
            <form @submit.prevent="createPasskey()" class="space-y-3">
                <label for="passkey_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Passkey name
                </label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input
                        id="passkey_name"
                        x-model="name"
                        type="text"
                        maxlength="255"
                        required
                        placeholder="Work laptop, iPhone, etc."
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    >
                    <button
                        type="submit"
                        :disabled="busy"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-primary-700 dark:hover:bg-primary-800"
                    >
                        <i class="fas fa-plus mr-2"></i>
                        <span x-text="busy ? 'Creating...' : 'Create passkey'"></span>
                    </button>
                </div>
            </form>

            <p x-show="error" x-text="error" class="text-sm text-red-600 dark:text-red-400"></p>
            <p x-show="success" x-text="success" class="text-sm text-green-600 dark:text-green-400"></p>

            <template x-if="passkeys.length === 0">
                <div class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-gray-600 dark:text-gray-300">
                    No passkeys registered yet.
                </div>
            </template>

            <template x-if="passkeys.length > 0">
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Name</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Created</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Last used</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            <template x-for="passkey in passkeys" :key="passkey.id">
                                <tr>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100" x-text="passkey.name"></td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300" x-text="formatDate(passkey.created_at)"></td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300" x-text="formatLastUsed(passkey.last_used_at)"></td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            @click="deletePasskey(passkey.id)"
                                            class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/30"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </template>
</div>
