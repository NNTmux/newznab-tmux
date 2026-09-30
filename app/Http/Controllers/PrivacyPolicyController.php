<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class PrivacyPolicyController extends BasePageController
{
    /**
     * Display the privacy policy page.
     */
    public function privacyPolicy(): View
    {
        $title = 'Privacy Policy';
        $meta_title = config('app.name').' - Privacy Policy';
        $meta_keywords = 'privacy,policy,data protection';
        $meta_description = 'Privacy Policy for '.config('app.name');
        $privacy_content = null;

        return view('privacy-policy', compact('title', 'meta_title', 'meta_keywords', 'meta_description', 'privacy_content'));
    }
}
