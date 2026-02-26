<?php

declare(strict_types=1);

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

        return view('terms', compact('title', 'meta_title', 'meta_keywords', 'meta_description'));
    }
}
