@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Privacy Policy</h1>
                <nav aria-label="breadcrumb">
                    <ol class="flex text-sm text-gray-600 dark:text-gray-400">
                        <li><a href="{{ url('/') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Home</a></li>
                        <li class="mx-2">/</li>
                        <li class="text-gray-500 dark:text-gray-500">Privacy Policy</li>
                    </ol>
                </nav>
            </div>

            <!-- Info Alert -->
            <div class="mx-6 mt-4 p-4 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg flex items-start">
                <i class="fas fa-shield-alt text-blue-600 dark:text-blue-400 text-xl mr-3 mt-0.5"></i>
                <div class="text-blue-800 dark:text-blue-200">
                    Your privacy is important to us. This policy outlines how we collect, use, and protect your personal information.
                </div>
            </div>

            <!-- Privacy Policy Content -->
            <div class="px-6 py-6">
                <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
                    @if($privacy_content)
                        {!! $privacy_content !!}
                    @else
                        <h2 class="text-xl font-semibold mb-4">1. Information We Collect</h2>
                        <p class="mb-4">When you use {{ config('app.name') }}, we may collect the following types of information:</p>
                        <ul class="list-disc pl-10 ml-4 mb-4">
                            <li><strong>Account Information:</strong> Username, email address, and password when you register.</li>
                            <li><strong>Cookies:</strong> We use cookies to maintain your session and preferences.</li>
                        </ul>

                        <h2 class="text-xl font-semibold mb-4 mt-6">2. How We Use Your Information</h2>
                        <p class="mb-4">We use the collected information for the following purposes:</p>
                        <ul class="list-disc pl-10 ml-4 mb-4">
                            <li>To provide and maintain our service</li>
                            <li>To authenticate and authorize your access</li>
                            <li>To improve and personalize your experience</li>
                            <li>To communicate with you about service updates or account issues</li>
                            <li>To detect, prevent, and address technical issues or security threats</li>
                            <li>To comply with legal obligations</li>
                        </ul>

                        <h2 class="text-xl font-semibold mb-4 mt-6">3. Data Storage and Security</h2>
                        <p class="mb-4">We take the security of your personal information seriously:</p>
                        <ul class="list-disc pl-10 ml-4 mb-4">
                            <li>All passwords are securely hashed using industry-standard encryption</li>
                            <li>We implement appropriate technical and organizational measures to protect your data</li>
                        </ul>
                        <p class="mb-4">However, no method of transmission over the Internet or electronic storage is 100% secure. While we strive to use commercially acceptable means to protect your personal information, we cannot guarantee its absolute security.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">4. Information Sharing and Disclosure</h2>
                        <p class="mb-4">We do not sell, trade, or rent your personal information to third parties. We may share your information only in the following circumstances:</p>
                        <ul class="list-disc pl-10 ml-4 mb-4">
                            <li><strong>Legal Requirements:</strong> When required by law, court order, or government regulation</li>
                            <li><strong>Service Protection:</strong> To protect the rights, property, or safety of {{ config('app.name') }}, our users, or the public</li>
                        </ul>

                        <h2 class="text-xl font-semibold mb-4 mt-6">5. Data Retention</h2>
                        <p class="mb-4">We retain your personal information for as long as necessary to:</p>
                        <ul class="list-disc pl-10 ml-4 mb-4">
                            <li>Provide our services to you</li>
                            <li>Comply with legal obligations</li>
                            <li>Resolve disputes</li>
                        </ul>
                        <p class="mb-4">When you delete your account, we will delete or anonymize your personal information, unless we are required to retain it for legal purposes.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">6. Your Rights and Choices</h2>
                        <p class="mb-4">You have the following rights regarding your personal information:</p>
                        <ul class="list-disc pl-10 ml-4 mb-4">
                            <li><strong>Access:</strong> Request access to the personal information we hold about you</li>
                            <li><strong>Correction:</strong> Request correction of inaccurate or incomplete information</li>
                            <li><strong>Deletion:</strong> Request deletion of your account and associated data</li>
                        </ul>
                        <p class="mb-4">To exercise these rights, please contact us through your account settings or the contact methods provided on our website.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">7. Cookies and Tracking Technologies</h2>
                        <p class="mb-4">We use cookies and similar tracking technologies to:</p>
                        <ul class="list-disc pl-10 ml-4 mb-4">
                            <li>Maintain your login session</li>
                            <li>Remember your preferences (such as dark mode settings)</li>
                            <li>Analyze site usage and performance</li>
                        </ul>
                        <p class="mb-4">You can control cookie settings through your browser preferences. Note that disabling cookies may affect the functionality of our service.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">8. Third-Party Services</h2>
                        <p class="mb-4">Our service may contain links to third-party websites or integrate with third-party services. We are not responsible for the privacy practices of these third parties. We encourage you to review their privacy policies before providing any personal information.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">9. Children's Privacy</h2>
                        <p class="mb-4">Our service is not intended for users under the age of 18. We do not knowingly collect personal information from children. If we become aware that we have collected personal information from a child without parental consent, we will take steps to delete that information.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">10. International Data Transfers</h2>
                        <p class="mb-4">Your information may be transferred to and maintained on servers located outside of your jurisdiction. By using our service, you consent to the transfer of your information to countries that may have different data protection laws than your country of residence.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">11. Changes to This Privacy Policy</h2>
                        <p class="mb-4">We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date below. We encourage you to review this Privacy Policy periodically for any changes.</p>

                        <h2 class="text-xl font-semibold mb-4 mt-6">12. Contact Us</h2>
                        <p class="mb-4">If you have any questions about this Privacy Policy or our data practices, please contact us through the contact methods provided on our website.</p>
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
