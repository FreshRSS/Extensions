<?php

class TitleWrapExtension extends Minz_Extension {
	public function install() {
		return true;
	}

	public function uninstall() {
		return true;
	}

	public function handleConfigureAction() {
	}

	public function init() {
		Minz_View::appendStyle($this->getFileUrl('title_wrap.css', 'css'));
	}
}
