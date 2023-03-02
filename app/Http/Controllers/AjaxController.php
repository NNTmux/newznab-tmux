<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AjaxController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function profile(Request $request): void
    {
        $this->setPrefs();
        if ($request->has('action') && (int) $request->input('action') === 1 && $request->has('emailto')) {
            $emailTo = $request->input('emailto');
            $ret = User::sendInvite(url('/'), $this->userdata->id, $emailTo);
            if (! $ret) {
                echo 'Invite not sent.';
            } else {
                echo 'Invite sent. Alternatively paste them following link to register - '.$ret;
            }
        } else {
            echo 'Invite not sent.';
        }
    }
}
