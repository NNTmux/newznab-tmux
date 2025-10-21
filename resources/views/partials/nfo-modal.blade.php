<!-- NFO Modal - CSP Safe -->
<div id="nfoModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" aria-hidden="true" data-close-nfo-modal></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                        <i class="fas fa-file-alt mr-2 text-yellow-600 dark:text-yellow-400"></i>NFO File
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" data-close-nfo-modal>
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="nfoContent" class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto font-mono text-sm whitespace-pre nfo-content">
                    <div class="flex items-center justify-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl mr-2"></i>
                        <span>Loading NFO...</span>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" data-close-nfo-modal>
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- NFO Modal functionality moved to resources/js/csp-safe.js -->

