<?php

declare(strict_types=1);

namespace App\Actions\Passkeys;

use App\Support\Passkeys\RelyingPartyIdResolver;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction as BaseGeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Support\Config;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRpEntity;

final class GeneratePasskeyRegisterOptionsAction extends BaseGeneratePasskeyRegisterOptionsAction
{
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

        $supportedAlgorithms = [
            ['type' => 'public-key', 'alg' => -7],   // ES256
            ['type' => 'public-key', 'alg' => -257], // RS256
        ];

        // Different serializer/browser integrations can use either shape:
        // - options.pubKeyCredParams (WebAuthn JSON)
        // - options.publicKey.pubKeyCredParams (navigator.credentials.create payload)
        // Enforce valid algorithms for both to prevent "alg undefined" errors.
        $decoded['pubKeyCredParams'] = $supportedAlgorithms;

        if (isset($decoded['publicKey']) && is_array($decoded['publicKey'])) {
            $decoded['publicKey']['pubKeyCredParams'] = $supportedAlgorithms;
        }

        return json_encode($decoded, JSON_THROW_ON_ERROR);
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
