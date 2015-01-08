<?php

class CustomCSSExtension extends Minz_Extension {
	public function init() {
		$this->registerTranslates();
		Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));

		$current_user = Minz_Session::param('currentUser');
		$filename =  'style.' . $current_user . '.css';
		$filepath = join_path($this->getPath(), 'static', $filename);

		if (file_exists($filepath)) {
			Minz_View::appendStyle($this->getFileUrl($filename, 'css'));
		}
	}

	public function handleConfigureAction() {
		$this->registerTranslates();

		$current_user = Minz_Session::param('currentUser');
		$filename =  'style.' . $current_user . '.css';
		$filepath = join_path($this->getPath(), 'static', $filename);

		if (Minz_Request::isPost()) {
			$css_rules = Minz_Request::param('css-rules', '');
			file_put_contents($filepath, $css_rules);
		}

		$this->css_rules = '';
		if (file_exists($filepath)) {
			$this->css_rules = file_get_contents($filepath);
		}
	}
}
