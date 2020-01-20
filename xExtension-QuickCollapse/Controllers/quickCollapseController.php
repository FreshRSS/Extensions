<?php

class FreshExtension_quickCollapse_Controller extends Minz_ActionController {
	public function jsVarsAction() {
		$extension = Minz_ExtensionManager::findExtension('Quick Collapse');

		$this->view->icon_url_in = $extension->getFileUrl('in.svg', 'svg');
		$this->view->icon_url_out = $extension->getFileUrl('out.svg', 'svg');
		$this->view->i18n_toggle_collapse = _t('gen.js.toggle_collapse');

		$this->view->_layout(false);
		$this->view->_path('quickCollapse/vars.js');
		header('Content-Type: application/javascript');
	}
}
