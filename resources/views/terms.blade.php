@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Terms and Conditions</h1>
                <nav aria-label="breadcrumb">
                    <ol class="flex text-sm text-gray-600 dark:text-gray-400">
                        <li><a href="{{ url('/') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Home</a></li>
                        <li class="mx-2">/</li>
                        <li class="text-gray-500 dark:text-gray-500">Terms and Conditions</li>
                    </ol>
                </nav>
            </div>

            <!-- Info Alert -->
            <div class="mx-6 mt-4 p-4 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg flex items-start">
                <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 text-xl mr-3 mt-0.5"></i>
                <div class="text-blue-800 dark:text-blue-200">
                    Please read our terms and conditions carefully. By using our services, you agree to be bound by these terms.
                </div>
            </div>

            <!-- Terms Content -->
            <div class="px-6 py-6">
                <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
                    @if($terms_content && $terms_content !== '<p>No terms and conditions have been set yet.</p>')
                        {!! $terms_content !!}
                    @else
                        <h2 class="text-xl font-semibold mb-4">1. Acceptance of Terms</h2>
                        <p class="mb-4">By accessing and using {{ config('app.name') }}, you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">2. Use License</h2>
                        <p class="mb-4">Permission is granted to temporarily access the data on {{ config('app.name') }} for personal, non-commercial use only. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
                        <ul class="list-disc pl-10 ml-4 mb-4">
                            <li>Modify or copy the data;</li>
                            <li>Use the data for any commercial purpose or for any public display;</li>
                            <li>Remove any copyright or other proprietary notations from the data;</li>
                            <li>Mirror the data on any other server.</li>
                        </ul>

                        <h2 class="text-xl font-semibold mb-4 mt-6">3. User Accounts</h2>
                        <p class="mb-4">To access certain features of the service, you may be required to create an account. You are responsible for:</p>
                        <ul class="list-disc pl-10 ml-4 mb-4">
                            <li>Maintaining the confidentiality of your account and password;</li>
                            <li>Restricting access to your computer and account;</li>
                            <li>All activities that occur under your account or password;</li>
                            <li>Ensuring that all information you provide is accurate and up to date.</li>
                        </ul>

                        <h2 class="text-xl font-semibold mb-4 mt-6">4. Acceptable Use</h2>
                        <p class="mb-4">You agree not to use the service to:</p>
                        <ul class="list-disc pl-10 ml-4 mb-4">
                            <li>Transmit any viruses, malware, or other harmful code;</li>
                            <li>Attempt to gain unauthorized access to any portion of the service;</li>
                            <li>Interfere with or disrupt the service or servers;</li>
                            <li>Use automated systems to access the service in a manner that sends more requests than humanly possible.</li>
                        </ul>

                        <h2 class="text-xl font-semibold mb-4 mt-6">5. Content and Copyright</h2>
                        <p class="mb-4">Users are solely responsible for the content they access through this service. {{ config('app.name') }} does not host, store, or control usenet content and disclaims all liability for such content.</p>
                        <p class="mb-4">We respect the intellectual property rights of others and expect users to do the same. We will respond to notices of alleged copyright infringement that comply with applicable law.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">6. Privacy</h2>
                        <p class="mb-4">Your use of {{ config('app.name') }} is also governed by our Privacy Policy. We collect and use information as described in our Privacy Policy.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">7. Disclaimer</h2>
                        <p class="mb-4">The data on {{ config('app.name') }} is provided on an 'as is' basis. {{ config('app.name') }} makes no warranties, expressed or implied, and hereby disclaims and negates all other warranties including, without limitation, implied warranties or conditions of merchantability, fitness for a particular purpose, or non-infringement of intellectual property or other violation of rights.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">8. Limitations</h2>
                        <p class="mb-4">In no event shall {{ config('app.name') }} be liable for any damages (including, without limitation, damages for loss of data or profit, or due to business interruption) arising out of the use or inability to use the data on {{ config('app.name') }}.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">9. Account Termination</h2>
                        <p class="mb-4">We reserve the right to terminate or suspend your account and access to the service immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">10. Changes to Terms</h2>
                        <p class="mb-4">{{ config('app.name') }} may revise these terms of service at any time without notice. By using this service you are agreeing to be bound by the then current version of these terms of service.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">11. Contact Information</h2>
                        <p class="mb-4">If you have any questions about these Terms and Conditions, please contact us through the appropriate channels provided on our website.</p>
                    @endif
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    Last updated: {{ now()->format('F j, Y') }}
                </span>
            </div>
        </div>
    </div>
</div>
@endsection

