<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Passkeys\RelyingPartyIdResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\LaravelPasskeys\Support\Config;
use Throwable;

final class PasskeyManagementController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $generatePassKeyOptionsAction = Config::getAction(
            'generate_passkey_register_options',
            GeneratePasskeyRegisterOptionsAction::class
        );

        /** @var User $user */
        $user = Auth::user();
        $options = $generatePassKeyOptionsAction->execute($user);
        $request->session()->put('passkey-registration-options', $options);
        $request->session()->put('passkey-registration-name', $validated['name']);

        return response()->json([
            'ok' => true,
            'options' => json_decode($options, true, 512, JSON_THROW_ON_ERROR),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'passkey' => ['required', 'string'],
        ]);

        $storePasskeyAction = Config::getAction('store_passkey', StorePasskeyAction::class);

        try {
            /** @var User $user */
            $user = Auth::user();
            /** @var Passkey $passkey */
            $passkey = $storePasskeyAction->execute(
                $user,
                $validated['passkey'],
                $request->session()->pull('passkey-registration-options'),
                RelyingPartyIdResolver::resolve($request),
                ['name' => $validated['name']]
            );
        } catch (Throwable $exception) {
            Log::error('Passkey registration failed', [
                'user_id' => Auth::id(),
                'host' => $request->getHost(),
                'resolved_rp_id' => RelyingPartyIdResolver::resolve($request),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => __('passkeys::passkeys.error_something_went_wrong_generating_the_passkey'),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'passkey' => [
                'id' => $passkey?->id,
                'name' => $passkey?->name,
                'last_used_at' => $passkey?->last_used_at?->toIso8601String(),
                'created_at' => $passkey?->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(Request $request, int $passkey): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $deleted = $user->passkeys()->where('id', $passkey)->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => $deleted > 0]);
        }

        return back()->with(
            $deleted > 0 ? 'success' : 'error',
            $deleted > 0 ? 'Passkey deleted.' : 'Passkey not found.'
        );
    }
}
