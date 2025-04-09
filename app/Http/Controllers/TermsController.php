<?php

namespace App\Http\Controllers;

class TermsController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function terms(): void
    {
        $this->setPreferences();
        $title = 'Terms and Conditions';
        $meta_title = config('app.name').' - Terms and conditions';
        $meta_keywords = 'terms,conditions';
        $meta_description = 'Terms and Conditions for '.config('app.name');

        $content = $this->smarty->fetch('terms.tpl');

        $this->smarty->assign(compact('title', 'content', 'meta_title', 'meta_keywords', 'meta_description'));

        $this->pagerender();
    }
}
