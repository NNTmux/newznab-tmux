{{-- Summary of validation errors for a form. Renders nothing when the form is valid. --}}
@props([
    'title' => __('Please fix the following:'),
    'bag' => 'default',
])

@if(isset($errors) && $errors->getBag($bag)->any())
    <x-alert tone="danger" :title="$title" {{ $attributes }}>
        <ul class="mt-1 list-disc space-y-0.5 pl-5">
            @foreach($errors->getBag($bag)->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
