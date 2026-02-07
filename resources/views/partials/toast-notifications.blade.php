{{-- Toast Notification Component - Alpine.js CSP Safe --}}
{{-- The toastContainer component is defined in resources/js/alpine/components/toast-notification.js --}}
{{-- The toast store is defined in resources/js/alpine/stores/toast.js --}}

<!-- Toast Notification Container - Alpine.js CSP Safe -->
<div x-data="toastContainer"
     class="fixed top-4 right-4 z-9999 flex flex-col gap-2 pointer-events-none"
     aria-live="polite"
     aria-atomic="true">
    <template x-for="toast in items()" x-bind:key="toast.id">
        <div class="pointer-events-auto max-w-sm w-full bg-white dark:bg-gray-800 shadow-lg rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden transform transition-all duration-300"
             x-bind:class="toast.removing ? 'opacity-0 translate-x-full' : 'opacity-100 translate-x-0'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-full"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-full">
            <div class="p-4 flex items-start gap-3">
                <div class="shrink-0">
                    <i class="fas"
                       x-bind:class="[
                           iconFor(toast.type),
                           toast.type === 'success' ? 'text-green-500' : '',
                           toast.type === 'error' ? 'text-red-500' : '',
                           toast.type === 'warning' ? 'text-yellow-500' : '',
                           toast.type === 'info' ? 'text-blue-500' : ''
                       ]"></i>
                </div>
                <div class="flex-1 text-sm text-gray-700 dark:text-gray-300" x-text="toast.message"></div>
                <button type="button"
                        @click="dismiss(toast.id)"
                        class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </template>
</div>


