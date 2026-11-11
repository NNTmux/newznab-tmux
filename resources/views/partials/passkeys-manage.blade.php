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
    data-confirm-password-url="{{ route('password.confirm') }}"
    data-store-url="{{ route('passkeys.store') }}"
    data-destroy-base-url="{{ url('passkeys') }}"
    data-passkeys='@json($passkeyPayload)'
>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Register a passkey using your browser, password manager, Windows Hello, Touch ID / Face ID, or your phone.
    </p>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
        Hardware security keys are available separately when you want to use a USB/FIDO2 key.
    </p>

    <template x-if="!supported">
        <div class="mt-4 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-900/20 dark:text-warning-200">
            Your browser does not support passkeys.
        </div>
    </template>

    <template x-if="supported">
        <div class="mt-4 space-y-4">
            <form @submit.prevent="createBrowserPasskey()" class="space-y-3">
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
                        :disabled="busy || browserPasskeyBlocked()"
                        class="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-60 dark:bg-accent-hover dark:hover:bg-primary-800"
                    >
                        <i aria-hidden="true" class="fas fa-plus mr-2"></i>
                        <span x-text="busy ? 'Creating...' : 'Create browser/app passkey'"></span>
                    </button>
                </div>
                <p x-show="browserPasskeyNotice()" x-text="browserPasskeyNotice()" x-cloak class="text-xs text-warning-700 dark:text-warning-300">
                </p>
                <button
                    type="button"
                    @click="createSecurityKeyPasskey()"
                    :disabled="busy || !name"
                    class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    <i aria-hidden="true" class="fas fa-key mr-2"></i>
                    Use USB security key
                </button>
            </form>

            <p x-show="error" x-text="error" class="text-sm text-danger-600 dark:text-danger-400"></p>
            <p x-show="success" x-text="success" class="text-sm text-success-600 dark:text-success-400"></p>

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
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Name</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Created</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Last used</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Actions</th>
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
                                            class="rounded-lg border border-danger-300 px-3 py-1.5 text-xs font-medium text-danger-700 hover:bg-danger-50 dark:border-danger-700 dark:text-danger-300 dark:hover:bg-danger-900/30"
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
