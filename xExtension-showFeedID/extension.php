<?php

declare(strict_types=1);

final class ShowFeedIdExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		if (Minz_Request::paramString('c') === 'subscription'
			&& Minz_Request::actionName() === 'index') {
			$this->registerTranslates();
			$this->registerHook('js_vars', [$this, 'jsVars']);
			Minz_View::appendScript($this->getFileUrl('showfeedid.js'));
		}
	}

	/**
	 * @param array<string,mixed> $vars
	 * @return array<string,mixed>
	 */
	public function jsVars(array $vars): array {
		$vars['showfeedid_i18n'] = [
			'show' => _t('ext.showfeedid.show'),
			'hide' => _t('ext.showfeedid.hide')
		];
		return $vars;
	}
}
