<?php

declare(strict_types=1);

final class ImageCamoExtension extends Minz_Extension {
	// Defaults
	private const DEFAULT_CAMO_URL = 'https://your-camo-instance.example.com';
	private const DEFAULT_HMAC_KEY = '';
	private const DEFAULT_SCHEME_HTTP = true;
	private const DEFAULT_SCHEME_HTTPS = false;
	private const DEFAULT_SCHEME_DEFAULT = 'auto';
	private const DEFAULT_SCHEME_INCLUDE = false;
	private const DEFAULT_ENCODING = 'base64'; // base64 or hex

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	#[\Override]
	public function init(): void {
		if (!FreshRSS_Context::hasSystemConf()) {
			throw new FreshRSS_Context_Exception('System configuration not initialised!');
		}
		$this->registerHook('entry_before_display', [self::class, 'setImageProxyHook']);

		// Initialize defaults if not set
		$save = false;
		if (FreshRSS_Context::userConf()->attributeString('camo_proxy_url') == null) {
			FreshRSS_Context::userConf()->_attribute('camo_proxy_url', self::DEFAULT_CAMO_URL);
			$save = true;
		}
		if (FreshRSS_Context::userConf()->attributeString('camo_hmac_key') == null) {
			FreshRSS_Context::userConf()->_attribute('camo_hmac_key', self::DEFAULT_HMAC_KEY);
			$save = true;
		}
		if (FreshRSS_Context::userConf()->attributeBool('camo_scheme_http') === null) {
			FreshRSS_Context::userConf()->_attribute('camo_scheme_http', self::DEFAULT_SCHEME_HTTP);
			$save = true;
		}
		if (FreshRSS_Context::userConf()->attributeBool('camo_scheme_https') === null) {
			FreshRSS_Context::userConf()->_attribute('camo_scheme_https', self::DEFAULT_SCHEME_HTTPS);
			$save = true;
		}
		if (FreshRSS_Context::userConf()->attributeString('camo_scheme_default') === null) {
			FreshRSS_Context::userConf()->_attribute('camo_scheme_default', self::DEFAULT_SCHEME_DEFAULT);
			$save = true;
		}
		if (FreshRSS_Context::userConf()->attributeBool('camo_scheme_include') === null) {
			FreshRSS_Context::userConf()->_attribute('camo_scheme_include', self::DEFAULT_SCHEME_INCLUDE);
			$save = true;
		}
		if (FreshRSS_Context::userConf()->attributeString('camo_encoding') === null) {
			FreshRSS_Context::userConf()->_attribute('camo_encoding', self::DEFAULT_ENCODING);
			$save = true;
		}
		if ($save) {
			FreshRSS_Context::userConf()->save();
		}
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			FreshRSS_Context::userConf()->_attribute('camo_proxy_url', Minz_Request::paramString('camo_proxy_url', plaintext: true) ?: self::DEFAULT_CAMO_URL);
			FreshRSS_Context::userConf()->_attribute('camo_hmac_key', Minz_Request::paramString('camo_hmac_key', plaintext: true) ?: self::DEFAULT_HMAC_KEY);
			FreshRSS_Context::userConf()->_attribute('camo_scheme_http', Minz_Request::paramBoolean('camo_scheme_http'));
			FreshRSS_Context::userConf()->_attribute('camo_scheme_https', Minz_Request::paramBoolean('camo_scheme_https'));
			FreshRSS_Context::userConf()->_attribute('camo_scheme_default', Minz_Request::paramString('camo_scheme_default', plaintext: true) ?: self::DEFAULT_SCHEME_DEFAULT);
			FreshRSS_Context::userConf()->_attribute('camo_scheme_include', Minz_Request::paramBoolean('camo_scheme_include'));
			FreshRSS_Context::userConf()->_attribute('camo_encoding', Minz_Request::paramString('camo_encoding', plaintext: true) ?: self::DEFAULT_ENCODING);
			FreshRSS_Context::userConf()->save();
		}
	}

	/**
	 * Generate camo signed URL
	 * @throws FreshRSS_Context_Exception
	 */
	public static function getImageCamoUri(string $url): string {
		$parsed_url = parse_url($url);
		$scheme = $parsed_url['scheme'] ?? '';

		// Check if we should proxy this scheme
		if ($scheme === 'http') {
			if (!FreshRSS_Context::userConf()->attributeBool('camo_scheme_http')) {
				return $url;
			}
		} elseif ($scheme === 'https') {
			if (!FreshRSS_Context::userConf()->attributeBool('camo_scheme_https')) {
				return $url;
			}
		} elseif ($scheme === '') {
			// Handle protocol-relative URLs
			$schemeDefault = FreshRSS_Context::userConf()->attributeString('camo_scheme_default');
			if ($schemeDefault === 'auto') {
				$autoScheme = ((is_string($_SERVER['HTTPS'] ?? null) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https:' : 'http:');
				if (FreshRSS_Context::userConf()->attributeBool('camo_scheme_include')) {
					$url = $autoScheme . $url;
				}
			} elseif (str_starts_with($schemeDefault ?? '', 'http')) {
				if (FreshRSS_Context::userConf()->attributeBool('camo_scheme_include')) {
					$url = $schemeDefault . ':' . $url;
				}
			} else {
				// Do not proxy unschemed URLs
				return $url;
			}
		} else {
			// Unknown/unsupported scheme
			return $url;
		}

		$hmacKey = FreshRSS_Context::userConf()->attributeString('camo_hmac_key');
		$camoUrl = FreshRSS_Context::userConf()->attributeString('camo_proxy_url');
		$encoding = FreshRSS_Context::userConf()->attributeString('camo_encoding') ?: 'base64';

		if (empty($hmacKey) || empty($camoUrl)) {
			return $url; // Return original URL if configuration is incomplete
		}

		// Generate HMAC signature and encode URL according to camo format
		if ($encoding === 'hex') {
			return self::generateHexCamoUrl($hmacKey, $camoUrl, $url);
		} else {
			return self::generateBase64CamoUrl($hmacKey, $camoUrl, $url);
		}
	}

	/**
	 * Generate Base64 encoded camo URL
	 */
	private static function generateBase64CamoUrl(string $hmacKey, string $camoUrl, string $imageUrl): string {
		// Generate HMAC-SHA1
		$hmac = hash_hmac('sha1', $imageUrl, $hmacKey, true);

		// Base64 encode without padding (camo style)
		$b64Hmac = rtrim(strtr(base64_encode($hmac), '+/', '-_'), '=');
		$b64Url = rtrim(strtr(base64_encode($imageUrl), '+/', '-_'), '=');

		return rtrim($camoUrl, '/') . '/' . $b64Hmac . '/' . $b64Url;
	}

	/**
	 * Generate Hex encoded camo URL
	 */
	private static function generateHexCamoUrl(string $hmacKey, string $camoUrl, string $imageUrl): string {
		// Generate HMAC-SHA1 in hex
		$hexHmac = hash_hmac('sha1', $imageUrl, $hmacKey);
		$hexUrl = bin2hex($imageUrl);

		return rtrim($camoUrl, '/') . '/' . $hexHmac . '/' . $hexUrl;
	}

	/**
	 * @param array<string> $matches
	 * @throws FreshRSS_Context_Exception
	 */
	public static function getSrcSetUris(array $matches): string {
		return str_replace($matches[1], self::getImageCamoUri($matches[1]), $matches[0]);
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	public static function swapUris(string $content): string {
		if ($content === '') {
			return $content;
		}

		$doc = new DOMDocument();
		libxml_use_internal_errors(true); // prevent tag soup errors from showing
		$content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
		if (!is_string($content)) {
			return '';
		}
		$doc->loadHTML($content);
		$imgs = $doc->getElementsByTagName('img');
		foreach ($imgs as $img) {
			if (!($img instanceof DOMElement)) {
				continue;
			}
			if ($img->hasAttribute('src')) {
				$src = $img->getAttribute('src');
				$newSrc = self::getImageCamoUri($src);
				/*
				Due to the URL change, FreshRSS is not aware of already rendered enclosures.
				Adding data-xextension-imagecamo-original-src / srcset ensures that original URLs are present in the content for the renderer check FreshRSS_Entry->containsLink.
				*/
				$img->setAttribute('data-xextension-imagecamo-original-src', $src);
				$img->setAttribute('src', $newSrc);
			}
			if ($img->hasAttribute('srcset')) {
				$srcSet = $img->getAttribute('srcset');
				$newSrcSet = preg_replace_callback('/(?:([^\s,]+)(\s*(?:\s+\d+[wx])(?:,\s*)?))/', fn (array $matches) => self::getSrcSetUris($matches), $srcSet);
				if ($newSrcSet != null) {
					$img->setAttribute('data-xextension-imagecamo-original-srcset', $srcSet);
					$img->setAttribute('srcset', $newSrcSet);
				}
			}
		}

		$body = $doc->getElementsByTagName('body')->item(0);

		$output = $doc->saveHTML($body);
		if ($output === false) {
			return '';
		}

		$output = preg_replace('/^<body>|<\/body>$/', '', $output) ?? '';

		return $output;
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	public static function setImageProxyHook(FreshRSS_Entry $entry): FreshRSS_Entry {
		$entry->_content(
			self::swapUris($entry->content())
		);

		return $entry;
	}
}
