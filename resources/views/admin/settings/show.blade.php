@extends('layouts.admin')

@section('content')
    @php
        $errors ??= new \Illuminate\Support\ViewErrorBag();
    @endphp
    <div class="mx-auto max-w-6xl space-y-6">
        <x-admin.page-header
            :title="$domain->label().' settings'"
            icon="fas fa-sliders-h"
            subtitle="Validated, typed configuration for this domain."
        />

        <nav class="flex flex-wrap gap-2" aria-label="Configuration domains">
            @foreach ($domains as $catalogDomain)
                <a href="{{ route('admin.settings.show', ['domain' => $catalogDomain->value]) }}"
                   @class([
                       'rounded-lg px-3 py-2 text-sm font-medium',
                       'bg-primary-600 text-white' => $catalogDomain === $domain,
                       'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200' => $catalogDomain !== $domain,
                   ])>
                    {{ $catalogDomain->label() }}
                </a>
            @endforeach
        </nav>

        @if (session('success'))
            <div class="rounded-lg bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
        @endif

        @if (isset($errors) && $errors->any())
            <div class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
                <p class="font-semibold">Nothing was saved. Correct the highlighted values.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('admin.settings.update', ['domain' => $domain->value]) }}" enctype="multipart/form-data" class="rounded-xl bg-white p-6 shadow dark:bg-gray-900">
            @csrf
            @method('PUT')

            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($fields as $field)
                    @php
                        $storedValue = $configuration->getAttribute($field->column);
                        if ($storedValue instanceof \BackedEnum) {
                            $storedValue = $storedValue->value;
                        }
                        $inputValue = old($field->column, $storedValue);
                    @endphp
                    <div @class(['md:col-span-2' => in_array($field->control, ['textarea', 'rich-text', 'multiselect'], true)])>
                        <label for="{{ $field->column }}" class="block text-sm font-medium text-gray-800 dark:text-gray-100">{{ $field->label }}</label>

                        @if ($field->control === 'boolean' || $field->control === 'enum')
                            <select id="{{ $field->column }}" name="{{ $field->column }}" class="mt-1 w-full rounded-lg border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-800">
                                @foreach ($field->options as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected((string) $inputValue === (string) $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        @elseif ($field->control === 'textarea' || $field->control === 'rich-text' || $field->control === 'regex')
                            <textarea id="{{ $field->column }}" name="{{ $field->column }}" rows="{{ $field->control === 'rich-text' ? 10 : 4 }}" class="mt-1 w-full rounded-lg border-gray-300 bg-white font-mono text-sm dark:border-gray-700 dark:bg-gray-800">{{ $inputValue }}</textarea>
                        @elseif ($field->control === 'bytes')
                            <div class="mt-1 flex gap-2">
                                <input id="{{ $field->column }}" name="{{ $field->column }}" type="number" min="0" step="0.01" value="{{ old($field->column, $sizeValues[$field->column]['value']) }}" class="w-full rounded-lg border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-800">
                                <select name="{{ $field->column }}_unit" class="rounded-lg border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-800">
                                    @foreach ($sizeUnits as $unit)
                                        <option value="{{ $unit }}" @selected(old($field->column.'_unit', $sizeValues[$field->column]['unit']) === $unit)>{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif ($field->control === 'secret')
                            <input id="{{ $field->column }}" name="{{ $field->column }}" type="password" value="" autocomplete="new-password" placeholder="{{ $storedValue !== null ? 'Configured - leave blank to preserve' : 'Not configured' }}" class="mt-1 w-full rounded-lg border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-800">
                            @if ($storedValue !== null)
                                <label class="mt-2 inline-flex items-center gap-2 text-sm"><input type="checkbox" name="clear_{{ $field->column }}" value="1"> Clear stored value</label>
                            @endif
                        @elseif ($field->control === 'upload')
                            <input id="{{ $field->column }}" name="{{ $field->column }}" type="file" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm">
                            @if ($storedValue)
                                <label class="mt-2 inline-flex items-center gap-2 text-sm"><input type="checkbox" name="remove_site_logo" value="1"> Remove current logo</label>
                            @endif
                        @elseif ($field->control === 'multiselect')
                            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                                @foreach ($field->options as $optionValue => $optionLabel)
                                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $field->column }}[]" value="{{ $optionValue }}" @checked(in_array($optionValue, old($field->column, $selectedCleanupRules), true))> {{ $optionLabel }}</label>
                                @endforeach
                            </div>
                        @elseif ($field->control === 'number-list')
                            <input id="{{ $field->column }}" name="{{ $field->column }}" type="text" value="{{ old($field->column, implode(',', $selectedColorExclusions)) }}" class="mt-1 w-full rounded-lg border-gray-300 bg-white font-mono dark:border-gray-700 dark:bg-gray-800">
                        @else
                            <input id="{{ $field->column }}" name="{{ $field->column }}" type="{{ $field->control === 'integer' ? 'number' : ($field->control === 'date' ? 'date' : ($field->control === 'url' ? 'url' : 'text')) }}" value="{{ $inputValue }}" class="mt-1 w-full rounded-lg border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-800">
                        @endif

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $field->help }}@if($field->unit) Unit: {{ $field->unit }}.@endif {{ $field->guidance }}</p>
                        @error($field->column)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>

            <div class="mt-8 flex justify-end">
                <button type="submit" class="rounded-lg bg-primary-600 px-5 py-2.5 font-medium text-white hover:bg-primary-700">Save {{ $domain->label() }} settings</button>
            </div>
        </form>
    </div>
@endsection
