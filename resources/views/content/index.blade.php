@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    @if($front)
        <!-- Front Page Content -->
        <div class="px-6 py-8">
            @if(!empty($content) && count($content) > 0)
                <div class="space-y-6">
                    @foreach($content as $item)
                        <article class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border border-gray-100 hover:shadow-lg transition-shadow duration-200">
                            <div class="prose max-w-none">
                                @if(isset($item->title))
                                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-4">{{ $item->title }}</h1>
                                @endif

                                @if(isset($item->body))
                                    <div class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                        {!! html_entity_decode(trim($item->body, '\'"')) !!}
                                    </div>
                                @endif

                                @if(isset($item->metadescription))
                                    <p class="mt-4 text-gray-600 dark:text-gray-400 italic">{{ $item->metadescription }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No Content Available</h3>
                    <p class="text-gray-500">There is no content to display at this time.</p>
                </div>
            @endif
        </div>
    @else
        <!-- Content List Page -->
        <div class="px-6 py-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Content</h1>
                <p class="text-gray-600">Browse our content pages</p>
            </div>

            @if(!empty($content) && count($content) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($content as $item)
                        @if($item)
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6 hover:shadow-md transition">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-3">
                                    <a href="{{ url('/content?page=content&id=' . $item->id) }}" class="hover:text-blue-600 dark:text-blue-400 transition">
                                        {{ $item->title ?? 'Untitled' }}
                                    </a>
                                </h3>

                                @if(isset($item->metadescription))
                                    <p class="text-gray-600 dark:text-gray-400 mb-4">{{ Str::limit($item->metadescription, 150) }}</p>
                                @endif

                                <a href="{{ url('/content?page=content&id=' . $item->id) }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">
                                    Read More <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No Content Available</h3>
                    <p class="text-gray-500">There is no content to display at this time.</p>
                </div>
            @endif
        </div>
    @endif
</div>

@push('styles')
<style>
    .prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
        margin-top: 1.5em;
        margin-bottom: 0.75em;
        font-weight: 600;
    }

    .prose p {
        margin-bottom: 1em;
    }

    .prose ul, .prose ol {
        margin-bottom: 1em;
        padding-left: 1.5em;
    }

    .prose a {
        color: #2563eb;
        text-decoration: underline;
    }

    .prose a:hover {
        color: #1d4ed8;
    }

    .prose img {
        max-width: 100%;
        height: auto;
        border-radius: 0.5rem;
        margin: 1.5em 0;
    }

    .prose blockquote {
        border-left: 4px solid #e5e7eb;
        padding-left: 1em;
        color: #6b7280;
        font-style: italic;
        margin: 1.5em 0;
    }

    .prose code {
        background-color: #f3f4f6;
        padding: 0.25em 0.5em;
        border-radius: 0.25rem;
        font-size: 0.875em;
    }

    .prose pre {
        background-color: #1f2937;
        color: #f9fafb;
        padding: 1em;
        border-radius: 0.5rem;
        overflow-x: auto;
        margin: 1.5em 0;
    }

    .prose pre code {
        background-color: transparent;
        padding: 0;
        color: inherit;
    }
</style>
@endpush
@endsection

