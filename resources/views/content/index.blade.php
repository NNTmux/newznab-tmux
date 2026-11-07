@extends('layouts.main')

@section('content')
<x-page-header :title="$front ? auth()->user()->username : 'Content'" :eyebrow="$front ? 'Welcome' : 'Explore the site'" :description="$front ? 'Discover the latest site information and start exploring the index.' : 'Browse our published information and useful content pages.'" icon="fas fa-file-alt" />

<div class="surface-panel rounded-xl shadow-sm">

    @if($front)
        <!-- Front Page Content -->
        <div class="px-6 py-8">
            @if(!empty($content) && count($content) > 0)
                <div class="space-y-6">
                    @foreach($content as $item)
                        <article class="surface-panel rounded-lg shadow-sm p-6 border transition-shadow duration-200 hover:shadow-md">
                            <div class="surface-prose">
                                @if(filled($item->title))
                                    <h2 class="mb-4 text-3xl font-bold">{{ $item->title }}</h2>
                                @endif

                                @if(isset($item->body))
                                    <div class="leading-relaxed">
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
                <x-empty-state
                    icon="fas fa-file-alt"
                    title="No Content Available"
                    message="There is no content to display at this time."
                />
            @endif
        </div>
    @else
        <!-- Content List Page -->
        <div class="px-6 pb-6">
            @if(!empty($content) && count($content) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($content as $item)
                        @if($item)
                            <div class="surface-panel-alt rounded-lg border p-6 transition hover:shadow-md">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-3">
                                    <a href="{{ url('/content?page=content&id=' . $item->id) }}" class="hover:text-link dark:hover:text-primary-400 transition">
                                        {{ filled($item->title) ? $item->title : 'Untitled' }}
                                    </a>
                                </h3>

                                @if(isset($item->metadescription))
                                    <p class="text-gray-600 dark:text-gray-400 mb-4">{{ Str::limit($item->metadescription, 150) }}</p>
                                @endif

                                <a href="{{ url('/content?page=content&id=' . $item->id) }}" class="inline-flex items-center text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-medium">
                                    Read More <i aria-hidden="true" class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <x-empty-state
                    icon="fas fa-file-alt"
                    title="No Content Available"
                    message="There is no content to display at this time."
                />
            @endif
        </div>
    @endif
</div>
@endsection
