<?php

use nntmux\Users;
use nntmux\Category;

/**
 * All admin pages implement this class. Enforces admin role for requesting user.
 */
class AdminPage extends BasePage
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
                'admin'    => NN_THEMES.'admin',
                'shared'    => NN_THEMES.'shared',
                'default'    => NN_THEMES.'Gentele',
            ]
        );

        if (! isset($this->userdata['user_roles_id']) || (int) $this->userdata['user_roles_id'] !== Users::ROLE_ADMIN || ! $this->users->isLoggedIn()) {
            $this->show403(true);
        }

        $category = new Category();
        $this->smarty->assign('catClass', $category);
    }

    /**
     * Output a page using the admin template.
     *
     * @throws \Exception
     */
    public function render(): void
    {
        $this->smarty->assign('page', $this);

        $admin_menu = $this->smarty->fetch('adminmenu.tpl');
        $this->smarty->assign('admin_menu', $admin_menu);

        $this->page_template = 'baseadminpage.tpl';

        parent::render();
    }
}
