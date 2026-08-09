<!-- NFO Modal - Alpine.js CSP Safe -->
<div x-data="nfoModal"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="nfo-modal-title"
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
            <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100" id="nfo-modal-title">
                        <i class="fas fa-file-alt mr-2 text-yellow-600 dark:text-yellow-400"></i>NFO File
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" @click="close()">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Loading state -->
                <div x-show="loading" class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl mr-2 text-yellow-600 dark:text-yellow-400"></i>
                    <span class="text-gray-600 dark:text-gray-400">Loading NFO...</span>
                </div>

                <!-- Error state -->
                <div x-show="error && !loading" class="text-center py-8">
                    <i class="fas fa-exclamation-circle text-3xl text-red-600 dark:text-red-400"></i>
                    <p class="text-red-600 dark:text-red-400 mt-2">Failed to load NFO file</p>
                </div>

                <!-- Content -->
                <div x-show="!loading && !error"
                     class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto font-mono text-sm whitespace-pre nfo-content max-h-96 overflow-y-auto"
                     x-text="content"></div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button"
                        @click="close()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Close
                </button>
            </div>
            </div>
        </div>
    </div>
</div>


