                <!-- Additional Usenet Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Additional Usenet Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="maxsizetopostprocess" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-file-archive mr-1"></i>Maximum Release Size to Post Process
                            </label>
                            <div class="flex gap-2">
                                <input type="number" step="any" min="0" id="maxsizetopostprocess" name="maxsizetopostprocess" value="{{ $sizeFields['maxsizetopostprocess']['value'] ?? 0 }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <select id="maxsizetopostprocess_unit" name="maxsizetopostprocess_unit"
                                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    @foreach($sizeUnits as $unit)
                                        <option value="{{ $unit }}" {{ ($sizeFields['maxsizetopostprocess']['unit'] ?? 'GB') === $unit ? 'selected' : '' }}>{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The maximum size to postprocess a release, stored as bytes. If set to 0, then ignored.</p>
                        </div>

                        <div>
                            <label for="minsizetopostprocess" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-file-archive mr-1"></i>Minimum Release Size to Post Process
                            </label>
                            <div class="flex gap-2">
                                <input type="number" step="any" min="0" id="minsizetopostprocess" name="minsizetopostprocess" value="{{ $sizeFields['minsizetopostprocess']['value'] ?? 0 }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <select id="minsizetopostprocess_unit" name="minsizetopostprocess_unit"
                                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    @foreach($sizeUnits as $unit)
                                        <option value="{{ $unit }}" {{ ($sizeFields['minsizetopostprocess']['unit'] ?? 'MB') === $unit ? 'selected' : '' }}>{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The minimum size to post process (additional) a release, stored as bytes. If set to 0, then ignored.</p>
                        </div>
                    </div>
                </div>
