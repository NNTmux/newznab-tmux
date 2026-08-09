                <!-- Password Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Password Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-file-archive mr-1"></i>Download Last Compressed File
                            </label>
                            <select id="end" name="end" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['end'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Try to download the last rar or zip file? (This is good if most of the files are at the end.) Note: The first rar/zip is still downloaded.</p>
                        </div>

                        <div>
                            <label for="showpasswordedrelease" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-lock mr-1"></i>Show Passworded Releases
                            </label>
                            <select id="showpasswordedrelease" name="showpasswordedrelease" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($passworded['ids'] as $index => $passwordedId)
                                    <option value="{{ $passwordedId }}" {{ ($site['showpasswordedrelease'] ?? '') == $passwordedId ? 'selected' : '' }}>
                                        {{ $passworded['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to show passworded releases in browse, search, api and rss feeds.</p>
                        </div>
                    </div>
                </div>
