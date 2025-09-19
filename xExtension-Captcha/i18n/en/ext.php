<?php

return array(
	'form_captcha' => array(
		'protected_pages' => 'Protected pages',
		'pages' => array(
			'register' => 'Register',
			'login' => 'Login',
		),
		'captcha_provider' => 'CAPTCHA Provider',
		'providers' => array(
			'none' => 'None',
			'site_key' => array(
				'label' => 'Site Key (public)',
				'placeholder' => 'Enter your site key here…',
			),
			'secret_key' => array(
				'label' => 'Secret Key (private)',
				'placeholder' => 'Enter your secret key here…',
			),
		),
		'invalid_captcha' => 'Invalid captcha, try again.',
		'clear_fields' => 'Clear fields',
		'unsafe_login_warning' => 'Warning: Unsafe autologin is enabled, it is recommended to disable this option in order to prevent bypassing the captcha',
		'ext_must_be_enabled' => 'The extension must be enabled for the configuration view to work properly.',
		'help' => array(
			'turnstile' => '<a target="_blank" href="https://www.cloudflare.com/application-services/products/turnstile/">Turnstile website</a> | <a target="_blank" href="https://www.cloudflare.com/privacypolicy/">Cloudflare privacy policy</a> | <a target="_blank" href="https://www.cloudflare.com/website-terms/">Cloudflare ToS</a>',
			'recaptcha' => '<a target="_blank" href="https://developers.google.com/recaptcha">reCAPTCHA website</a> | <a target="_blank"href="https://policies.google.com/privacy">Google privacy policy</a> | <a target="_blank" href="https://policies.google.com/terms">Google ToS</a>',
			'hcaptcha' => '<a target="_blank" href="https://www.hcaptcha.com/">hCaptcha website</a> | <a target="_blank" href="https://www.hcaptcha.com/privacy">hCaptcha privacy policy</a> | <a target="_blank" href="https://www.hcaptcha.com/terms">hCaptcha ToS</a>',
		),
		'send_client_ip' => 'Send client IP address',
	),
);
