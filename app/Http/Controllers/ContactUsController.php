<?php

namespace App\Http\Controllers;

use App\Mail\ContactUs;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactUsController extends BasePageController
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->middleware('guest');
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function showContactForm(Request $request)
    {
        $this->validate($request, [
            'useremail' => 'required',
        ]);

        if (env('NOCAPTCHA_ENABLED') === true) {
            $this->validate($request, [
                'g-recaptcha-response' => 'required|captcha',
            ]);
        }

        $msg = '';

        if ($request->has('useremail')) {
            $email = $request->input('useremail');
            $mailTo = Settings::settingValue('site.main.email');
            $mailBody = "Values submitted from contact form:\n";

            foreach ($request->all() as $key => $value) {
                if ($key !== 'submit') {
                    $mailBody .= "$key : $value<br />\r\n";
                }
            }

            if (! preg_match("/\n/i", $request->input('useremail'))) {
                Mail::to($mailTo)->send(new ContactUs($email, $mailBody));
            }
            $msg = "<h2 style='text-align:center;'>Thank you for getting in touch with ".Settings::settingValue('site.main.title').'.</h2>';
        }
        $this->smarty->assign('msg', $msg);
        $title = 'Contact '.Settings::settingValue('site.main.title');
        $meta_title = 'Contact '.Settings::settingValue('site.main.title');
        $meta_keywords = 'contact us,contact,get in touch,email';
        $meta_description = 'Contact us at '.Settings::settingValue('site.main.title').' and submit your feedback';

        $content = $this->smarty->fetch('contact.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );

        $this->pagerender();
    }
}
