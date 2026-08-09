                <!-- Developer Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Developer Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="showdroppedyencparts" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-bug mr-1"></i>Log Dropped Headers
                            </label>
                            <select id="showdroppedyencparts" name="showdroppedyencparts" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['showdroppedyencparts'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">For developers. Whether to log all headers that have 'yEnc' and are dropped. Logged to not_yenc/groupname.dropped.txt.</p>
                        </div>
                    </div>
                </div>
