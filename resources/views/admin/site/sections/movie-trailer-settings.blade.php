                <!-- Movie Trailer Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Movie Trailer Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="trailers_display" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-play-circle mr-1"></i>Fetch/Display Movie Trailers
                            </label>
                            <select id="trailers_display" name="trailers_display" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['trailers_display'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Fetch and display trailers from TraktTV (Requires API key) and/or TrailerAddict on the details page?</p>
                        </div>

                        <div>
                            <label for="trailers_size_x" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-arrows-alt-h mr-1"></i>Trailers Width
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="trailers_size_x" name="trailers_size_x" value="{{ $site['trailers_size_x'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">px</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Maximum width in pixels for the trailer window. (Default: 480)</p>
                        </div>

                        <div>
                            <label for="trailers_size_y" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-arrows-alt-v mr-1"></i>Trailers Height
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="trailers_size_y" name="trailers_size_y" value="{{ $site['trailers_size_y'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">px</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Maximum height in pixels for the trailer window. (Default: 345)</p>
                        </div>
                    </div>
                </div>
