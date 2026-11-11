{{-- Toast notifications (flash messages and showToast()). Store: resources/js/alpine/stores/toast.js --}}
<div x-data="toastContainer"
     class="pointer-events-none fixed right-4 top-4 z-9999 flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2">
    {{-- Always in the DOM so screen readers register it before the first toast appears. --}}
    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" x-text="announcement()"></p>
    <template x-for="toast in items()" :key="toast.id">
        <div class="toast-item pointer-events-auto flex w-full items-start gap-3 overflow-hidden rounded-card border border-line bg-surface p-4 shadow-overlay transition-all duration-300"
             :class="[toneFor(toast.type), toast.removing ? 'translate-x-full opacity-0' : 'translate-x-0 opacity-100']"
             :role="roleFor(toast.type)"
             @mouseenter="pause(toast.id)"
             @mouseleave="resume(toast.id)"
             @focusin="pause(toast.id)"
             @focusout="resume(toast.id)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-full"
             x-transition:enter-end="opacity-100 translate-x-0">
            <span class="toast-item__icon flex h-8 w-8 shrink-0 items-center justify-center rounded-full" aria-hidden="true">
                <i aria-hidden="true" class="fas" :class="iconFor(toast.type)"></i>
            </span>
            <p class="min-w-0 flex-1 self-center text-sm leading-5 text-fg" x-text="toast.message"></p>
            <button type="button"
                    @click="dismiss(toast.id)"
                    class="-m-1 shrink-0 rounded-lg p-1 text-fg-muted transition hover:bg-surface-hover hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    aria-label="Dismiss notification">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
    </template>
</div>
