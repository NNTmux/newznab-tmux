@extends('layouts.admin')

@section('content')
    @php
        $errors ??= new \Illuminate\Support\ViewErrorBag();
        $controlClasses = 'w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-blue-400 dark:focus:ring-blue-400';
        $checkboxClasses = 'h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-blue-400 dark:focus:ring-blue-400';
    @endphp
    <div class="settings-page space-y-6">
        <x-admin.card>
            <x-admin.page-header
                :title="$domain->label().' settings'"
                icon="fas fa-sliders-h"
                subtitle="Validated, typed configuration for this domain."
            />

            <nav class="settings-page__domain-nav surface-panel-alt flex flex-wrap gap-2 border-b px-4 py-4 sm:px-6" aria-label="Configuration domains">
                @foreach ($domains as $catalogDomain)
                    <a href="{{ route('admin.settings.show', ['domain' => $catalogDomain->value]) }}"
                       @if ($catalogDomain === $domain) aria-current="page" @endif
                       @class([
                           'inline-flex min-h-11 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900',
                           'bg-blue-600 text-white shadow-sm hover:bg-blue-700' => $catalogDomain === $domain,
                           'bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600' => $catalogDomain !== $domain,
                       ])>
                        {{ $catalogDomain->label() }}
                    </a>
                @endforeach
            </nav>

            @if (isset($errors) && $errors->any())
                <div class="mx-4 mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-700 dark:bg-red-900/30 dark:text-red-200 sm:mx-6" role="alert">
                    <p class="font-semibold"><i class="fas fa-exclamation-circle mr-2" aria-hidden="true"></i>Nothing was saved. Correct the highlighted values.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="post" action="{{ route('admin.settings.update', ['domain' => $domain->value]) }}" enctype="multipart/form-data" class="settings-page__form">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-6 p-4 sm:p-6 md:grid-cols-2">
                    @foreach ($fields as $field)
                        @php
                            $storedValue = $configuration->getAttribute($field->column);
                            if ($storedValue instanceof \BackedEnum) {
                                $storedValue = $storedValue->value;
                            }
                            $inputValue = old($field->column, $storedValue);
                            if ($field->control === 'boolean' && is_bool($inputValue)) {
                                $inputValue = (int) $inputValue;
                            }
                            if ($field->control === 'date' && $inputValue instanceof \DateTimeInterface) {
                                $inputValue = $inputValue->format('Y-m-d');
                            }
                            $helpId = $field->column.'_help';
                            $errorId = $field->column.'_error';
                        @endphp
                        <div @class(['md:col-span-2' => in_array($field->control, ['textarea', 'rich-text', 'multiselect'], true)]) @if ($field->control === 'rich-text') x-data="tinyMceEditor" @endif>
                            <label for="{{ $field->column }}" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $field->label }}</label>

                            @if ($field->control === 'boolean' || $field->control === 'enum')
                                <select id="{{ $field->column }}" name="{{ $field->column }}" class="{{ $controlClasses }}" aria-describedby="{{ $helpId }}" @error($field->column) aria-invalid="true" aria-errormessage="{{ $errorId }}" @enderror>
                                    @foreach ($field->options as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}" @selected((string) $inputValue === (string) $optionValue)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @elseif ($field->control === 'textarea' || $field->control === 'rich-text' || $field->control === 'regex')
                                <textarea id="{{ $field->column }}" name="{{ $field->column }}" rows="{{ $field->control === 'rich-text' ? 10 : 4 }}" class="{{ $controlClasses }} text-sm {{ $field->control === 'rich-text' ? 'tinymce-editor' : 'font-mono' }}" aria-describedby="{{ $helpId }}" @error($field->column) aria-invalid="true" aria-errormessage="{{ $errorId }}" @enderror>{{ $inputValue }}</textarea>
                            @elseif ($field->control === 'bytes')
                                <div class="flex gap-2">
                                    <input id="{{ $field->column }}" name="{{ $field->column }}" type="number" min="0" step="0.01" value="{{ old($field->column, $sizeValues[$field->column]['value']) }}" class="{{ $controlClasses }}" aria-describedby="{{ $helpId }}" @error($field->column) aria-invalid="true" aria-errormessage="{{ $errorId }}" @enderror>
                                    <select name="{{ $field->column }}_unit" class="w-28 shrink-0 rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-blue-400 dark:focus:ring-blue-400" aria-label="{{ $field->label }} unit">
                                        @foreach ($sizeUnits as $unit)
                                            <option value="{{ $unit }}" @selected(old($field->column.'_unit', $sizeValues[$field->column]['unit']) === $unit)>{{ $unit }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif ($field->control === 'secret')
                                <input id="{{ $field->column }}" name="{{ $field->column }}" type="password" value="" autocomplete="new-password" placeholder="{{ $storedValue !== null ? 'Configured - leave blank to preserve' : 'Not configured' }}" class="{{ $controlClasses }}" aria-describedby="{{ $helpId }}" @error($field->column) aria-invalid="true" aria-errormessage="{{ $errorId }}" @enderror>
                                @if ($storedValue !== null)
                                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="clear_{{ $field->column }}" value="1" class="{{ $checkboxClasses }}"> Clear stored value</label>
                                @endif
                            @elseif ($field->control === 'upload')
                                <div class="rounded-lg border-2 border-dashed border-gray-300 p-4 dark:border-gray-600">
                                    <input id="{{ $field->column }}" name="{{ $field->column }}" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:text-gray-400 dark:file:bg-blue-900 dark:file:text-blue-200" aria-describedby="{{ $helpId }}" @error($field->column) aria-invalid="true" aria-errormessage="{{ $errorId }}" @enderror>
                                    @if ($storedValue)
                                        <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="remove_site_logo" value="1" class="{{ $checkboxClasses }}"> Remove current logo</label>
                                    @endif
                                </div>
                            @elseif ($field->control === 'multiselect')
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4" aria-describedby="{{ $helpId }}">
                                    @foreach ($field->options as $optionValue => $optionLabel)
                                        <label class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"><input type="checkbox" name="{{ $field->column }}[]" value="{{ $optionValue }}" class="{{ $checkboxClasses }}" @checked(in_array($optionValue, old($field->column, $selectedCleanupRules), true))> {{ $optionLabel }}</label>
                                    @endforeach
                                </div>
                            @elseif ($field->control === 'number-list')
                                <input id="{{ $field->column }}" name="{{ $field->column }}" type="text" value="{{ old($field->column, implode(',', $selectedColorExclusions)) }}" class="{{ $controlClasses }} font-mono" aria-describedby="{{ $helpId }}" @error($field->column) aria-invalid="true" aria-errormessage="{{ $errorId }}" @enderror>
                            @else
                                <input id="{{ $field->column }}" name="{{ $field->column }}" type="{{ $field->control === 'integer' ? 'number' : ($field->control === 'date' ? 'date' : ($field->control === 'url' ? 'url' : 'text')) }}" value="{{ $inputValue }}" class="{{ $controlClasses }}" aria-describedby="{{ $helpId }}" @error($field->column) aria-invalid="true" aria-errormessage="{{ $errorId }}" @enderror>
                            @endif

                            <p id="{{ $helpId }}" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $field->help }}@if($field->unit) Unit: {{ $field->unit }}.@endif {{ $field->guidance }}</p>
                            @error($field->column)<p id="{{ $errorId }}" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>

                <div class="settings-page__action-bar surface-panel-alt flex justify-end border-t px-4 py-4 sm:px-6">
                    <x-admin.button type="submit" icon="fas fa-save">Save {{ $domain->label() }} settings</x-admin.button>
                </div>
            </form>
        </x-admin.card>
    </div>
@endsection
