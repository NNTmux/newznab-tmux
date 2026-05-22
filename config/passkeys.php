<?php

declare(strict_types=1);

use App\Actions\Passkeys\ConfigureCeremonyStepManagerFactoryAction;
use App\Actions\Passkeys\FindPasskeyToAuthenticateAction;
use App\Actions\Passkeys\GeneratePasskeyAuthenticationOptionsAction;
use App\Actions\Passkeys\GeneratePasskeyRegisterOptionsAction;
use App\Models\User;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Passkey;

return [
    /*
     * After a successful authentication attempt using a passkey
     * we'll redirect to this URL.
     */
    'redirect_to_after_login' => '/',

    /*
     * These class are responsible for performing core tasks regarding passkeys.
     * You can customize them by creating a class that extends the default, and
     * by specifying your custom class name here.
     */
    'actions' => [
        'generate_passkey_register_options' => GeneratePasskeyRegisterOptionsAction::class,
        'store_passkey' => StorePasskeyAction::class,
        'generate_passkey_authentication_options' => GeneratePasskeyAuthenticationOptionsAction::class,
        'find_passkey' => FindPasskeyToAuthenticateAction::class,
        'configure_ceremony_step_manager_factory' => ConfigureCeremonyStepManagerFactoryAction::class,
    ],

    /*
     * These properties will be used to generate the passkey.
     */
    'relying_party' => [
        'name' => config('app.name'),
        'id' => env(
            'PASSKEY_RELYING_PARTY_ID',
            parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'
        ),
        'icon' => null,
    ],

    /*
     * Controls the WebAuthn `authenticatorSelection` ceremony parameters that are
     * sent to the browser when a user is registering a new passkey.
     *
     * - `authenticator_attachment` accepts: null (no preference - allows both
     *   Windows Hello / Touch ID / password managers AND roaming FIDO2 keys),
     *   "platform" (Windows Hello / Touch ID / Android only) or
     *   "cross-platform" (only roaming/hardware security keys).
     *   Leave it `null` so users on Windows domain machines see Windows Hello,
     *   password managers AND hardware keys in the browser picker.
     *
     * - `resident_key` accepts: "preferred" (recommended), "required" or
     *   "discouraged". Some locked-down Windows domain machines refuse to expose
     *   the platform authenticator when "required" is requested. Use
     *   "preferred" for the widest interoperability.
     *
     * - `user_verification` accepts: "preferred", "required" or "discouraged".
     */
    'authenticator_selection' => [
        'authenticator_attachment' => env('PASSKEY_AUTHENTICATOR_ATTACHMENT'), // null = no preference
        'resident_key' => env('PASSKEY_RESIDENT_KEY', 'preferred'),
        'user_verification' => env('PASSKEY_USER_VERIFICATION', 'preferred'),
    ],

    /*
     * WebAuthn Level 3 client hints. Modern Chromium based browsers (including
     * Edge on Windows) use these to render a richer credential chooser that
     * lists Windows Hello, mobile (hybrid/QR) and security keys side by side.
     *
     * Allowed values: "client-device", "hybrid", "security-key".
     */
    'hints' => [
        'client-device',
        'hybrid',
        'security-key',
    ],

    /*
     * Whether to request the `credProps` WebAuthn extension. It tells the
     * server (via the browser response) whether the created credential is a
     * discoverable / resident key. Safe to leave enabled.
     */
    'request_cred_props_extension' => true,

    /*
     * The models used by the package.
     *
     * You can override this by specifying your own models.
     */
    'models' => [
        'passkey' => Passkey::class,
        'authenticatable' => env('AUTH_MODEL', User::class),
    ],
];
