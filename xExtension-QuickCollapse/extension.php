<?php

declare(strict_types=1);

final class QuickCollapseExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		$this->registerTranslates();
		$this->registerHook('js_vars', [$this, 'jsVars']);

		Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));
		Minz_View::appendScript($this->getFileUrl('script.js', 'js'), false, true, false);
	}

	/**
	 * @return array<string, string|array<string, string>>
	 */
	public function jsVars(): array {
		return [
			'icon_url_in' => $this->getFileUrl('in.svg', 'svg'),
			'icon_url_out' => $this->getFileUrl('out.svg', 'svg'),
			'i18n' => [
				'toggle_collapse' => _t('gen.js.toggle_collapse'),
			],
		];
	}
}
