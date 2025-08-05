<?php
declare(strict_types=1);

final class CaptchaExtension extends Minz_Extension {
	 /** @var array{protectedPages:array<string,string>,captchaProvider:string,provider:array<string,string>,sendClientIp:bool} $default_config */
	public static array $default_config = [
		'protectedPages' => [],
		'captchaProvider' => 'none',
		'provider' => [],
		'sendClientIp' => true,
	];
	public static string $recaptcha_v3_js;

	#[\Override]
	public function init(): void {
		$this->registerTranslates();
		$this->registerHook('before_login_btn', [$this, 'captchaWidget']);
		$this->registerController('auth');

		// This is done because controllerName() or actionName() won't be accurate due to internal redirect being used on the login page
		self::$recaptcha_v3_js = $this->getFileUrl('recaptcha-v3.js');

		if (Minz_Request::controllerName() === 'extension') {
			Minz_View::appendScript($this->getFileUrl('captchaConfig.js'));
		}
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	public static function isProtectedPage(): bool {
		$config = self::getConfig();
		$page = Minz_Request::controllerName() . '_' . Minz_Request::actionName();
		return in_array($page, $config['protectedPages'], true);
	}

	public static function getClientIp(): string {
		$ip = checkTrustedIP() ? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? connectionRemoteAddress()) : connectionRemoteAddress();
		return is_string($ip) ? $ip : '';
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	public function captchaWidget(): string {
		$config = self::getConfig();
		if (!self::isProtectedPage()) {
			return '';
		}
		$siteKey = $config['provider']['siteKey'] ?? '';
		return match ($config['captchaProvider']) {
			'turnstile' => '<div class="cf-turnstile" data-sitekey="' . $siteKey . '"></div>',
			'recaptcha-v2' => '<div class="g-recaptcha" data-sitekey="' . $siteKey . '"></div>',
			'recaptcha-v3' => '<template id="siteKey">' . $siteKey . '</template>',
			'hcaptcha' => '<div class="h-captcha" data-sitekey="' . $siteKey . '"></div>',
			default => '',
		};
	}

	/**
	 * @throws Minz_PermissionDeniedException
	 */
	public static function warnLog(string $msg): void {
		Minz_Log::warning('[Form Captcha] ' . $msg, ADMIN_LOG);
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 * @return array{protectedPages:array<string,string>,captchaProvider:string,provider:array<string,string>,sendClientIp:bool}
	 */
	public static function getConfig(): array {
		/** @var array{protectedPages:array<string,string>,captchaProvider:string,provider:array<string,string>,sendClientIp:bool} $cfg */
		$cfg = FreshRSS_Context::systemConf()->attributeArray('form_captcha_config') ?? self::$default_config;
		return $cfg;
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			$form_captcha_config = [
				'protectedPages' => Minz_Request::paramArray('protectedPages'),
				'captchaProvider' => Minz_Request::paramStringNull('captchaProvider') ?? 'none',
				'provider' => Minz_Request::paramArray('provider'),
				'sendClientIp' => Minz_Request::paramBoolean('sendClientIp'),
			];

			FreshRSS_Context::systemConf()->_attribute('form_captcha_config', $form_captcha_config);
			FreshRSS_Context::systemConf()->save();

			Minz_Request::setGoodNotification(_t('feedback.conf.updated'));
		}
	}
}
