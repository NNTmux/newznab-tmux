<?php

declare(strict_types=1);

namespace App\Actions\Passkeys;

use App\Support\Passkeys\RelyingPartyIdResolver;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction as BaseGeneratePasskeyAuthenticationOptionsAction;
use Spatie\LaravelPasskeys\Support\Serializer;
use Webauthn\PublicKeyCredentialRequestOptions;

final class GeneratePasskeyAuthenticationOptionsAction extends BaseGeneratePasskeyAuthenticationOptionsAction
{
    public function execute(): string
    {
        $rpId = RelyingPartyIdResolver::resolve();

        $options = new PublicKeyCredentialRequestOptions(
            challenge: Str::random(),
            rpId: $rpId,
            allowCredentials: [],
        );

        $options = Serializer::make()->toJson($options);

        Session::flash('passkey-authentication-options', $options);

        return $options;
    }
}
