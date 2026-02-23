@if(isset($modal) && $modal)
    <pre class="nfo-content">{{ $nfo['nfoUTF'] ?? $nfo['nfo'] ?? 'NFO content not available' }}</pre>
@else
    @extends('layouts.main')

    @section('content')
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-2">NFO File</h1>
            <nav class="text-sm text-gray-600 dark:text-gray-400">
                <a href="{{ url('/') }}" class="hover:text-blue-600">Home</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                @if(isset($rel))
                    <a href="{{ url('/details/' . $rel['guid']) }}" class="hover:text-blue-600 wrap-break-word break-all">{{ $rel['searchname'] }}</a>
                    <i class="fas fa-chevron-right mx-2 text-xs"></i>
                @endif
                <span>NFO</span>
            </nav>
        </div>

        <div class="bg-gray-900 text-green-400 p-6 rounded-lg overflow-x-auto font-mono text-sm">
            <pre class="whitespace-pre">{{ $nfo['nfoUTF'] ?? $nfo['nfo'] ?? 'NFO content not available' }}</pre>
        </div>

        @if(isset($rel))
            <div class="mt-4">
                <a href="{{ url('/details/' . $rel['guid']) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Release
                </a>
            </div>
        @endif
    </div>
    @endsection
@endif

