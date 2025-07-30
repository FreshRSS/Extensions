<?php
declare(strict_types=1);

class FreshExtension_auth_Controller extends FreshRSS_auth_Controller {
	/**
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_PermissionDeniedException
	 */
	public function initCaptcha(): bool {
		$username = Minz_Request::paramString('username');

		$config = CaptchaExtension::getConfig();
		$provider = $config['captchaProvider'];

		if ($provider === 'none') {
			return true;
		}

		$isPOST = Minz_Request::isPost() && !Minz_Session::paramBoolean('POST_to_GET');
		if ($isPOST && CaptchaExtension::isProtectedPage()) {
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
		} else {
			$js_url = match ($provider) {
				'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
				'recaptcha-v2' => 'https://www.google.com/recaptcha/api.js',
				'recaptcha-v3' => 'https://www.google.com/recaptcha/api.js?render=' . $config['provider']['siteKey'],
				'hcaptcha' => 'https://js.hcaptcha.com/1/api.js',
				default => '',
			};
			$js_domain = parse_url($js_url);
			if (!is_array($js_domain)) {
				Minz_Error::error(500);
				return false;
			}
			$js_domain = $js_domain['host'] ?? '';
			if ($js_domain === 'www.google.com') {
				// Original js_url redirects to www.gstatic.com therefore this is needed
				$js_domain .= ' www.gstatic.com';
			} else if ($js_domain === 'js.hcaptcha.com') {
				$js_domain = 'hcaptcha.com *.hcaptcha.com';
			}
			$csp = [
				'default-src' => "'self'",
				'frame-ancestors' => '"none"',
				'script-src' => "'self' $js_domain",
				'frame-src' => $js_domain,
				'connect-src' => "'self' $js_domain",
			];
			if ($provider === 'hcaptcha') {
				$csp['style-src'] = "'self' " . $js_domain;
			}
			$this->_csp($csp);
			Minz_View::appendScript($js_url);
			if ($provider === 'recaptcha-v3') {
				Minz_View::appendScript(CaptchaExtension::$recaptcha_v3_js);
			}
		}
		return true;
	}

	public function formLoginAction(): void {
		if (!$this->initCaptcha()) {
			return;
		}
		parent::formLoginAction();
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_PermissionDeniedException
	 */
	public function registerAction(): void {
		if (!$this->initCaptcha()) {
			return;
		}
		parent::registerAction();
	}
}
