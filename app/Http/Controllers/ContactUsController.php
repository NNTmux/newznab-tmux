<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ContactContactURequest;
use App\Jobs\SendContactUsEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactUsController extends BasePageController
{
    public function contact(ContactContactURequest $request): RedirectResponse
    {
        $replyTo = $request->replyToAddress();

        $lines = [
            'Name: '.$request->validated('username'),
            'Email: '.$replyTo,
        ];
        if ($request->user() !== null) {
            $lines[] = 'Account: '.$request->user()->username.($replyTo !== $request->user()->email ? ' ('.$request->user()->email.')' : '');
        }
        $lines[] = '';
        $lines[] = (string) $request->validated('comment');

        // Guard against header injection through the reply-to address.
        if ($replyTo !== '' && ! preg_match('/[\r\n]/', $replyTo)) {
            SendContactUsEmail::dispatch($replyTo, (string) config('mail.from.address'), implode(PHP_EOL, $lines))->onQueue('contactemail');
        }

        return redirect()->route('contact-us')->with('success', 'Thank you for getting in touch with '.config('app.name').'. We will reply to '.$replyTo.'.');
    }

    public function showContactForm(): View
    {
        return view('contact.index', [
            'title' => 'Contact '.config('app.name'),
            'meta_title' => 'Contact '.config('app.name'),
            'meta_keywords' => 'contact us,contact,get in touch,email',
            'meta_description' => 'Contact us at '.config('app.name').' and submit your feedback',
        ]);
    }
}
