<?php

class QuickCollapseExtension extends Minz_Extension {
	public function init() {
		$this->registerTranslates();
		$this->registerViews();
		$this->registerController('quickCollapse');

		Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));
		Minz_View::appendScript(_url('quickCollapse', 'jsVars'));
		Minz_View::appendScript($this->getFileUrl('script.js', 'js'));
	}
}
