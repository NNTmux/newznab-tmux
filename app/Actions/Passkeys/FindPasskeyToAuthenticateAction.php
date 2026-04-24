<?php

declare(strict_types=1);

namespace App\Actions\Passkeys;

use App\Support\Passkeys\RelyingPartyIdResolver;
use Spatie\LaravelPasskeys\Actions\ConfigureCeremonyStepManagerFactoryAction;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction as BaseFindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\LaravelPasskeys\Support\Config;
use Spatie\LaravelPasskeys\Support\CredentialRecordConverter;
use Throwable;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

final class FindPasskeyToAuthenticateAction extends BaseFindPasskeyToAuthenticateAction
{
    protected function determinePublicKeyCredentialSource(
        PublicKeyCredential $publicKeyCredential,
        PublicKeyCredentialRequestOptions $passkeyOptions,
        Passkey $passkey,
    ): ?PublicKeyCredentialSource {
        $configureCeremonyStepManagerFactoryAction = Config::getAction(
            'configure_ceremony_step_manager_factory',
            ConfigureCeremonyStepManagerFactoryAction::class
        );
        $csmFactory = $configureCeremonyStepManagerFactoryAction->execute();
        $requestCsm = $csmFactory->requestCeremony();

        $host = RelyingPartyIdResolver::resolve();

        try {
            $validator = AuthenticatorAssertionResponseValidator::create($requestCsm);

            $publicKeyCredentialSource = $validator->check(
                $passkey->data,
                $publicKeyCredential->response,
                $passkeyOptions,
                $host,
                null,
            );
        } catch (Throwable) {
            return null;
        }

        return CredentialRecordConverter::toPublicKeyCredentialSource($publicKeyCredentialSource);
    }
}
