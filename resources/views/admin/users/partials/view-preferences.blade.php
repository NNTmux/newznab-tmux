                @if(!empty($user['id']))
                    <!-- View Preferences -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Category Preferences
                        </label>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="movieview"
                                       name="movieview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['movieview'] ?? 0) : ($user->movieview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="movieview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Movies</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="musicview"
                                       name="musicview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['musicview'] ?? 0) : ($user->musicview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="musicview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Music</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="gameview"
                                       name="gameview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['gameview'] ?? 0) : ($user->gameview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="gameview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Games</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="consoleview"
                                       name="consoleview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['consoleview'] ?? 0) : ($user->consoleview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="consoleview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Console</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="bookview"
                                       name="bookview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['bookview'] ?? 0) : ($user->bookview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="bookview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Books</label>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </form>

                @if(!is_array($user))
                    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900" @if($user->passkeys->isNotEmpty()) x-data="adminUserPasskeys" @endif>
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                <i class="fas fa-fingerprint mr-2 text-blue-600 dark:text-blue-400"></i>Passkeys (WebAuthn)
                            </h3>
                            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-200">
                                {{ $user->passkeys->count() }} registered
                            </span>
                        </div>

                        @if($user->passkeys->isEmpty())
                            <div class="mt-4 rounded-lg border border-dashed border-gray-300 p-3 text-sm text-gray-600 dark:border-gray-600 dark:text-gray-300">
                                This user has no registered passkeys. They can create one from their profile security settings.
                            </div>
                        @else
                            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
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
                                        @foreach($user->passkeys as $passkey)
                                            <tr>
                                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $passkey->name }}</td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ optional($passkey->created_at)->diffForHumans() ?? 'Unknown' }}</td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ optional($passkey->last_used_at)->diffForHumans() ?? 'Not used yet' }}</td>
                                                <td class="px-4 py-3 text-right">
                                                    <form method="POST" action="{{ route('admin.user-passkey.destroy', $passkey) }}" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            data-confirm="Delete this passkey? The user will lose this device as a login method."
                                                            class="confirm-link rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/30"
                                                        >
                                                            Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if($user->passkeys->isNotEmpty())
                        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                            <button
                                type="button"
                                @click="toggleDangerZone()"
                                class="text-sm font-semibold text-red-700 hover:text-red-800 dark:text-red-300 dark:hover:text-red-200"
                            >
                                <i class="fas fa-exclamation-triangle mr-2"></i>Emergency actions
                            </button>

                            <div x-show="showDangerZone" x-cloak class="mt-3 space-y-3">
                                <p class="text-xs text-red-700 dark:text-red-300">
                                    Remove all passkeys if this user lost or replaced their passkey device.
                                </p>

                                <form method="POST" action="{{ route('admin.user-passkeys.wipe') }}" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $user->id }}">

                                    <div>
                                        <label for="passkey_wipe_confirmation" class="block text-xs font-medium text-red-700 dark:text-red-300">Type WIPE to confirm</label>
                                        <input
                                            id="passkey_wipe_confirmation"
                                            name="confirmation"
                                            x-model="wipeConfirm"
                                            type="text"
                                            required
                                            class="mt-1 w-full rounded-md border border-red-300 px-3 py-2 text-sm text-gray-900 focus:border-red-500 focus:ring-2 focus:ring-red-500 dark:border-red-700 dark:bg-gray-800 dark:text-gray-100"
                                        >
                                    </div>

                                    <button
                                        type="submit"
                                        :disabled="!canWipe()"
                                        class="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-700 dark:hover:bg-red-800"
                                    >
                                        Remove ALL passkeys
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endif
                    </div>
                @endif
