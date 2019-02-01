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

        $content = $this->smarty->fetch($this->theme.'/terms.tpl');

        $this->smarty->assign(compact('title', 'content', 'meta_title', 'meta_keywords', 'meta_description'));

        $this->smarty->display($this->theme.'/basepage.tpl');
    }
}
