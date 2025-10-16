<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class TermsController extends BasePageController
{
    /**
     * Display the terms and conditions page.
     */
    public function terms(): View
    {
        $title = 'Terms and Conditions';
        $meta_title = config('app.name').' - Terms and conditions';
        $meta_keywords = 'terms,conditions';
        $meta_description = 'Terms and Conditions for '.config('app.name');

        // Get terms content from settings
        $terms_content = $this->settings->tandc ?? '<p>No terms and conditions have been set yet.</p>';

        return view('terms', compact('title', 'meta_title', 'meta_keywords', 'meta_description', 'terms_content'));
    }
}
