                <!-- NFO Processing Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">NFO Processing Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="lookupnfo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-file-alt mr-1"></i>Lookup NFO
                            </label>
                            <select id="lookupnfo" name="lookupnfo" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['lookupnfo'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to retrieve an nfo file from usenet.<br><strong>NOTE: disabling nfo lookups will disable movie lookups.</strong></p>
                        </div>

                        <div>
                            <label for="maxnfoprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-file-text mr-1"></i>Maximum NFO Files Per Run
                            </label>
                            <input type="text" id="maxnfoprocessed" name="maxnfoprocessed" value="{{ $site['maxnfoprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of NFO files to process per run. This uses NNTP an connection, 1 per thread. This does not query external metadata providers.</p>
                        </div>

                        <div>
                            <label for="maxsizetoprocessnfo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-upload mr-1"></i>Maximum Release Size to Process NFOs
                            </label>
                            <div class="flex gap-2">
                                <input type="number" step="any" min="0" id="maxsizetoprocessnfo" name="maxsizetoprocessnfo" value="{{ $sizeFields['maxsizetoprocessnfo']['value'] ?? 0 }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <select id="maxsizetoprocessnfo_unit" name="maxsizetoprocessnfo_unit"
                                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    @foreach($sizeUnits as $unit)
                                        <option value="{{ $unit }}" {{ ($sizeFields['maxsizetoprocessnfo']['unit'] ?? 'GB') === $unit ? 'selected' : '' }}>{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The maximum size of a release to process it for NFOs, stored as bytes. If set to 0, then ignored.</p>
                        </div>

                        <div>
                            <label for="minsizetoprocessnfo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-download mr-1"></i>Minimum Release Size to Process NFOs
                            </label>
                            <div class="flex gap-2">
                                <input type="number" step="any" min="0" id="minsizetoprocessnfo" name="minsizetoprocessnfo" value="{{ $sizeFields['minsizetoprocessnfo']['value'] ?? 0 }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <select id="minsizetoprocessnfo_unit" name="minsizetoprocessnfo_unit"
                                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    @foreach($sizeUnits as $unit)
                                        <option value="{{ $unit }}" {{ ($sizeFields['minsizetoprocessnfo']['unit'] ?? 'MB') === $unit ? 'selected' : '' }}>{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The minimum size of a release to process it for NFOs, stored as bytes. If set to 0, then ignored.</p>
                        </div>

                        <div>
                            <label for="maxnforetries" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-refresh mr-1"></i>Maximum Amount of Times to Redownload a NFO
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="maxnforetries" name="maxnforetries" value="{{ $site['maxnforetries'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">times</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">How many times to retry when a NFO fails to download. If set to 0, we will not retry. The max is 7.</p>
                        </div>
                    </div>
                </div>
