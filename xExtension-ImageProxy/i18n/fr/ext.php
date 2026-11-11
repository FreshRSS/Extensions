<?php

return array(
	'imageproxy' => array(
		'proxy_url' => 'URL du proxy',
		'scheme_http' => 'Proxy HTTP',
		'scheme_https' => 'Proxy HTTPS',
		'scheme_default' => 'Proxy URLs sans protocole',
		'scheme_include' => 'Inclure http*:// dans l\'URL',
		'url_encode' => 'Encoder l\'URL',
		'excluded_domains' => 'Ne pas utiliser le proxy pour ces domaines',
		'excluded_domains_help' => 'Un domaine par ligne (ou séparés par des virgules). Leurs sous-domaines ne passent pas non plus par le proxy : par exemple, example.org correspond aussi à images.example.org.',
	),
);
