<?php

namespace Blacklight;

use ReCaptcha\ReCaptcha;
use Blacklight\http\BasePage;

class Captcha
{
    /**
     * @var \Blacklight\http\BasePage
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
     * ReCaptcha Secret Key from the
     * settings database.
     *
     * @var bool|string
     */
    private $secretkey;

    /**
     * ReCaptcha instance if enabled.
     *
     * @var \ReCaptcha\ReCaptcha
     */
    private $recaptcha;

    /**
     * Contains the error output if ReCaptcha
     * validation fails.
     *
     * @var string|bool
     */
    private $error = false;

    /**
     * $_POST key for the user-supplied ReCaptcha response.
     */
    private const RECAPTCHA_POSTKEY = 'g-recaptcha-response';

    /**
     * Error key literals.
     */
    private const RECAPTCHA_ERROR_MISSING_SECRET = 'missing-input-secret';
    private const RECAPTCHA_ERROR_INVALID_SECRET = 'invalid-input-secret';
    private const RECAPTCHA_ERROR_MISSING_RESPONSE = 'missing-input-response';
    private const RECAPTCHA_ERROR_INVALID_RESPONSE = 'invalid-input-response';

    /**
     * Settings key literals.
     */
    public const RECAPTCHA_SETTING_SITEKEY = 'APIs.recaptcha.sitekey';
    public const RECAPTCHA_SETTING_SECRETKEY = 'APIs.recaptcha.secretkey';
    public const RECAPTCHA_SETTING_ENABLED = 'APIs.recaptcha.enabled';

    /**
     * Captcha constructor.
     *
     * @param $page
     *
     * @throws \Exception
     */
    public function __construct(&$page)
    {
        if (! $page instanceof BasePage) {
            throw new \InvalidArgumentException('Invalid Page variable provided');
        }
        $this->page = $page;
        if ($this->shouldDisplay()) {
            $this->page->smarty->assign('showCaptcha', true);
            $this->page->smarty->assign('sitekey', $this->sitekey);
            if ($this->page->isPostBack()) {
                if (! $this->processCaptcha($_POST, $_SERVER['REMOTE_ADDR'])) {
                    $this->page->smarty->assign('error', $this->getError());
                }
                //Delete this key after using so it doesn't interfere with normal $_POST
                //processing. (i.e. contact-us)
                unset($_POST[self::RECAPTCHA_POSTKEY]);
            }
        } else {
            $this->page->smarty->assign('showCaptcha', false);
        }
    }

    /**
     * If site admin setup keys properly,
     * allow display of recaptcha.
     *
     * @return bool
     * @throws \Exception
     */
    public function shouldDisplay(): bool
    {
        if ($this->_bootstrapCaptcha()) {
            return true;
        }

        return false;
    }

    /**
     * Return formatted error messages.
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Process the submitted captcha and validate.
     *
     * @param array  $response
     * @param string $ip
     *
     * @return bool
     */
    public function processCaptcha($response, $ip): bool
    {
        if (isset($response[self::RECAPTCHA_POSTKEY])) {
            $post_response = $response[self::RECAPTCHA_POSTKEY];
        } else {
            $post_response = '';
        }
        $verify_response = $this->recaptcha->verify($post_response, $ip);
        if (! $verify_response->isSuccess()) {
            $this->_handleErrors($verify_response->getErrorCodes());

            return false;
        }

        return true;
    }

    /**
     * Build formatted error string for output using
     * Google's reCaptcha error codes.
     *
     * @param array $codes
     */
    private function _handleErrors($codes): void
    {
        $rc_error = 'ReCaptcha Failed: ';
        foreach ($codes as $c) {
            switch ($c) {
                case self::RECAPTCHA_ERROR_MISSING_SECRET:
                    $rc_error .= 'Missing Secret Key';
                    break;
                case self::RECAPTCHA_ERROR_INVALID_SECRET:
                    $rc_error .= 'Invalid Secret Key';
                    break;
                case self::RECAPTCHA_ERROR_MISSING_RESPONSE:
                    $rc_error .= 'No Response!';
                    break;
                case self::RECAPTCHA_ERROR_INVALID_RESPONSE:
                    $rc_error .= 'Invalid response! You are a bot!';
                    break;
                default:
                    $rc_error .= 'Unknown Error!';
            }
        }
        $this->error = $rc_error;
    }

    /**
     * Instantiate the ReCaptcha library and store it.
     * Return bool on success/failure.
     *
     * @return bool
     * @throws \Exception
     */
    private function _bootstrapCaptcha(): bool
    {
        if ($this->recaptcha instanceof ReCaptcha) {
            return true;
        }
        if (env('RECAPTCHA_ENABLED') === true) {
            $this->sitekey = env('RECAPTCHA_SITEKEY');
            $this->secretkey = env('RECAPTCHA_SECRETKEY');
            if ($this->sitekey !== '' && $this->secretkey !== '') {
                $this->recaptcha = new ReCaptcha($this->secretkey);

                return true;
            }
        }

        return false;
    }
}
