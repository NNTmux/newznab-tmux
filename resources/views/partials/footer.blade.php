<footer class="bg-gray-100 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 transition-colors duration-200">
    <div class="container mx-auto px-4 py-3">
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <!-- Copyright -->
            <p class="text-gray-600 dark:text-gray-400">
                &copy; {{ now()->year }}
                <a href="https://github.com/NNTmux/newznab-tmux" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition">NNTmux</a>
            </p>

            <!-- Quick Links -->
            <div class="flex items-center gap-4">
                <a href="{{ url('/terms-and-conditions') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition">Terms</a>
                <a href="{{ url('/privacy-policy') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition">Privacy</a>
                <a href="{{ route('contact-us') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition">Contact</a>
            </div>

            <!-- Social Links -->
            <div class="flex items-center gap-3">
                <a href="https://github.com/NNTmux/newznab-tmux" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition" title="GitHub">
                    <i class="fab fa-github text-lg"></i>
                </a>
                <a href="{{ url('/rss') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition" title="RSS Feeds">
                    <i class="fas fa-rss text-lg"></i>
                </a>
            </div>
        </div>
    </div>
</footer>

