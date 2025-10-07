<!-- Modal View for Trailer - No layout wrapper -->
<div class="p-4">
    @if(isset($movie))
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">{{ $movie['title'] ?? 'Movie Trailer' }}</h2>

        @if(isset($movie['trailer']) && $movie['trailer'])
            <div class="aspect-w-16 aspect-h-9 mb-4">
                <iframe src="https://www.youtube.com/embed/{{ $movie['trailer'] }}"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                        class="w-full h-96 rounded-lg">
                </iframe>
            </div>
        @else
            <div class="bg-gray-100 dark:bg-gray-800 p-8 rounded-lg text-center">
                <i class="fas fa-video-slash text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600">No trailer available for this movie.</p>
            </div>
        @endif

        @if(isset($movie['plot']) && $movie['plot'])
            <div class="mt-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Plot</h3>
                <p class="text-gray-700">{{ $movie['plot'] }}</p>
            </div>
        @endif
    @endif
</div>

