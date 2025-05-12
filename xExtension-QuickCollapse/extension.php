<?php

declare(strict_types=1);

final class QuickCollapseExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		$this->registerTranslates();
		$this->registerHook('js_vars', [$this, 'jsVars']);

		Minz_View::appendStyle($this->getFileUrl('style.css'));
		Minz_View::appendScript($this->getFileUrl('script.js'), cond: false, defer: true, async: false);
	}

	/**
	 * @param array<string,mixed> $vars
	 * @return array<string,mixed>
	 */
	public function jsVars(array $vars): array {
		$vars['quick_collapse'] = [
			'icon_url_in' => $this->getFileUrl('in.svg'),
			'icon_url_out' => $this->getFileUrl('out.svg'),
			'i18n' => [
				'toggle_collapse' => _t('gen.js.toggle_collapse'),
			]
		];
		return $vars;
	}
}
