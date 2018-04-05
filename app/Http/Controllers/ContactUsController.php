<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Mail\ContactUs;
use App\Models\Admin;
use App\Models\Settings;
use App\Notifications\InboxMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactUsController extends Controller
{

    /**
     * @param mixed $ability
     * @param array $arguments
     *
     * @return bool|\Illuminate\Auth\Access\Response
     */
    public function authorize($ability, $arguments = [])
    {
        return true;
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function contact(Request $request)
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
        app('smarty.view')->assign('message', $msg);
    }

    /**
     * @throws \Exception
     */
    public function show()
    {
        $theme = Settings::settingValue('site.main.style');
        $title = 'Contact '.Settings::settingValue('site.main.title');
        $meta_title = 'Contact '.Settings::settingValue('site.main.title');
        $meta_keywords = 'contact us,contact,get in touch,email';
        $meta_description = 'Contact us at '.Settings::settingValue('site.main.title').' and submit your feedback';

        $content = app('smarty.view')->fetch($theme.'/contact.tpl');

        app('smarty.view')->assign(
            [
                'title' => $title,
                'content' => $content,
                'nocaptcha' => env('NOCAPTCHA_ENABLED'),
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );

        app('smarty.view')->display($theme.'/basepage.tpl');
    }

    /**
     * @param \App\Http\Requests\ContactFormRequest $message
     * @param \App\Models\Admin                     $admin
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function mailToAdmin(ContactFormRequest $message, Admin $admin)
    {        //send the admin an notification
        $admin->notify(new InboxMessage($message));
        // redirect the user back
        return redirect()->back()->with('message', 'Thanks for the message! We will get back to you soon!');
    }
}
