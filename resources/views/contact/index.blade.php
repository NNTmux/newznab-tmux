@extends('layouts.main')


@section('content')
<div class="w-full contact-page-container mx-auto">
    <x-page-header title="Contact us" eyebrow="We are here to help" description="Send a message to the site team and we will get back to you as soon as possible." icon="fas fa-message" />

    <div class="surface-panel rounded-xl shadow-sm mb-6">

        <div class="px-6 py-8 lg:px-12 lg:py-10">
            @if(isset($msg) && $msg != '')
                <div class="mb-6 p-4 bg-success-50 dark:bg-success-900/30 border border-success-200 dark:border-success-700 text-success-800 dark:text-success-200 rounded-lg">
                    <i aria-hidden="true" class="fa-solid fa-check-circle mr-2"></i>{!! $msg !!}
                </div>
            @endif

            @if(session('success'))
                <div class="mb-6 p-4 bg-success-50 dark:bg-success-900/30 border border-success-200 dark:border-success-700 text-success-800 dark:text-success-200 rounded-lg">
                    <i aria-hidden="true" class="fa-solid fa-check-circle mr-2"></i>{{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-red-800 dark:text-red-200 rounded-lg">
                    <i aria-hidden="true" class="fa-solid fa-exclamation-circle mr-2"></i>
                    <ul class="mb-0 list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-6">
                <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-3">Have a question?</h3>
                <p class="text-gray-600 dark:text-gray-400">Don't hesitate to send us a message. Our team will be happy to help you.</p>
            </div>

            <form method="POST" action="{{ route('contact-us') }}">
                @csrf
                <div class="mb-6">
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 bg-gray-50 dark:bg-gray-900 border border-r-0 border-gray-300 dark:border-gray-600 rounded-l-md">
                            <i aria-hidden="true" class="fas fa-user text-gray-500 dark:text-gray-400"></i>
                        </span>
                        <input id="username" type="text" name="username" value="{{ old('username') }}"
                               placeholder="Your name"
                               class="flex-1 block w-full px-4 py-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-r-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder-gray-400 dark:placeholder-gray-500 @error('username') border-red-500 @enderror"
                               required>
                    </div>
                    @error('username')
                        <div class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="useremail" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 bg-gray-50 dark:bg-gray-900 border border-r-0 border-gray-300 dark:border-gray-600 rounded-l-md">
                            <i aria-hidden="true" class="fas fa-envelope text-gray-500 dark:text-gray-400"></i>
                        </span>
                        <input type="email" id="useremail" name="useremail" value="{{ old('useremail') }}"
                               placeholder="Your email address"
                               class="flex-1 block w-full px-4 py-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-r-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder-gray-400 dark:placeholder-gray-500 @error('useremail') border-red-500 @enderror"
                               required>
                    </div>
                    @error('useremail')
                        <div class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="comment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Message <span class="text-red-500">*</span>
                    </label>
                    <div class="flex">
                        <span class="inline-flex items-start px-3 pt-3 bg-gray-50 dark:bg-gray-900 border border-r-0 border-gray-300 dark:border-gray-600 rounded-tl-md rounded-bl-md">
                            <i aria-hidden="true" class="fas fa-comment text-gray-500 dark:text-gray-400"></i>
                        </span>
                        <textarea rows="7" name="comment" id="comment"
                                  placeholder="Your message"
                                  class="flex-1 block w-full px-4 py-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-r-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder-gray-400 dark:placeholder-gray-500 @error('comment') border-red-500 @enderror"
                                  required>{{ old('comment') }}</textarea>
                    </div>
                    @error('comment')
                        <div class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                @if(\App\Support\CaptchaHelper::isEnabled())
                    <div class="mb-6 flex justify-center">
                        {!! \App\Support\CaptchaHelper::display() !!}
                    </div>
                    @error(\App\Support\CaptchaHelper::getResponseFieldName())
                        <div class="text-red-500 dark:text-red-400 text-center text-sm mb-6">{{ $message }}</div>
                    @enderror
                @endif

                <div class="w-full">
                    <button type="submit" class="w-full bg-success-600 dark:bg-success-700 hover:bg-success-700 dark:hover:bg-success-800 text-white font-semibold py-3 px-6 rounded-lg transition duration-150 ease-in-out flex items-center justify-center text-lg">
                        <i aria-hidden="true" class="fas fa-paper-plane mr-2"></i>Send Message
                    </button>
                </div>
            </form>
        </div>

        <div class="surface-panel-alt px-6 py-4 border-t rounded-b-lg text-center">
            <p class="mb-0 text-gray-600 dark:text-gray-400">
                <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>
                We typically respond to messages within 1-2 business days.
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @if(\App\Support\CaptchaHelper::isEnabled())
        {!! \App\Support\CaptchaHelper::renderJs() !!}
    @endif
@endpush
