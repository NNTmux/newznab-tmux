<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Controller specifically for handling profile security operations
 * like 2FA management with no dependencies on other profile functions
 */
class ProfileSecurityController extends BasePageController
{
    /**
     * Disable 2FA for the authenticated user from the profile page
     * This is separate from the main profile edit functionality
     *
     * @return JsonResponse|RedirectResponse
     */
    public function disable2fa(Request $request)
    {
        // Simple validation - only password is required
        $validated = $request->validate([
            'current_password' => 'required',
        ]);

        // Check if password is correct
        if (! Hash::check($validated['current_password'], Auth::user()->password)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your password does not match. Please try again.',
                ]);
            }

            return redirect()
                ->to('profileedit#security')
                ->with('error_2fa', 'Your password does not match. Please try again.');
        }

        // Get the user and disable 2FA
        $user = Auth::user();
        if ($user->passwordSecurity) {
            $user->passwordSecurity->google2fa_enable = 0;
            $user->passwordSecurity->save();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => '2FA has been successfully disabled.',
                ]);
            }

            return redirect()
                ->to('profileedit#security')
                ->with('success_2fa', '2FA has been successfully disabled.');
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'No 2FA configuration found for this user.',
            ]);
        }

        return redirect()
            ->to('profileedit#security')
            ->with('error_2fa', 'No 2FA configuration found for this user.');
    }
}
