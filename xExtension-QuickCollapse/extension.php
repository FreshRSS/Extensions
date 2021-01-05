<?php

class QuickCollapseExtension extends Minz_Extension {
	public function install() {
		return true;
	}

	public function uninstall() {
		return true;
	}

	public function handleConfigureAction() {
	}

	public function init() {
		$this->registerTranslates();
		$this->registerViews();
		$this->registerController('quickCollapse');

		Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));
		Minz_View::appendScript(_url('quickCollapse', 'jsVars'), false, true, false);
		Minz_View::appendScript($this->getFileUrl('script.js', 'js'), false, true, false);
	}
}
