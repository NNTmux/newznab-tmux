@extends('layouts.admin')

@section('content')

<div class="space-y-6" x-data="tinyMceEditor">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fas fa-cog mr-2"></i>{{ $title }}
            </h1>
        </div>

        <!-- Error Messages -->
        @if(!empty($error))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-red-800 dark:text-red-200">
                    <i class="fas fa-exclamation-circle mr-2"></i>{{ $error }}
                </p>
            </div>
        @endif

        <!-- Site Settings Form -->
        <form method="post" action="{{ url('admin/site-edit') }}" class="p-6">
            @csrf
            <input type="hidden" name="action" value="submit">

            <div class="space-y-8">
                @include('admin.site.sections.main-settings')

                @include('admin.site.sections.usenet-settings')

                @include('admin.site.sections.lookup-settings')

                @include('admin.site.sections.language-categorization-settings')

                @include('admin.site.sections.registration-settings')

                @include('admin.site.sections.login-session-settings')

                @include('admin.site.sections.password-settings')

                @include('admin.site.sections.additional-usenet-settings')

                @include('admin.site.sections.advanced-settings')

                @include('admin.site.sections.movie-trailer-settings')

                @include('admin.site.sections.postprocessing-settings')

                @include('admin.site.sections.nfo-processing-settings')

                @include('admin.site.sections.connection-settings')

                @include('admin.site.sections.developer-settings')

                <!-- Note about full settings -->
                <div class="bg-primary-50 border border-primary-200 rounded-lg p-4">
                    <p class="text-primary-800 text-sm">
                        <i class="fas fa-info-circle mr-2"></i>
                        This is a simplified settings page. For complete site configuration, please use the full settings management interface or edit settings directly in the database.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="px-6 py-2 bg-primary-600 dark:bg-primary-700 text-white rounded-lg hover:bg-primary-700">
                        <i class="fas fa-save mr-2"></i>Save Settings
                    </button>
                    <a href="{{ url('admin') }}" class="px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
