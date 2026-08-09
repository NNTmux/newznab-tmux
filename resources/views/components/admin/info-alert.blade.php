{{-- Admin blue info alert box --}}
<div class="px-6 py-4 bg-primary-50 dark:bg-primary-900/20 border-b border-primary-100 dark:border-primary-800">
    <div class="flex">
        <div class="shrink-0">
            <i class="fas fa-info-circle text-primary-500 dark:text-primary-400 text-xl mr-3"></i>
        </div>
        <div class="text-sm text-primary-700 dark:text-primary-300">
            {{ $slot }}
        </div>
    </div>
</div>

