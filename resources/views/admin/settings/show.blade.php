@extends('layouts.admin')

@section('content')
    @php
        $errors ??= new \Illuminate\Support\ViewErrorBag();
        $controlClasses = 'w-full rounded-control border border-line bg-surface px-3 py-2 text-fg shadow-raised focus:border-primary-500 focus:ring-primary-500 aria-invalid:border-danger-500 aria-invalid:focus:ring-danger-500 dark:[color-scheme:dark]';
        $checkboxClasses = 'h-4 w-4 rounded border-line bg-surface text-accent focus:ring-primary-500';
    @endphp
    <div class="settings-page space-y-6">
        <x-admin.page-header
            :title="$domain->label().' settings'"
            icon="fas fa-sliders-h"
            subtitle="Validated, typed configuration for this domain."
        />
        <nav class="settings-page__domain-nav surface-panel-alt flex flex-wrap gap-2 rounded-card border p-2" aria-label="Configuration domains">
            @foreach ($domains as $catalogDomain)
                <a href="{{ route('admin.settings.show', ['domain' => $catalogDomain->value]) }}"
                   @if ($catalogDomain === $domain) aria-current="page" @endif
                   @class([
                       'inline-flex min-h-10 items-center justify-center rounded-control px-4 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                       'bg-accent text-on-accent shadow-raised hover:bg-accent-hover' => $catalogDomain === $domain,
                       'text-fg hover:bg-surface-hover' => $catalogDomain !== $domain,
                   ])>
                    {{ $catalogDomain->label() }}
                </a>
            @endforeach
        </nav>

        <x-form-errors :title="__('Nothing was saved. Correct the highlighted values.')" />

        <form method="post" action="{{ route('admin.settings.update', ['domain' => $domain->value]) }}" enctype="multipart/form-data" class="settings-page__form space-y-6">
            @csrf
            @method('PUT')

            @foreach ($sections as $sectionTitle => $sectionFields)
                <section class="admin-card surface-panel rounded-card border p-5 shadow-raised sm:p-6" aria-labelledby="settings-section-{{ $loop->index }}">
                    <h2 id="settings-section-{{ $loop->index }}" class="text-base font-semibold text-fg">{{ $sectionTitle }}</h2>
                    <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2">
                    @foreach ($sectionFields as $field)
                        @php
                            $storedValue = $configurationValues[$field->column] ?? null;
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
                            @if ($field->control === 'multiselect')
                                {{-- A checkbox group has no single control to point at: the group is labelled instead. --}}
                                <span id="{{ $field->column }}_label" class="mb-1.5 block text-sm font-semibold text-fg">{{ $field->label }}</span>
                            @else
                                <label for="{{ $field->column }}" class="mb-1.5 block text-sm font-semibold text-fg">{{ $field->label }}</label>
                            @endif

                            @if ($field->control === 'boolean')
                                <div class="flex min-h-11 items-center">
                                    <x-toggle :name="$field->column" :id="$field->column" :checked="(string) $inputValue === '1'" aria-describedby="{{ $helpId }}" />
                                </div>
                            @elseif ($field->control === 'enum')
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
                                    <select name="{{ $field->column }}_unit" class="w-28 shrink-0 rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-primary-400 dark:focus:ring-primary-400" aria-label="{{ $field->label }} unit">
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
                                    <input id="{{ $field->column }}" name="{{ $field->column }}" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-link hover:file:bg-primary-100 dark:text-gray-400 dark:file:bg-primary-900 dark:file:text-primary-200" aria-describedby="{{ $helpId }}" @error($field->column) aria-invalid="true" aria-errormessage="{{ $errorId }}" @enderror>
                                    @if ($storedValue)
                                        <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="remove_site_logo" value="1" class="{{ $checkboxClasses }}"> Remove current logo</label>
                                    @endif
                                </div>
                            @elseif ($field->control === 'multiselect')
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4" role="group" aria-labelledby="{{ $field->column }}_label" aria-describedby="{{ $helpId }}">
                                    @foreach ($field->options as $optionValue => $optionLabel)
                                        <label class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"><input type="checkbox" name="{{ $field->column }}[]" value="{{ $optionValue }}" class="{{ $checkboxClasses }}" @checked(in_array($optionValue, old($field->column, $selectedCleanupRules), true))> {{ $optionLabel }}</label>
                                    @endforeach
                                </div>
                            @elseif ($field->control === 'number-list')
                                <input id="{{ $field->column }}" name="{{ $field->column }}" type="text" value="{{ old($field->column, implode(',', $selectedColorExclusions)) }}" class="{{ $controlClasses }} font-mono" aria-describedby="{{ $helpId }}" @error($field->column) aria-invalid="true" aria-errormessage="{{ $errorId }}" @enderror>
                            @else
                                <input id="{{ $field->column }}" name="{{ $field->column }}" type="{{ $field->control === 'integer' ? 'number' : ($field->control === 'date' ? 'date' : ($field->control === 'url' ? 'url' : 'text')) }}" value="{{ $inputValue }}" class="{{ $controlClasses }}" aria-describedby="{{ $helpId }}" @error($field->column) aria-invalid="true" aria-errormessage="{{ $errorId }}" @enderror>
                            @endif

                            <p id="{{ $helpId }}" class="mt-1.5 text-xs leading-5 text-fg-muted">{{ $field->help }}@if($field->unit) Unit: {{ $field->unit }}.@endif {{ $field->guidance }}</p>
                            @error($field->column)<p id="{{ $errorId }}" class="mt-1 flex items-start gap-1.5 text-sm font-medium text-danger-600 dark:text-danger-400"><i aria-hidden="true" class="fa-solid fa-circle-exclamation mt-0.5"></i>{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    </div>
                </section>
            @endforeach

            <div class="settings-page__action-bar surface-panel-alt sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 rounded-card border px-4 py-3 shadow-overlay">
                <p class="text-sm text-fg-muted">{{ __('Changes apply to the :domain configuration only.', ['domain' => $domain->label()]) }}</p>
                <x-admin.button type="submit" icon="fas fa-save">Save {{ $domain->label() }} settings</x-admin.button>
            </div>
        </form>
    </div>
@endsection
