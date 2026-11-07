{{-- Admin blue info alert box --}}
<div class="border-b border-info-100 bg-info-50/80 px-6 py-4 dark:border-info-800 dark:bg-info-900/20" role="note">
    <div class="flex">
        <div class="shrink-0">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-info-100 text-info-600 dark:bg-info-900/60 dark:text-info-300">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
            </span>
        </div>
        <div class="ml-3 self-center text-sm leading-6 text-info-800 dark:text-info-200">
            {{ $slot }}
        </div>
    </div>
</div>
