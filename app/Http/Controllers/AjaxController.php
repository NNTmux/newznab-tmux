<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AjaxController extends BasePageController
{
    public function profile(Request $request)
    {
        $this->setPrefs();
        if ($request->has('action') && (int) $request->input('action') === 1 && $request->has('emailto')) {
            $emailTo = $request->input('emailto');
            $ret = User::sendInvite(url('/'), $this->userdata->id, $emailTo);
            if (! $ret) {
                print 'Invite not sent.';
            } else {
                print 'Invite sent. Alternatively paste them following link to register - ' . $ret;
            }
        } else {
            print 'Invite not sent.';
        }
    }
}
