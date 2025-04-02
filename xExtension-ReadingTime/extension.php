<?php

declare(strict_types=1);

final class ReadingTimeExtension extends Minz_Extension {
	private int $speed = 300;
	private string $metrics = 'words';

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	#[\Override]
	public function init(): void {
		$this->registerTranslates();
		if (!FreshRSS_Context::hasUserConf()) {
			return;
		}
		// Defaults
		$speed = FreshRSS_Context::userConf()->attributeInt('reading_time_speed');
		if ($speed === null) {
			FreshRSS_Context::userConf()->_attribute('reading_time_speed', $this->speed);
		} else {
			$this->speed = $speed;
		}
		$metrics = FreshRSS_Context::userConf()->attributeString('reading_time_metrics');
		if ($metrics === null) {
			FreshRSS_Context::userConf()->_attribute('reading_time_metrics', $this->metrics);
		} else {
			$this->metrics = $metrics;
		}
		if (in_array(null, [$speed, $metrics], true)) {
			FreshRSS_Context::userConf()->save();
		}
		$this->registerHook('js_vars', [$this, 'getParams']);
		Minz_View::appendScript($this->getFileUrl('readingtime.js', 'js'));
	}

	public function getSpeed(): int {
		return $this->speed;
	}

	public function getMetrics(): string {
		return $this->metrics;
	}

	/**
	 * Called from js_vars hook
	 *
	 * Pass dynamic parameters to readingtime.js via `window.context.extensions`.
	 * Chain with other js_vars hooks via $vars.
	 *
	 * @param array<mixed> $vars is the result of hooks chained in the previous step.
	 * @return array<mixed> is passed to the hook chained to the next step.
	 */
	public function getParams(array $vars): array {
		$vars['reading_time_speed'] = $this->speed;
		$vars['reading_time_metrics'] = $this->metrics;
		return $vars;
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_ConfigurationParamException
	 */
	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			$speed = $this->validateSpeed(Minz_Request::paramInt('reading_time_speed'));
			FreshRSS_Context::userConf()->_attribute('reading_time_speed', $speed);
			$metrics = $this->validateMetrics(Minz_Request::paramString('reading_time_metrics'));
			FreshRSS_Context::userConf()->_attribute('reading_time_metrics', $metrics);
			FreshRSS_Context::userConf()->save();
		}
	}

	/** @throws Minz_ConfigurationParamException */
	private function validateSpeed(int $speed): int {
		if ($speed <= 0) {
			throw new Minz_ConfigurationParamException(_t('ext.reading_time.speed.invalid'));
		}
		return $speed;
	}

	/** @throws Minz_ConfigurationParamException */
	private function validateMetrics(string $metrics): string {
		switch ($metrics) {
			case 'words':
			case 'letters':
				return $metrics;
			default:
				throw new Minz_ConfigurationParamException(_t('ext.reading_time.metrics.invalid'));
		}
	}
}
