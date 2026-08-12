<div class="border-b border-gray-200 pb-6 dark:border-gray-700">
    <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Web Login Sessions</h2>

    <div class="space-y-4">
        <div>
            <x-label for="single_active_session">
                <i class="fas fa-laptop mr-1"></i>Single Active Session
            </x-label>
            <x-select id="single_active_session" name="single_active_session">
                <option value="0" @selected((int) ($site['single_active_session'] ?? 0) === 0)>Off — allow concurrent devices</option>
                <option value="1" @selected((int) ($site['single_active_session'] ?? 0) === 1)>On — a new login ends the account's other logins</option>
            </x-select>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                This anti-account-sharing policy applies from the next login onward. Enabling it does not immediately end existing sessions.
            </p>
        </div>

        <div class="surface-panel-alt rounded-lg p-4">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Breach response</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Expire every Web Login Session, Remembered Login, and 2FA Trusted Device. Your current admin session is the only session spared.
            </p>
            <x-button
                type="submit"
                variant="danger"
                icon="fas fa-right-from-bracket"
                class="mt-3"
                formaction="{{ route('admin.login-sessions.expire-all') }}"
                formmethod="POST"
                data-confirm="Expire all web logins and trusted devices? Only this admin session will remain signed in."
            >Expire All Logins</x-button>
        </div>
    </div>
</div>
