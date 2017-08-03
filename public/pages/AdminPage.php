<?php

use nntmux\Category;
use nntmux\Users;

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
				'admin' 	=> NN_THEMES . 'shared/templates/admin',
				'shared' 	=> NN_THEMES . 'shared/templates',
				'default' 	=> NN_THEMES . 'Omicron/templates'
			]
		);

		if (!isset($this->userdata['role']) || (int)$this->userdata['role'] !== Users::ROLE_ADMIN || !$this->users->isLoggedIn()) {
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
		$this->smarty->assign('page',$this);

		$admin_menu = $this->smarty->fetch('adminmenu.tpl');
		$this->smarty->assign('admin_menu',$admin_menu);

		$this->page_template = 'baseadminpage.tpl';

		parent::render();
	}
}
