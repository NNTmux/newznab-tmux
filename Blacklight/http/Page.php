<?php
namespace Blacklight\http;


use App\Models\Menu;
use App\Models\User;
use App\Models\Category;
use App\Models\Settings;
use Blacklight\Contents;
use App\Models\Forumpost;

/**
 * This class represents every normal user page in the site.
 */
class Page extends BasePage
{
    /**
     * Default constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();

        // Tell Smarty which directories to use for templates
        $this->smarty->setTemplateDir(
            [
                'user'        => NN_THEMES.$this->theme,
                'shared'    => NN_THEMES.'shared',
                'default'    => NN_THEMES.'Gentele',
            ]
        );

        $role = User::ROLE_USER;
        if (! empty($this->userdata)) {
            $role = $this->userdata['user_roles_id'];
        }

        $content = new Contents();
        $this->smarty->assign('menulist', Menu::getMenu($role, $this->serverurl));
        $this->smarty->assign('usefulcontentlist', $content->getForMenuByTypeAndRole(Contents::TYPEUSEFUL, $role));
        $this->smarty->assign('articlecontentlist', $content->getForMenuByTypeAndRole(Contents::TYPEARTICLE, $role));
        if ($this->userdata !== null) {
            $this->smarty->assign('recentforumpostslist', Forumpost::getPosts(Settings::settingValue('..showrecentforumposts')));
        }

        $this->smarty->assign('main_menu', $this->smarty->fetch('mainmenu.tpl'));
        $this->smarty->assign('useful_menu', $this->smarty->fetch('usefullinksmenu.tpl'));
        $this->smarty->assign('article_menu', $this->smarty->fetch('articlesmenu.tpl'));

        if (! empty($this->userdata)) {
            $parentcatlist = Category::getForMenu($this->userdata['categoryexclusions'], $this->userdata['rolecategoryexclusions']);
        } else {
            $parentcatlist = Category::getForMenu();
        }

        $this->smarty->assign('parentcatlist', $parentcatlist);
        $this->smarty->assign('catClass', Category::class);
        $searchStr = '';
        if ($this->page === 'search' && request()->has('id')) {
            $searchStr = request()->input('id');
        }
        $this->smarty->assign('header_menu_search', $searchStr);

        if (request()->has('t')) {
            $this->smarty->assign('header_menu_cat', request()->input('t'));
        } else {
            $this->smarty->assign('header_menu_cat', '');
        }
        $header_menu = $this->smarty->fetch('headermenu.tpl');
        $this->smarty->assign('header_menu', $header_menu);
    }

    /**
     * Output the page.
     */
    public function render()
    {
        $this->smarty->assign('page', $this);
        $this->page_template = 'basepage.tpl';

        parent::render();
    }
}
