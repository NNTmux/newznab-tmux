<footer class="bg-gray-100 border-t border-gray-200 mt-auto">
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- About Section -->
            <div>
                <h5 class="text-lg font-bold text-gray-800 mb-4">{{ config('app.name') }}</h5>
                <p class="text-gray-600 mb-4">Your trusted source for Usenet indexing and search services.</p>
                <div class="flex space-x-4">
                    <a href="https://github.com/NNTmux/newznab-tmux" class="text-gray-600 hover:text-gray-900 transition" title="GitHub">
                        <i class="fab fa-github text-2xl"></i>
                    </a>
                    <a href="{{ route('contact-us') }}" class="text-gray-600 hover:text-gray-900 transition" title="Contact Us">
                        <i class="fas fa-envelope text-2xl"></i>
                    </a>
                    <a href="{{ url('/rss') }}" class="text-gray-600 hover:text-gray-900 transition" title="RSS Feeds">
                        <i class="fas fa-rss text-2xl"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h5 class="text-lg font-bold text-gray-800 mb-4">Quick Links</h5>
                <ul class="space-y-2">
                    <li><a href="{{ url('/') }}" class="text-gray-600 hover:text-gray-900 transition">Home</a></li>
                    <li><a href="{{ url('/browse/all') }}" class="text-gray-600 hover:text-gray-900 transition">Browse</a></li>
                    <li><a href="{{ route('search') }}" class="text-gray-600 hover:text-gray-900 transition">Search</a></li>
                    <li><a href="{{ route('contact-us') }}" class="text-gray-600 hover:text-gray-900 transition">Contact</a></li>
                </ul>
            </div>

            <!-- Resources -->
            <div>
                <h5 class="text-lg font-bold text-gray-800 mb-4">Resources</h5>
                <ul class="space-y-2">
                    <li><a href="{{ url('/terms-and-conditions') }}" class="text-gray-600 hover:text-gray-900 transition">Terms & Conditions</a></li>
                    <li><a href="{{ url('/privacy-policy') }}" class="text-gray-600 hover:text-gray-900 transition">Privacy Policy</a></li>
                    <li><a href="https://github.com/NNTmux/newznab-tmux/issues" class="text-gray-600 hover:text-gray-900 transition" target="_blank">Report Issues</a></li>
                    <li><a href="https://github.com/NNTmux/newznab-tmux/wiki" class="text-gray-600 hover:text-gray-900 transition" target="_blank">Documentation</a></li>
                </ul>
            </div>
        </div>

        <div class="border-t border-gray-300 mt-8 pt-6 text-center">
            <p class="text-gray-600">
                <strong>Copyright &copy; {{ now()->year }}
                    <a href="https://github.com/NNTmux/newznab-tmux" class="text-blue-600 hover:text-blue-800 transition">NNTmux</a>
                    <i class="fab fa-github-alt"></i>
                </strong>
            </p>
        </div>
    </div>
</footer>

