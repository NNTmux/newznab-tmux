<?php
require_once './config.php';

/**
 * All admin pages implement this class. Enforces admin role for requesting user.
 */
class AdminPage extends BasePage
{
	/**
	 * Default constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$tplpaths = [];
		if ($this->site->style != "default")
			$tplpaths["style_admin"] = WWW_DIR.'templates/'.$this->site->style.'/views/admin';
		$tplpaths["admin"] = WWW_DIR.'templates/default/views/admin';
		$tplpaths["frontend"] = WWW_DIR.'templates/default/views/frontend';
		$this->smarty->setTemplateDir($tplpaths);

		$users = new Users();
		if (!$users->isLoggedIn() || !isset($this->userdata["role"]) || $this->userdata["role"] != Users::ROLE_ADMIN)
			$this->show403(true);

	}

	/**
	 * Output a page using the admin template.
	 */
	public function render()
	{
		$this->smarty->assign('page',$this);

		$admin_menu = $this->smarty->fetch('adminmenu.tpl');
		$this->smarty->assign('admin_menu',$admin_menu);

		$this->page_template = "baseadminpage.tpl";

		parent::render();
	}
}