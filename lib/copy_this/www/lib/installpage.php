<?php
require_once('../lib/smarty/Smarty.class.php');

/**
 * This class represents each page during installation.
 */
class Installpage
{
	public $title = '';
	public $content = '';
	public $head = '';
	public $page_template = ''; 
	public $smarty = '';
	
	public $error = false;
	
	/**
	 * Default constructor.
	 */
	function Installpage()
	{
		@session_start();
		
		$this->smarty = new Smarty();

		$this->smarty->setTemplateDir(realpath('../install/views/'));
		$this->smarty->setCompileDir(realpath('../lib/smarty/templates_c/'));
		$this->smarty->setConfigDir(realpath('../lib/smarty/configs/'));
		$this->smarty->setCacheDir(realpath('../lib/smarty/cache/'));
	}    
	
	/**
	 * Writes out the page.
	 */
	public function render()
	{
		$this->page_template = "installpage.tpl";
		$this->smarty->display($this->page_template);
	}
	
	/**
	 * Determine if a postback has occurred.
	 */
	public function isPostBack()
	{
		return (strtoupper($_SERVER["REQUEST_METHOD"]) === "POST");	
	}
	
	/**
	 * Determine if an install page has issued a success.
	 */
	public function isSuccess()
	{
		return isset($_GET['success']);	
	}
	
}