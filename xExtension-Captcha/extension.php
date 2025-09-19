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
		$this->registerController('user');

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
		$ip = checkTrustedIP() ? ($_SERVER['HTTP_X_REAL_IP'] ?? connectionRemoteAddress()) : connectionRemoteAddress();
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
	 * @return array{protectedPages:string[],captchaProvider:string,provider:array<string,string>,sendClientIp:bool}
	 */
	public static function getConfig(): array {
		/** @var array{protectedPages:array<string,string>,captchaProvider:string,provider:array<string,string>,sendClientIp:bool} $cfg */
		$cfg = FreshRSS_Context::systemConf()->attributeArray('form_captcha_config') ?? self::$default_config;
		if (in_array('auth_register', $cfg['protectedPages'], true)) {
			// Protect POST action for registration form
			$cfg['protectedPages'][] = 'user_create';
		}
		return $cfg;
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_PermissionDeniedException
	 */
	public static function initCaptcha(): bool {
		$username = Minz_Request::paramString('username');

		$config = CaptchaExtension::getConfig();
		$provider = $config['captchaProvider'];

		if ($provider === 'none') {
			return true;
		}

		if (Minz_Request::isPost() && CaptchaExtension::isProtectedPage()) {
			$ch = curl_init();
			if ($ch === false) {
				Minz_Error::error(500);
				return false;
			}

			/*
			See:
			https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
			https://developers.google.com/recaptcha/docs/verify?hl=en
			https://docs.hcaptcha.com/#verify-the-user-response-server-side
			*/

			$siteverify_url = match ($provider) {
				'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
				'recaptcha-v2' => 'https://www.google.com/recaptcha/api/siteverify',
				'recaptcha-v3' => 'https://www.google.com/recaptcha/api/siteverify',
				'hcaptcha' => 'https://hcaptcha.com/siteverify',
				default => '',
			};
			$response_param = match ($provider) {
				'turnstile' => 'cf-turnstile-response',
				'recaptcha-v2' => 'g-recaptcha-response',
				'recaptcha-v3' => 'g-recaptcha-response',
				'hcaptcha' => 'h-captcha-response',
				default => '',
			};
			$response_val = Minz_Request::paramString($response_param);

			$fields = [
				'secret' => $config['provider']['secretKey'] ?? '',
				'response' => $response_val,
			];
			if ($config['sendClientIp']) {
				$fields['remoteip'] = CaptchaExtension::getClientIp();
			}
			curl_setopt_array($ch, [
				CURLOPT_URL => $siteverify_url,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query($fields),
				CURLOPT_USERAGENT => FRESHRSS_USERAGENT,
				CURLOPT_RETURNTRANSFER => true,
			]);
			curl_setopt_array($ch, FreshRSS_Context::systemConf()->curl_options);

			$body = curl_exec($ch);
			if (!is_string($body)) {
				Minz_Error::error(500);
				return false;
			}
			/** @var array{success:bool,error-codes:string[]} $json */
			$json = json_decode($body, true);
			if (!is_array($json)) {
				Minz_Error::error(500);
				return false;
			}
			if ($json['success'] !== true) {
				$actionName = Minz_Request::actionName();
				CaptchaExtension::warnLog("($actionName) Failed to verify '$provider' challenge for user \"$username\": " . implode(',', $json['error-codes']));
				Minz_Error::error(400, ['error' => [_t('ext.form_captcha.invalid_captcha')]]);
				return false;
			}
		}
		return true;
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 * @return array<string,string>
	 */
	public static function loadDependencies(): array {
		$cfg = self::getConfig();
		$provider = self::isProtectedPage() ? $cfg['captchaProvider'] : '';
		$js_url = match ($provider) {
			'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
			'recaptcha-v2' => 'https://www.google.com/recaptcha/api.js',
			'recaptcha-v3' => 'https://www.google.com/recaptcha/api.js?render=' . $cfg['provider']['siteKey'],
			'hcaptcha' => 'https://js.hcaptcha.com/1/api.js',
			default => '',
		};
		if ($js_url === '') {
			return [];
		}
		$csp_hosts = parse_url($js_url);
		if (!is_array($csp_hosts)) {
			Minz_Error::error(500);
			return [];
		}
		$csp_hosts = 'https://' . ($csp_hosts['host'] ?? '');
		if ($csp_hosts === 'https://www.google.com') {
			// Original js_url injects script from www.gstatic.com therefore this is needed
			$csp_hosts .= "/recaptcha/api.js https://www.gstatic.com/recaptcha/";
		} else if ($csp_hosts === 'https://js.hcaptcha.com') {
			$csp_hosts = 'https://hcaptcha.com https://*.hcaptcha.com';
		}
		$csp = [
			'default-src' => "'self'",
			'frame-ancestors' => "'none'",
			'script-src' => "'self' $csp_hosts",
			'frame-src' => $csp_hosts,
			'connect-src' => "'self' $csp_hosts",
		];
		if ($provider === 'hcaptcha') {
			$csp['style-src'] = "'self' " . $csp_hosts;
		}
		Minz_View::appendScript($js_url);
		if ($provider === 'recaptcha-v3') {
			Minz_View::appendScript(self::$recaptcha_v3_js);
		}
		return $csp;
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (FreshRSS_Auth::requestReauth()) {
			return;
		}

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
