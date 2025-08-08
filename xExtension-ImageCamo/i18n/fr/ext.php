<?php

return array(
	'imagecamo' => array(
		'proxy_url' => 'URL du proxy Camo',
		'proxy_url_help' => 'L’URL de base de votre instance camo (ex: https://camo.example.com)',
		'hmac_key' => 'Clé HMAC',
		'hmac_key_help' => 'La clé secrète partagée utilisée pour signer les URLs (doit correspondre à votre configuration camo)',
		'encoding' => 'Encodage d’URL',
		'encoding_help' => 'Choisissez le format d’encodage d’URL supporté par votre instance camo',
		'encoding_base64_desc' => 'recommandé, URLs plus courtes',
		'encoding_hex_desc' => 'URLs plus longues, insensible à la casse',
		'scheme_http' => 'Proxifier les images HTTP',
		'scheme_https' => 'Proxifier les images HTTPS',
		'scheme_default' => 'Proxifier les URLs relatives au protocole',
		'scheme_include' => 'Inclure http*:// dans l’URL',
		'security_notice_title' => 'Avis de sécurité',
		'security_notice_text' => 'Gardez votre clé HMAC secrète et sécurisée. Utilisez une clé forte et aléatoire qui correspond à la configuration de votre instance camo.'
	),
);
