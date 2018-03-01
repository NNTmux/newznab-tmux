<?php

use App\Mail\ContactUs;
use Blacklight\Captcha;
use App\Models\Settings;
use Illuminate\Support\Facades\Mail;

$captcha = new Captcha($page);
$msg = '';

if (\request()->has('useremail')) {
    //
    // send the contact info and report back to user.
    //

    if ($captcha->getError() === false) {
        $email = \request()->input('useremail');
        $mailTo = Settings::settingValue('site.main.email');
        $mailBody = "Values submitted from contact form:\n";
        $request = \request()->all();

        foreach ($request as $key => $value) {
            if ($key !== 'submit') {
                $mailBody .= "$key : $value<br />\r\n";
            }
        }

        if (! preg_match("/\n/i", \request()->input('useremail'))) {
            Mail::to($mailTo)->send(new ContactUs($email, $mailBody));
        }
        $msg = "<h2 style='text-align:center;'>Thank you for getting in touch with ".Settings::settingValue('site.main.title').'.</h2>';
    }
}
$page->smarty->assign('msg', $msg);
$page->title = 'Contact '.Settings::settingValue('site.main.title');
$page->meta_title = 'Contact '.Settings::settingValue('site.main.title');
$page->meta_keywords = 'contact us,contact,get in touch,email';
$page->meta_description = 'Contact us at '.Settings::settingValue('site.main.title').' and submit your feedback';

$page->content = $page->smarty->fetch('contact.tpl');

$page->render();
