<div class="container mx-auto px-4 py-3">
    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <p class="text-gray-300 dark:text-gray-400">
            &copy; {{ now()->year }}
            <a href="https://github.com/NNTmux/newznab-tmux" class="text-primary-400 hover:text-primary-300 transition">NNTmux</a>
        </p>

        <div class="flex items-center gap-4">
            <a href="{{ url('/terms-and-conditions') }}" class="text-gray-300 dark:text-gray-400 hover:text-white transition">Terms</a>
            <a href="{{ url('/privacy-policy') }}" class="text-gray-300 dark:text-gray-400 hover:text-white transition">Privacy</a>
            <a href="{{ route('contact-us') }}" class="text-gray-300 dark:text-gray-400 hover:text-white transition">Contact</a>
        </div>

        <div class="flex items-center gap-3">
            <a href="https://github.com/NNTmux/newznab-tmux" class="text-gray-300 dark:text-gray-400 hover:text-white transition" title="GitHub">
                <i class="fab fa-github text-lg"></i>
            </a>
            <a href="{{ url('/rss') }}" class="text-gray-300 dark:text-gray-400 hover:text-white transition" title="RSS Feeds">
                <i class="fas fa-rss text-lg"></i>
            </a>
        </div>
    </div>
</div>

