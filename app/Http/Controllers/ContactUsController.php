<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactContactURequest;
use App\Jobs\SendContactUsEmail;

class ContactUsController extends BasePageController
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function contact(ContactContactURequest $request)
    {
        $this->setPreferences();

        if (config('captcha.enabled') === true && (! empty(config('captcha.secret')) && ! empty(config('captcha.sitekey')))) {
            $request->validate([
                'g-recaptcha-response' => 'required|captcha',
            ]);
        }

        $msg = '';

        if ($request->has('useremail')) {
            $email = $request->input('useremail');
            $mailTo = config('mail.from.address');
            $mailBody = 'Values submitted from contact form: ';

            foreach ($request->all() as $key => $value) {
                if ($key !== 'submit' && $key !== '_token' && $key !== 'g-recaptcha-response') {
                    $mailBody .= "$key : $value".PHP_EOL;
                }
            }

            if (! preg_match("/\n/i", $request->input('useremail'))) {
                SendContactUsEmail::dispatch($email, $mailTo, $mailBody)->onQueue('contactemail');
            }
            $msg = "<h2 style='text-align:center;'>Thank you for getting in touch with ".config('app.name').'.</h2>';
        }

        return $this->showContactForm($msg);
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    public function showContactForm(string $msg = '')
    {
        $this->setPreferences();
        $title = 'Contact '.config('app.name');
        $meta_title = 'Contact '.config('app.name');
        $meta_keywords = 'contact us,contact,get in touch,email';
        $meta_description = 'Contact us at '.config('app.name').' and submit your feedback';
        $content = $this->smarty->fetch('contact.tpl');

        $this->smarty->assign(compact('title', 'content', 'meta_title', 'meta_keywords', 'meta_description', 'msg'));

        $this->pagerender();
    }
}
