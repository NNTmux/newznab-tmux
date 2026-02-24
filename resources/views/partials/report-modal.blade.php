{{-- Shared Report Modal - Alpine.js CSP Safe --}}
{{-- Rendered once in the layout via partials/release-modals, reused by all report-trigger buttons --}}
@auth
<div x-data="releaseReport"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="report-modal-title"
     role="dialog"
     aria-modal="true"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">

    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity"
         aria-hidden="true"
         @click="close()"></div>

    <!-- Modal Content Container -->
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <!-- Modal Panel -->
            <div class="relative w-full max-w-md p-6 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-gray-800 shadow-xl rounded-2xl"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100" id="report-modal-title">
                        <i class="fas fa-flag text-red-500 mr-2"></i>Report Release
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" @click="close()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form @submit.prevent="submit()">
                    <!-- Reason Select -->
                    <div class="mb-4">
                        <label for="shared-report-reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Reason for Report <span class="text-red-500">*</span>
                        </label>
                        <select x-model="reason"
                                id="shared-report-reason"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">Select a reason...</option>
                            <option value="duplicate">Duplicate Release</option>
                            <option value="fake">Fake/Malicious Content</option>
                            <option value="password">Password Protected</option>
                            <option value="incomplete">Incomplete/Corrupted</option>
                            <option value="wrong_category">Wrong Category</option>
                            <option value="spam">Spam/Advertisement</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Description Textarea -->
                    <div class="mb-4">
                        <label for="shared-report-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Additional Details (optional)
                        </label>
                        <textarea x-model="description"
                                  id="shared-report-description"
                                  rows="3"
                                  maxlength="1000"
                                  placeholder="Provide any additional details that might help us review this report..."
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 resize-none"></textarea>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-text="charCount()"></p>
                    </div>

                    <!-- Error Message -->
                    <div x-show="errorMsg" x-cloak class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <p class="text-sm text-red-600 dark:text-red-400" x-text="errorMsg"></p>
                    </div>

                    <!-- Success Message -->
                    <div x-show="successMsg" x-cloak class="mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                        <p class="text-sm text-green-600 dark:text-green-400" x-text="successMsg"></p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-3">
                        <button type="button"
                                @click="close()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="!canSubmit()"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center">
                            <i x-show="isSubmitting" class="fas fa-spinner fa-spin mr-2"></i>
                            <span x-text="submitText()"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endauth
