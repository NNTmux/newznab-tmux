@extends('layouts.main')


@section('content')
<div class="w-full contact-page-container mx-auto">
    <x-page-header title="Contact us" eyebrow="We are here to help" description="Send a message to the site team and we will get back to you as soon as possible." icon="fas fa-message" />

    <div class="surface-panel rounded-xl shadow-sm mb-6">

        <div class="mx-auto w-full max-w-2xl px-6 py-8 lg:py-10">
            <x-form-errors class="mb-6" />

            <div class="mb-6">
                <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-3">Have a question?</h3>
                <p class="text-gray-600 dark:text-gray-400">Don't hesitate to send us a message. Our team will be happy to help you.</p>
            </div>

            <form method="POST" action="{{ route('contact-us') }}" class="space-y-6">
                @csrf
                <x-field label="Name" for="username" name="username" required>
                    <x-input id="username" name="username" :value="old('username', auth()->user()?->username)" placeholder="Your name" autocomplete="name" required />
                </x-field>

                @auth
                    {{-- Replies go to the account email; ticking the box reveals an optional other address (pure CSS, no JS). --}}
                    <div class="group/reply space-y-3">
                        <div class="rounded-control border border-line bg-surface-sunken px-4 py-3">
                            <p class="text-sm font-semibold text-fg">Reply email</p>
                            <p class="mt-1 flex items-center gap-2 text-sm text-fg-muted">
                                <i aria-hidden="true" class="fas fa-envelope"></i>
                                <span>We will reply to <span class="font-medium text-fg">{{ auth()->user()->email }}</span></span>
                            </p>
                            <div class="mt-3">
                                <x-checkbox name="use_different_email" value="1" :checked="(bool) old('use_different_email')" aria-controls="useremail-field">
                                    Use a different email address
                                </x-checkbox>
                            </div>
                        </div>
                        <x-field id="useremail-field" class="hidden group-has-[:checked]/reply:block" label="Different email" for="useremail" name="useremail" help="Optional. Only used to reply to this message.">
                            <x-input type="email" id="useremail" name="useremail" :value="old('useremail')" placeholder="other@example.com" autocomplete="email" />
                        </x-field>
                    </div>
                @else
                    <x-field label="Email" for="useremail" name="useremail" required>
                        <x-input type="email" id="useremail" name="useremail" :value="old('useremail')" placeholder="Your email address" autocomplete="email" required />
                    </x-field>
                @endauth

                <x-field label="Message" for="comment" name="comment" required>
                    <x-textarea id="comment" name="comment" rows="7" placeholder="Your message" required>{{ old('comment') }}</x-textarea>
                </x-field>

                @if(\App\Support\CaptchaHelper::isEnabled())
                    <div class="mb-6 flex justify-center">
                        {!! \App\Support\CaptchaHelper::display() !!}
                    </div>
                    @error(\App\Support\CaptchaHelper::getResponseFieldName())
                        <div class="text-danger-500 dark:text-danger-400 text-center text-sm mb-6">{{ $message }}</div>
                    @enderror
                @endif

                <div class="w-full">
                    <button type="submit" class="w-full bg-success-600 dark:bg-success-700 hover:bg-success-700 dark:hover:bg-success-800 text-white font-semibold py-2.5 px-6 rounded-control transition duration-150 ease-in-out flex items-center justify-center">
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
