<?php

declare(strict_types=1);

final class ImageProxyExtension extends Minz_Extension {
	// Defaults
	private const PROXY_URL = 'https://wsrv.nl/?url=';
	private const SCHEME_HTTP = true;
	private const SCHEME_HTTPS = false;
	private const SCHEME_DEFAULT = 'auto';
	private const SCHEME_INCLUDE = false;
	private const URL_ENCODE = true;

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	#[\Override]
	public function init(): void {
		if (!FreshRSS_Context::hasSystemConf()) {
			throw new FreshRSS_Context_Exception('System configuration not initialised!');
		}
		$this->registerHook('entry_before_display', [self::class, 'setImageProxyHook']);
		// Defaults
		$save = false;
		if (FreshRSS_Context::userConf()->attributeString('image_proxy_url') == null) {
			FreshRSS_Context::userConf()->_attribute('image_proxy_url', self::PROXY_URL);
			$save = true;
		}
		if (FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_http') === null) {
			FreshRSS_Context::userConf()->_attribute('image_proxy_scheme_http', self::SCHEME_HTTP);
			$save = true;
		}
		if (FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_https') === null) {
			FreshRSS_Context::userConf()->_attribute('image_proxy_scheme_https', self::SCHEME_HTTPS);
			$save = true;
		}
		if (FreshRSS_Context::userConf()->attributeString('image_proxy_scheme_default') === null) {
			FreshRSS_Context::userConf()->_attribute('image_proxy_scheme_default', self::SCHEME_DEFAULT);
			$save = true;
		}
		if (FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_include') === null) {
			FreshRSS_Context::userConf()->_attribute('image_proxy_scheme_include', self::SCHEME_INCLUDE);
			$save = true;
		}
		if (FreshRSS_Context::userConf()->attributeBool('image_proxy_url_encode') === null) {
			FreshRSS_Context::userConf()->_attribute('image_proxy_url_encode', self::URL_ENCODE);
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
			FreshRSS_Context::userConf()->_attribute('image_proxy_url', Minz_Request::paramString('image_proxy_url', plaintext: true) ?: self::PROXY_URL);
			FreshRSS_Context::userConf()->_attribute('image_proxy_scheme_http', Minz_Request::paramBoolean('image_proxy_scheme_http'));
			FreshRSS_Context::userConf()->_attribute('image_proxy_scheme_https', Minz_Request::paramBoolean('image_proxy_scheme_https'));
			FreshRSS_Context::userConf()->_attribute('image_proxy_scheme_default', Minz_Request::paramString('image_proxy_scheme_default', plaintext: true) ?: self::SCHEME_DEFAULT);
			FreshRSS_Context::userConf()->_attribute('image_proxy_scheme_include', Minz_Request::paramBoolean('image_proxy_scheme_include'));
			FreshRSS_Context::userConf()->_attribute('image_proxy_url_encode', Minz_Request::paramBoolean('image_proxy_url_encode'));
			FreshRSS_Context::userConf()->save();
		}
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	public static function getProxyImageUri(string $url): string {
		$parsed_url = parse_url($url);
		$scheme = $parsed_url['scheme'] ?? '';
		if ($scheme === 'http') {
			if (!FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_http')) {
				return $url;
			}
			if (!FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_include') == '') {
				$url = substr($url, 7);	// http://
			}
		} elseif ($scheme === 'https') {
			if (!FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_https') == '') {
				return $url;
			}
			if (!FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_include') == '') {
				$url = substr($url, 8);	// https://
			}
		} elseif ($scheme === '') {
			if (FreshRSS_Context::userConf()->attributeString('image_proxy_scheme_default') === 'auto') {
				if (FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_include')) {
					$url = ((is_string($_SERVER['HTTPS'] ?? null) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https:' : 'http:') . $url;
				}
			} elseif (str_starts_with(FreshRSS_Context::userConf()->attributeString('image_proxy_scheme_default') ?? '', 'http')) {
				if (FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_include')) {
					$url = FreshRSS_Context::userConf()->attributeString('image_proxy_scheme_default') . ':' . $url;
				}
			} else {	// do not proxy unschemed ("//path/...") URLs
				return $url;
			}
		} else {	// unknown/unsupported (non-http) scheme
			return $url;
		}
		if (FreshRSS_Context::userConf()->attributeBool('image_proxy_url_encode')) {
			$url = rawurlencode($url);
		}
		return FreshRSS_Context::userConf()->attributeString('image_proxy_url') . $url;
	}

	/**
	 * @param array<string> $matches
	 * @throws FreshRSS_Context_Exception
	 */
	public static function getSrcSetUris(array $matches): string {
		return str_replace($matches[1], self::getProxyImageUri($matches[1]), $matches[0]);
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	public static function swapUris(string $content): string {
		if ($content === '') {
			return $content;
		}

		$doc = new DOMDocument();
		libxml_use_internal_errors(true);	// prevent tag soup errors from showing
		$doc->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
		$imgs = $doc->getElementsByTagName('img');
		foreach ($imgs as $img) {
			if (!($img instanceof DOMElement)) {
				continue;
			}
			if ($img->hasAttribute('src')) {
				$src = $img->getAttribute('src');
				$newSrc = self::getProxyImageUri($src);
				/*
				Due to the URL change, FreshRSS is not aware of already rendered enclosures.
				Adding data-xextension-imageproxy-original-src / srcset ensures that original URLs are present in the content for the renderer check FreshRSS_Entry->containsLink.
				*/
				$img->setAttribute('data-xextension-imageproxy-original-src', $src);
				$img->setAttribute('src', $newSrc);
			}
			if ($img->hasAttribute('srcset')) {
				$srcSet = $img->getAttribute('srcset');
				$newSrcSet = preg_replace_callback('/(?:([^\s,]+)(\s*(?:\s+\d+[wx])(?:,\s*)?))/', fn (array $matches) => self::getSrcSetUris($matches), $srcSet);
				if ($newSrcSet != null) {
					$img->setAttribute('data-xextension-imageproxy-original-srcset', $srcSet);
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
