<!-- Media Info Modal - Alpine.js CSP Safe -->
<div x-data="mediainfoModal"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="mediainfo-modal-title"
     role="dialog"
     aria-modal="true"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">

    <!-- Background overlay -->
    <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity"
         aria-hidden="true"
         @click="close()"></div>

    <!-- Modal panel container -->
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100" id="mediainfo-modal-title">
                        <i class="fas fa-info-circle mr-2 text-blue-600 dark:text-blue-400"></i>Media Information
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" @click="close()">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Loading state -->
                <div x-show="loading" class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl mr-2 text-blue-600 dark:text-blue-400"></i>
                    <span class="text-gray-600 dark:text-gray-400">Loading media info...</span>
                </div>

                <!-- Content -->
                <div x-show="!loading"
                     x-html="html"
                     class="max-h-96 overflow-y-auto"></div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button"
                        @click="close()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Close
                </button>
            </div>
            </div>
        </div>
    </div>
</div>

