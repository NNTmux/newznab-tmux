@extends('layouts.main')

@push('styles')
<style>
    /* Override the container constraint for contact page */
    .contact-page-container {
        max-width: 75% !important;
        width: 75% !important;
    }
    @media (min-width: 1536px) {
        .contact-page-container {
            max-width: 1200px !important;
        }
    }
</style>
@endpush

@section('content')
<div class="w-full contact-page-container mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6">
        <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700 rounded-t-lg">
            <h4 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-0">Contact Us</h4>
            <nav class="mt-2" aria-label="breadcrumb">
                <ol class="flex space-x-2 text-sm">
                    <li><a href="{{ url($site->home_link ?? '/') }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800">Home</a></li>
                    <li class="text-gray-500">/</li>
                    <li class="text-gray-600">Contact</li>
                </ol>
            </nav>
        </div>

        <div class="px-6 py-8 lg:px-12 lg:py-10">
            @if(isset($msg) && $msg != '')
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
                    <i class="fa fa-check-circle mr-2"></i>{!! $msg !!}
                </div>
            @endif

            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
                    <i class="fa fa-check-circle mr-2"></i>{{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
                    <i class="fa fa-exclamation-circle mr-2"></i>
                    <ul class="mb-0 list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-6">
                <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-3">Have a question?</h3>
                <p class="text-gray-600">Don't hesitate to send us a message. Our team will be happy to help you.</p>
            </div>

            {!! Form::open(['url' => route('contact-us'), 'method' => 'POST']) !!}
                <div class="mb-6">
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 bg-gray-50 dark:bg-gray-900 border border-r-0 border-gray-300 dark:border-gray-600 rounded-l-md">
                            <i class="fas fa-user text-gray-500"></i>
                        </span>
                        <input id="username" type="text" name="username" value="{{ old('username') }}"
                               placeholder="Your name"
                               class="flex-1 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-r-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('username') border-red-500 @enderror"
                               required>
                    </div>
                    @error('username')
                        <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="useremail" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 bg-gray-50 dark:bg-gray-900 border border-r-0 border-gray-300 dark:border-gray-600 rounded-l-md">
                            <i class="fas fa-envelope text-gray-500"></i>
                        </span>
                        <input type="email" id="useremail" name="useremail" value="{{ old('useremail') }}"
                               placeholder="Your email address"
                               class="flex-1 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-r-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('useremail') border-red-500 @enderror"
                               required>
                    </div>
                    @error('useremail')
                        <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="comment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Message <span class="text-red-500">*</span>
                    </label>
                    <div class="flex">
                        <span class="inline-flex items-start px-3 pt-3 bg-gray-50 dark:bg-gray-900 border border-r-0 border-gray-300 dark:border-gray-600 rounded-tl-md rounded-bl-md">
                            <i class="fas fa-comment text-gray-500"></i>
                        </span>
                        <textarea rows="7" name="comment" id="comment"
                                  placeholder="Your message"
                                  class="flex-1 block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-r-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('comment') border-red-500 @enderror"
                                  required>{{ old('comment') }}</textarea>
                    </div>
                    @error('comment')
                        <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                @if(config('captcha.enabled') == true && !empty(config('captcha.sitekey')) && !empty(config('captcha.secret')))
                    <div class="mb-6 flex justify-center">
                        {!! NoCaptcha::display() !!}
                    </div>
                    @error('g-recaptcha-response')
                        <div class="text-red-500 text-center text-sm mb-6">{{ $message }}</div>
                    @enderror
                @endif

                <div class="w-full">
                    <button type="submit" class="w-full bg-green-600 dark:bg-green-700 hover:bg-green-700 dark:hover:bg-green-800 text-white font-semibold py-3 px-6 rounded-lg transition duration-150 ease-in-out flex items-center justify-center text-lg">
                        <i class="fas fa-paper-plane mr-2"></i>Send Message
                    </button>
                </div>
            {!! Form::close() !!}
        </div>

        <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-t border-gray-200 dark:border-gray-700 rounded-b-lg text-center">
            <p class="mb-0 text-gray-600">
                <i class="fas fa-info-circle mr-1"></i>
                We typically respond to messages within 1-2 business days.
            </p>
        </div>
    </div>
</div>

@if(config('captcha.enabled') == true && !empty(config('captcha.sitekey')) && !empty(config('captcha.secret')))
    {!! NoCaptcha::renderJs() !!}
@endif
@endsection

