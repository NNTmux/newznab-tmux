<!-- Reusable Confirmation Modal -->
<div id="confirmationModal" class="fixed inset-0 bg-gray-900/50 dark:bg-black/70 hidden items-center justify-center z-50 transition-opacity">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center" id="confirmationModalTitle">
                    <i class="fas fa-exclamation-circle text-blue-600 dark:text-blue-400 mr-2" id="confirmationModalIcon"></i>
                    <span id="confirmationModalTitleText">Confirm Action</span>
                </h3>
                <button type="button" data-close-confirmation-modal class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div class="px-6 py-4">
            <p class="text-gray-700 dark:text-gray-300" id="confirmationModalMessage">
                Are you sure you want to proceed?
            </p>
            <div id="confirmationModalDetails" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 mt-3 hidden">
                <p class="text-sm text-gray-600 dark:text-gray-400" id="confirmationModalDetailsText"></p>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
            <button type="button"
                    data-close-confirmation-modal
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition font-medium">
                <i class="fas fa-times mr-2"></i><span id="confirmationModalCancelText">Cancel</span>
            </button>
            <button type="button"
                    data-confirm-confirmation-modal
                    id="confirmationModalConfirmBtn"
                    class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition font-medium">
                <i class="fas fa-check mr-2"></i><span id="confirmationModalConfirmText">Confirm</span>
            </button>
        </div>
    </div>
</div>

