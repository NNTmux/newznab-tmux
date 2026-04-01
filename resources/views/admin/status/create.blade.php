@extends('layouts.admin')

@section('content')
@php
    use App\Enums\IncidentImpactEnum;
    use App\Enums\IncidentStatusEnum;

    $oldServiceIds = array_map(
        static fn (mixed $id): int => (int) $id,
        (array) old('service_status_ids', [])
    );
@endphp

<div class="space-y-6">
    <x-admin.card>
        <x-admin.page-header :title="$title" icon="fas fa-plus" />

        <div class="px-6 py-6">
            <form action="{{ route('admin.status.store') }}" method="post" class="space-y-6 max-w-2xl">
                @csrf

                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                    <input type="text" name="title" id="title" value="{{ old('title') }}" required
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    @error('title')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea name="description" id="description" rows="5" required
                              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <fieldset>
                    <legend class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Affected services</legend>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">Select one or more areas impacted by this incident.</p>
                    <div class="space-y-2 rounded-lg border border-gray-300 dark:border-gray-600 p-3 dark:bg-gray-900/40">
                        @foreach($services as $svc)
                            <label class="flex items-center gap-3 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
                                <input type="checkbox" name="service_status_ids[]" value="{{ $svc->id }}"
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                       @checked(in_array($svc->id, $oldServiceIds, true))>
                                <span>{{ $svc->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('service_status_ids')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div>
                    <label for="impact" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Impact (severity)</label>
                    <select name="impact" id="impact" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 shadow-sm">
                        @foreach(IncidentImpactEnum::cases() as $opt)
                            <option value="{{ $opt->value }}" @selected(old('impact', IncidentImpactEnum::Minor->value) === $opt->value)>{{ $opt->label() }}</option>
                        @endforeach
                    </select>
                    @error('impact')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Incident status</label>
                    <select name="status" id="status" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 shadow-sm">
                        @foreach(IncidentStatusEnum::cases() as $opt)
                            <option value="{{ $opt->value }}" @selected(old('status', IncidentStatusEnum::Investigating->value) === $opt->value)>{{ $opt->label() }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="started_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Started at</label>
                    <input type="datetime-local" name="started_at" id="started_at" required
                           value="{{ old('started_at', now()->format('Y-m-d\TH:i')) }}"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 shadow-sm">
                    @error('started_at')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="resolved_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Resolved at (optional)</label>
                    <input type="datetime-local" name="resolved_at" id="resolved_at"
                           value="{{ old('resolved_at') }}"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 shadow-sm">
                    @error('resolved_at')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Create</button>
                    <a href="{{ route('admin.status.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600">Cancel</a>
                </div>
            </form>
        </div>
    </x-admin.card>
</div>
@endsection
