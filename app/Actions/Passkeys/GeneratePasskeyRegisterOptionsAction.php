<?php

declare(strict_types=1);

namespace App\Actions\Passkeys;

use App\Support\Passkeys\RelyingPartyIdResolver;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction as BaseGeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Support\Config;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRpEntity;

final class GeneratePasskeyRegisterOptionsAction extends BaseGeneratePasskeyRegisterOptionsAction
{
    /**
     * WebAuthn algorithms we tell the browser we accept. Order matters:
     * the authenticator will pick the first algorithm it supports.
     *  -7   = ES256 (used by most FIDO2 hardware keys, Apple, Android)
     *  -257 = RS256 (used by Windows Hello / TPM-backed platform authenticators)
     *  -8   = EdDSA (used by some modern security keys & password managers)
     */
    private const SUPPORTED_ALGORITHMS = [
        ['type' => 'public-key', 'alg' => -7],
        ['type' => 'public-key', 'alg' => -257],
        ['type' => 'public-key', 'alg' => -8],
    ];

    public function execute(
        HasPasskeys $authenticatable,
        bool $asJson = true,
    ): string|PublicKeyCredentialCreationOptions {
        $options = parent::execute($authenticatable, $asJson);

        if (! $asJson || ! is_string($options)) {
            return $options;
        }

        $decoded = json_decode($options, true);
        if (! is_array($decoded)) {
            return $options;
        }

        // Different serializer/browser integrations can use either shape:
        // - options.pubKeyCredParams (WebAuthn JSON)
        // - options.publicKey.pubKeyCredParams (navigator.credentials.create payload)
        // Enforce valid algorithms (including RS256 for Windows Hello) for both
        // to prevent "alg undefined" errors and to allow Windows TPM-backed
        // platform authenticators to participate.
        $decoded['pubKeyCredParams'] = self::SUPPORTED_ALGORITHMS;

        // WebAuthn L3 hints help the browser show a richer chooser including
        // Windows Hello (client-device), phones (hybrid) and security keys.
        $hints = array_values(array_filter((array) config('passkeys.hints', [])));
        if ($hints !== []) {
            $decoded['hints'] = $hints;
        }

        // Request the credProps extension so we know whether a discoverable
        // (resident) key was actually created by the authenticator.
        if ((bool) config('passkeys.request_cred_props_extension', true)) {
            $decoded['extensions'] = array_merge(
                (array) ($decoded['extensions'] ?? []),
                ['credProps' => true],
            );
        }

        if (isset($decoded['publicKey']) && is_array($decoded['publicKey'])) {
            $decoded['publicKey']['pubKeyCredParams'] = self::SUPPORTED_ALGORITHMS;

            if ($hints !== []) {
                $decoded['publicKey']['hints'] = $hints;
            }

            if ((bool) config('passkeys.request_cred_props_extension', true)) {
                $decoded['publicKey']['extensions'] = array_merge(
                    (array) ($decoded['publicKey']['extensions'] ?? []),
                    ['credProps' => true],
                );
            }
        }

        return json_encode($decoded, JSON_THROW_ON_ERROR);
    }

    /**
     * Override the default selection criteria so that:
     *  - both platform authenticators (Windows Hello, Touch ID, password
     *    managers) and roaming/cross-platform FIDO2 security keys are offered;
     *  - a resident key is "preferred" rather than "required" — Windows
     *    domain-joined machines frequently refuse to expose Windows Hello when
     *    `required` is requested, which is why those users only saw the
     *    hardware-key dialog.
     */
    public function authenticatorSelection(): AuthenticatorSelectionCriteria
    {
        $attachment = config('passkeys.authenticator_selection.authenticator_attachment');
        $userVerification = (string) config(
            'passkeys.authenticator_selection.user_verification',
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
        );
        $residentKey = config(
            'passkeys.authenticator_selection.resident_key',
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
        );

        if (! in_array($attachment, AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENTS, true)) {
            $attachment = AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE;
        }

        if (! in_array($userVerification, AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENTS, true)) {
            $userVerification = AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED;
        }

        if (! in_array($residentKey, AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENTS, true)) {
            $residentKey = AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED;
        }

        return new AuthenticatorSelectionCriteria(
            authenticatorAttachment: $attachment,
            userVerification: $userVerification,
            residentKey: $residentKey,
        );
    }

    protected function relatedPartyEntity(): PublicKeyCredentialRpEntity
    {
        $rpId = RelyingPartyIdResolver::resolve();

        return new PublicKeyCredentialRpEntity(
            name: (string) Config::getRelyingPartyName(),
            id: $rpId,
            icon: Config::getRelyingPartyIcon(),
        );
    }
}
