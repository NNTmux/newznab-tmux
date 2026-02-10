{{-- Back to top button - appears when user scrolls down (Alpine backToTop component) --}}
<button type="button"
        x-data="backToTop()"
        x-show="visible"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        x-cloak
        @click="scrollToTop()"
        class="fixed z-40 bg-blue-600 dark:bg-blue-700 text-white p-3 rounded-full shadow-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors touch-target
               bottom-[max(5.5rem,calc(env(safe-area-inset-bottom)+5rem))] right-[max(1rem,env(safe-area-inset-right))]
               md:bottom-[max(1rem,env(safe-area-inset-bottom))] md:right-[max(1rem,env(safe-area-inset-right))]"
        aria-label="Back to top">
    <i class="fas fa-chevron-up text-lg"></i>
</button>
