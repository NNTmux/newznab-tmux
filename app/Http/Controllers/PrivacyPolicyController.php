<?php
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
        // Get privacy policy content from settings (if available)
        $privacy_content = $this->settings->privacy_policy ?? null;
        return view('privacy-policy', compact('title', 'meta_title', 'meta_keywords', 'meta_description', 'privacy_content'));
    }
}
