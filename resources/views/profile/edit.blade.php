@extends('layouts.main')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-800">Edit Profile</h1>
            <p class="text-gray-600 mt-1">Update your account settings and preferences</p>
        </div>

        <!-- Messages -->
        @if(session('success'))
            <div class="mx-6 mt-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-800 rounded">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif

        @if($success_2fa)
            <div class="mx-6 mt-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-800 rounded">
                <i class="fas fa-check-circle mr-2"></i>{{ $success_2fa }}
            </div>
        @endif

        @if($error || $error_2fa)
            <div class="mx-6 mt-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-800 rounded">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ $error ?: $error_2fa }}
            </div>
        @endif

        <!-- Form -->
        <form method="POST" action="{{ route('profileedit') }}" class="p-6 space-y-6">
            @csrf
            <input type="hidden" name="action" value="submit">

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror">
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">New Password (leave blank to keep current)</label>
                <input type="password" name="password" id="password"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('password') border-red-500 @enderror">
                <p class="mt-1 text-xs text-gray-500">Must contain at least 8 characters, including uppercase, lowercase, numbers and special characters</p>
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                <input type="password" name="password_confirmation" id="password_confirmation"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- View Preferences -->
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Cover View Preferences</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="movieview" value="1" {{ $user->movieview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">Movie Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="musicview" value="1" {{ $user->musicview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">Music Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="consoleview" value="1" {{ $user->consoleview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">Console Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="gameview" value="1" {{ $user->gameview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">Game Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="bookview" value="1" {{ $user->bookview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">Book Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="xxxview" value="1" {{ $user->xxxview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">XXX Covers</span>
                    </label>
                </div>
            </div>

            <!-- Category Permissions -->
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Category Permissions</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="viewmovies" value="1" {{ $user->movieview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">Movies</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewtv" value="1" {{ $user->can('view tv') ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">TV</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewaudio" value="1" {{ $user->musicview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">Audio</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewpc" value="1" {{ $user->gameview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">PC</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewconsole" value="1" {{ $user->consoleview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">Console</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewbooks" value="1" {{ $user->bookview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">Books</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewadult" value="1" {{ $user->xxxview ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">Adult</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewother" value="1" {{ $user->can('view other') ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700">Other</span>
                    </label>
                </div>
            </div>

            <!-- 2FA Section -->
            @if($google2fa_url)
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Two-Factor Authentication</h3>
                    <div class="bg-blue-50 rounded-lg p-4">
                        <p class="text-sm text-gray-700 mb-4">Scan this QR code with your authenticator app to enable 2FA:</p>
                        <div class="flex justify-center">
                            {!! $google2fa_url !!}
                        </div>
                        <p class="text-xs text-gray-600 mt-4 text-center">After scanning, visit the 2FA settings page to complete setup</p>
                    </div>
                </div>
            @endif

            <!-- Actions -->
            <div class="border-t border-gray-200 pt-6 flex items-center justify-between">
                <a href="{{ route('profile') }}" class="px-6 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </a>
                <div class="flex space-x-2">
                    <a href="{{ route('profileedit', ['action' => 'newapikey']) }}"
                       class="px-6 py-2 text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200 transition"
                       onclick="return confirm('Are you sure you want to generate a new API key?')">
                        <i class="fas fa-key mr-2"></i>New API Key
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

