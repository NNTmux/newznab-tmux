<!-- Reusable Confirmation Modal - Alpine.js CSP Safe -->
<div x-data="confirmModal"
     x-show="open"
     x-cloak
     class="fixed inset-0 bg-gray-900/50 dark:bg-black/70 flex items-center justify-center z-50 transition-opacity"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-md w-full mx-4 transform transition-all"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.outside="cancel()">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <i class="fas mr-2" x-bind:class="iconClass()"></i>
                    <span x-text="title">Confirm Action</span>
                </h3>
                <button type="button" @click="cancel()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div class="px-6 py-4">
            <p class="text-gray-700 dark:text-gray-300" x-text="message">
                Are you sure you want to proceed?
            </p>
            <div x-show="details" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 mt-3">
                <p class="text-sm text-gray-600 dark:text-gray-400" x-text="details"></p>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
            <button type="button"
                    @click="cancel()"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition font-medium">
                <i class="fas fa-times mr-2"></i><span x-text="cancelText">Cancel</span>
            </button>
            <button type="button"
                    @click="confirm()"
                    x-bind:class="confirmBtnClass()">
                <i class="fas fa-check mr-2"></i><span x-text="confirmText">Confirm</span>
            </button>
        </div>
    </div>
</div>

