<?php

return array(
	'imagecamo' => array(
		'proxy_url' => 'Camo Proxy URL',
		'proxy_url_help' => 'The base URL of your camo server (e.g., https://camo.example.com)',
		'hmac_key' => 'HMAC Key',
		'hmac_key_help' => 'The shared secret key used to sign URLs (must match your camo server configuration)',
		'encoding' => 'URL Encoding',
		'encoding_help' => 'Choose the URL encoding format supported by your camo server',
		'encoding_base64_desc' => 'recommended, shorter URLs',
		'encoding_hex_desc' => 'longer URLs, case insensitive',
		'scheme_http' => 'Proxy HTTP images',
		'scheme_https' => 'Proxy HTTPS images',
		'scheme_default' => 'Proxy protocol-relative URLs',
		'scheme_include' => 'Include http*:// in URL',
		'security_notice_title' => 'Security Notice',
		'security_notice_text' => 'Keep your HMAC key secret and secure. Use a strong, random key that matches your camo server configuration.'
	),
);
