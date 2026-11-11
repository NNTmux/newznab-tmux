@extends('layouts.main')

@section('content')
<x-page-header title="NFO file" eyebrow="Release metadata" description="Review the original text metadata supplied with this release." icon="fas fa-file-lines">
    @if(isset($rel))
        <x-slot:actions>
            <a href="{{ url('/details/' . $rel['guid']) }}" class="workspace-hero__action"><i class="fas fa-arrow-left" aria-hidden="true"></i>Back to release</a>
        </x-slot:actions>
    @endif
</x-page-header>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
    <div class="bg-gray-900 text-success-400 p-6 rounded-lg overflow-x-auto font-mono text-sm">
        <pre class="whitespace-pre">{{ $nfo['nfoUTF'] ?? $nfo['nfo'] ?? 'NFO content not available' }}</pre>
    </div>

    @if(isset($rel))
        <div class="mt-4">
            <a href="{{ url('/details/' . $rel['guid']) }}" class="inline-flex items-center px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-hover transition">
                <i aria-hidden="true" class="fas fa-arrow-left mr-2"></i> Back to Release
            </a>
        </div>
    @endif
</div>
@endsection
