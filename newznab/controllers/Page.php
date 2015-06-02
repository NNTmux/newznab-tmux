<?php

/**
 * This class represents every normal user page in the site.
 */
class Page extends BasePage
{
	/**
	 * Default constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$role=Users::ROLE_GUEST;
		if ($this->userdata != null)
			$role = $this->userdata["role"];

		$content = new Contents();
		$f = new Forum();
		$menu = new Menu($this->settings);
		$this->smarty->assign('menulist',$menu->get($role, $this->serverurl));
		$this->smarty->assign('usefulcontentlist', $content->getForMenuByTypeAndRole(Contents::TYPEUSEFUL, $role));
		$this->smarty->assign('articlecontentlist', $content->getForMenuByTypeAndRole(Contents::TYPEARTICLE, $role));
		if ($this->userdata != null)
			$this->smarty->assign('recentforumpostslist',$f->getRecentPosts($this->pdo->getSetting('showrecentforumposts')));

		$this->smarty->assign('main_menu',$this->smarty->fetch('mainmenu.tpl'));
		$this->smarty->assign('useful_menu',$this->smarty->fetch('usefullinksmenu.tpl'));
		$this->smarty->assign('article_menu',$this->smarty->fetch('articlesmenu.tpl'));

		$category = new Category();
		if ($this->userdata != null)
			$parentcatlist = $category->getForMenu($this->userdata["categoryexclusions"]);
		else
			$parentcatlist = $category->getForMenu();

		$this->smarty->assign('parentcatlist',$parentcatlist);
		$searchStr = '';
		if ($this->page == 'search' && isset($_REQUEST["id"]))
			$searchStr = (string) $_REQUEST["id"];
		$this->smarty->assign('header_menu_search',$searchStr);

		if (isset($_REQUEST["t"])) {
			$this->smarty->assign('header_menu_cat', $_REQUEST["t"]);
		} else {
			$this->smarty->assign('header_menu_cat', '');
		}
		$header_menu = $this->smarty->fetch('headermenu.tpl');
		$this->smarty->assign('header_menu',$header_menu);
	}

	/**
	 * Output the page.
	 */
	public function render()
	{
		$this->smarty->assign('page',$this);
		$this->page_template = "basepage.tpl";

		parent::render();
	}
}