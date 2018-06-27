<?php

namespace App\Http\Controllers;

use App\Models\Settings;

class TermsController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function terms()
    {
        $this->setPrefs();
        $title = 'Terms and Conditions';
        $meta_title = Settings::settingValue('site.main.title').' - Terms and conditions';
        $meta_keywords = 'terms,conditions';
        $meta_description = 'Terms and Conditions for '.Settings::settingValue('site.main.title');

        $content = $this->smarty->fetch('terms.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );

        $this->pagerender();
    }
}
