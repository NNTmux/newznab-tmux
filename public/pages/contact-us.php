<?php

use nntmux\Captcha;
use App\Models\Settings;
use nntmux\utility\Utility;

$captcha = new Captcha($page);
$msg = '';

if (isset($_POST['useremail'])) {
    //
    // send the contact info and report back to user.
    //

    if ($captcha->getError() === false) {
        $email = $_POST['useremail'];
        $mailto = Settings::settingValue('site.main.email');
        $mailsubj = 'Contact Form Submitted';
        $mailhead = "From: $email\n";
        $mailbody = "Values submitted from contact form:\n";

        foreach ($_POST as $key => $value) {
            if ($key !== 'submit') {
                $mailbody .= "$key : $value<br />\r\n";
            }
        }

        if (! preg_match("/\n/i", $_POST['useremail'])) {
            Utility::sendEmail($mailto, $mailsubj, $mailbody, $email);
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
