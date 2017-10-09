<?php

namespace nntmux;

use Vinkla\Recaptcha\Recaptcha;
use Vinkla\Recaptcha\RecaptchaException;

class Captcha
{
    /**
     * Smarty $page.
     *
     * @var \Page
     */
    private $page;

    /**
     * ReCaptcha Site Key from the
     * settings database.
     *
     * @var bool|string
     */
    private $sitekey;

    /**
     * @var \Vinkla\Recaptcha\Recaptcha
     */
    private $recaptcha;

    /**
     * Construct and decide whether to show the captcha or not.
     *
     * @note Passing $page by reference to setup smarty vars easily.
     *
     * @param \Page $page
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function __construct(&$page)
    {
        if (! $page instanceof \Page) {
            throw new \InvalidArgumentException('Invalid Page variable provided');
        }

        $this->page = $page;
        $this->sitekey = env('RECAPTCHA_SITEKEY');
        $this->page->smarty->assign('sitekey', $this->sitekey);
        $this->error = '';
        $this->recaptcha = new Recaptcha(env('RECAPTCHA_SECRETKEY'));

        if (! $this->processCaptcha()) {
            $this->page->smarty->assign('error', $this->error);
        }
    }

    /**
     * Process the submitted captcha and validate.
     *
     *
     * @return bool
     */
    public function processCaptcha(): bool
    {
        try {
            $this->recaptcha->verify('g-recaptcha-response');
        } catch (RecaptchaException $e) {
            $this->error = $e->getMessage();
            return false;
        }
        return true;
    }
}
