<?php

return array(
	'form_captcha' => array(
		'protected_pages' => 'Chronione strony',
		'pages' => array(
			'register' => 'Rejestracja',
			'login' => 'Logowanie',
		),
		'captcha_provider' => 'Dostawca CAPTCHA',
		'providers' => array(
			'none' => 'Brak',
			'site_key' => array(
				'label' => 'Klucz witryny (publiczny)',
				'placeholder' => 'Wprowadź swój klucz witryny…',
			),
			'secret_key' => array(
				'label' => 'Tajny klucz (prywatny)',
				'placeholder' => 'Wprowadź swój tajny klucz…',
			),
		),
		'invalid_captcha' => 'Nieprawidłowa captcha. Spróbuj ponownie.',
		'clear_fields' => 'Wyczyść pola',
		'unsafe_login_warning' => 'Uwaga: Niebezpieczne automatyczne logowanie jest włączone, zalecane jest wyłączenie tej opcji, aby uniemożliwić obejście captchy',
		'ext_must_be_enabled' => 'Rozszerzenie musi być włączone aby widok konfiguracji działał prawidłowo.',
		'help' => array(
			'turnstile' => '<a target="_blank" href="https://www.cloudflare.com/pl-pl/application-services/products/turnstile/">Witryna Turnstile</a> | <a target="_blank" href="https://www.cloudflare.com/pl-pl/privacypolicy/">Polityka prywatności Cloudflare</a> | <a target="_blank" href="https://www.cloudflare.com/pl-pl/website-terms/">Warunki użytkowania Cloudflare</a>',
			'recaptcha' => '<a target="_blank" href="https://developers.google.com/recaptcha?hl=pl">Witryna reCAPTCHA</a> | <a target="_blank"href="https://policies.google.com/privacy?hl=pl">Polityka prywatności Google</a> | <a target="_blank" href="https://policies.google.com/terms?hl=pl">Warunki użytkowania Google</a>',
			'hcaptcha' => '<a target="_blank" href="https://www.hcaptcha.com/?hl=pl">Witryna hCaptcha</a> | <a target="_blank" href="https://www.hcaptcha.com/privacy?hl=pl">Polityka prywatności hCaptcha</a> | <a target="_blank" href="https://www.hcaptcha.com/terms?hl=pl">Warunki użytkowania hCaptcha</a>',
		),
		'send_client_ip' => 'Wysyłaj adres IP klienta',
	),
);
