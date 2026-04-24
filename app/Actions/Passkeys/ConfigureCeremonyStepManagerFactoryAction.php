<?php

declare(strict_types=1);

namespace App\Actions\Passkeys;

use App\Support\Passkeys\RelyingPartyIdResolver;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;

final class ConfigureCeremonyStepManagerFactoryAction extends \Spatie\LaravelPasskeys\Actions\ConfigureCeremonyStepManagerFactoryAction
{
    public function execute(): CeremonyStepManagerFactory
    {
        $factory = parent::execute();

        $request = request();
        $rpId = RelyingPartyIdResolver::resolve($request);
        $origins = [$request->getSchemeAndHttpHost()];

        // HTTPS origins for normal environments.
        $origins[] = "https://{$rpId}";

        // Local development allowance for localhost over HTTP.
        if ($rpId === 'localhost') {
            $origins[] = 'http://localhost';
        }

        $factory->setAllowedOrigins(array_values(array_unique($origins)));

        return $factory;
    }
}
