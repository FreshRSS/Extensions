<?php

declare(strict_types=1);

final class ReadingTimeExtension extends Minz_Extension {
	private int $speed = 300;
	private string $metrics = 'words';

	#[\Override]
	public function init(): void {
		$this->registerTranslates();
		if (!FreshRSS_Context::hasUserConf()) {
			return;
		}
		// Defaults
		$speed = FreshRSS_Context::userConf()->attributeInt('reading_time_speed');	// @phpstan-ignore missingType.checkedException
		if ($speed === null) {
			FreshRSS_Context::userConf()->_attribute('reading_time_speed', $this->speed);	// @phpstan-ignore missingType.checkedException
		} else {
			$this->speed = $speed;
		}
		$metrics = FreshRSS_Context::userConf()->attributeString('reading_time_metrics');	// @phpstan-ignore missingType.checkedException
		if ($metrics === null) {
			FreshRSS_Context::userConf()->_attribute('reading_time_metrics', $this->metrics);	// @phpstan-ignore missingType.checkedException
		} else {
			$this->metrics = $metrics;
		}
		if (in_array(null, [$speed, $metrics], true)) {
			FreshRSS_Context::userConf()->save();	// @phpstan-ignore missingType.checkedException
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
	 * @param array<mixed> $vars
	 * @return array{
	 * 	reading_time_speed: int,
	 * 	reading_time_metrics: string,
	 * }
	 */
	public function getParams(array $vars): array {
		return array(
			'reading_time_speed' => $this->speed,
			'reading_time_metrics' => $this->metrics,
		);
	}

	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			$speed = $this->validateSpeed(Minz_Request::paramInt('reading_time_speed'));	// @phpstan-ignore missingType.checkedException
			FreshRSS_Context::userConf()->_attribute('reading_time_speed', $speed);	// @phpstan-ignore missingType.checkedException
			$metrics = $this->validateMetrics(Minz_Request::paramString('reading_time_metrics'));	// @phpstan-ignore missingType.checkedException
			FreshRSS_Context::userConf()->_attribute('reading_time_metrics', $metrics);	// @phpstan-ignore missingType.checkedException
			FreshRSS_Context::userConf()->save();	// @phpstan-ignore missingType.checkedException
		}
	}

	/** @throws Minz_ActionException */
	private function validateSpeed(int $speed): int {
		if ($speed <= 0) {
			throw new Minz_ActionException(_t('ext.reading_time.speed.invalid'), Minz_Request::actionName());
		}
		return $speed;
	}

	/** @throws Minz_ActionException */
	private function validateMetrics(string $metrics): string {
		switch ($metrics) {
			case 'words':
			case 'letters':
				return $metrics;
			default:
				throw new Minz_ActionException(_t('ext.reading_time.metrics.invalid'), Minz_Request::actionName());
		}
	}
}
