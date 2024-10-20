<?php

namespace App\Policies;

use Spatie\Csp\Directive;
use Spatie\Csp\Policies\Basic;

class CustomCsp extends Basic
{
    /**
     * Create a new policy instance.
     */
    public function configure()
    {
        parent::configure();
        $this->addDirective(Directive::SCRIPT, 'self')
            ->addDirective(Directive::STYLE, 'self')
            ->addNonceForDirective(Directive::SCRIPT)
            ->addNonceForDirective(Directive::STYLE);
    }
}
