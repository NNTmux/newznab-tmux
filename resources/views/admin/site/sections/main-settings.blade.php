                <!-- Main Site Settings, HTML Layout, Tags -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Main Site Settings, HTML Layout, Tags</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="strapline" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-quote-right mr-1"></i>Strapline
                            </label>
                            <input type="text" id="strapline" name="strapline" value="{{ $site['strapline'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Displayed in the header on every public page.</p>
                        </div>

                        <div>
                            <label for="metatitle" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-heading mr-1"></i>Meta Title
                            </label>
                            <input type="text" id="metatitle" name="metatitle" value="{{ $site['metatitle'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Stem meta-tag appended to all page title tags.</p>
                        </div>

                        <div>
                            <label for="metadescription" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-comment mr-1"></i>Meta Description
                            </label>
                            <textarea id="metadescription" name="metadescription" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $site['metadescription'] ?? '' }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">Stem meta-description appended to all page meta description tags.</p>
                        </div>

                        <div>
                            <label for="metakeywords" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-tags mr-1"></i>Meta Keywords
                            </label>
                            <textarea id="metakeywords" name="metakeywords" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $site['metakeywords'] ?? '' }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">Stem meta-keywords appended to all page meta keyword tags.</p>
                        </div>

                        <div>
                            <label for="footer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-copyright mr-1"></i>Footer
                            </label>
                            <textarea id="footer" name="footer" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $site['footer'] ?? '' }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">Displayed in the footer section of every public page.</p>
                        </div>

                        <div>
                            <label for="home_link" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-home mr-1"></i>Default Home Page
                            </label>
                            <input type="text" id="home_link" name="home_link" value="{{ $site['home_link'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The relative path to the landing page shown when a user logs in, or clicks the home link.</p>
                        </div>

                        <div>
                            <label for="dereferrer_link" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-external-link-alt mr-1"></i>Dereferrer Link
                            </label>
                            <input type="text" id="dereferrer_link" name="dereferrer_link" value="{{ $site['dereferrer_link'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Optional URL to prepend to external links.</p>
                        </div>

                        <div>
                            <label for="tandc" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-gavel mr-1"></i>Terms and Conditions
                            </label>
                            <textarea id="tandc" name="tandc" rows="15"
                                      data-tinymce-api-key="{{ config('tinymce.api_key', 'no-api-key') }}"
                                      class="tinymce-editor w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $site['tandc'] ?? '' }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">Text displayed in the terms and conditions page. Use the rich text editor to format your content.</p>
                        </div>
                    </div>
                </div>
