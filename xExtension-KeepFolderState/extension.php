<?php

class KeepFolderStateExtension extends Minz_Extension {
	public function install() {
		return true;
	}

	public function uninstall() {
		return true;
	}

	public function handleConfigureAction() {
    }

    public function init() {
        Minz_View::appendScript($this->getFileUrl('jquerymin.js', 'js'),'','','');
        Minz_View::appendScript($this->getFileUrl('script.js', 'js'),'','','');   
    }

}
