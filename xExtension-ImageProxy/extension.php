<?php

declare(strict_types=1);

final class ImageProxyExtension extends Minz_Extension {
	// Defaults
	private const PROXY_URL = 'https://wsrv.nl/?url=';
	private const SCHEME_HTTP = '1';
	private const SCHEME_HTTPS = '';
	private const SCHEME_DEFAULT = 'auto';
	private const SCHEME_INCLUDE = '';
	private const URL_ENCODE = '1';

	public function init(): void {
		if (FreshRSS_Context::systemConf() === null) {
			throw new FreshRSS_Context_Exception('System configuration not initialised!');
		}
		$this->registerHook(
			'entry_before_display',
			array('ImageProxyExtension', 'setImageProxyHook')
		);
		// Defaults
		$save = false;
		if (is_null(FreshRSS_Context::userConf()->image_proxy_url)) {
			FreshRSS_Context::userConf()->image_proxy_url = self::PROXY_URL;
			$save = true;
		}
		if (is_null(FreshRSS_Context::userConf()->image_proxy_scheme_http)) {
			FreshRSS_Context::userConf()->image_proxy_scheme_http = self::SCHEME_HTTP;
			$save = true;
		}
		if (is_null(FreshRSS_Context::userConf()->image_proxy_scheme_https)) {
			FreshRSS_Context::userConf()->image_proxy_scheme_https = self::SCHEME_HTTPS;
			// Legacy
			if (!is_null(FreshRSS_Context::userConf()->image_proxy_force)) {
				FreshRSS_Context::userConf()->image_proxy_scheme_https = FreshRSS_Context::userConf()->image_proxy_force;
				FreshRSS_Context::userConf()->image_proxy_force = null;  // Minz -> unset
			}
			$save = true;
		}
		if (is_null(FreshRSS_Context::userConf()->image_proxy_scheme_default)) {
			FreshRSS_Context::userConf()->image_proxy_scheme_default = self::SCHEME_DEFAULT;
			$save = true;
		}
		if (is_null(FreshRSS_Context::userConf()->image_proxy_scheme_include)) {
			FreshRSS_Context::userConf()->image_proxy_scheme_include = self::SCHEME_INCLUDE;
			$save = true;
		}
		if (is_null(FreshRSS_Context::userConf()->image_proxy_url_encode)) {
			FreshRSS_Context::userConf()->image_proxy_url_encode = self::URL_ENCODE;
			$save = true;
		}
		if ($save) {
			FreshRSS_Context::userConf()->save();
		}
	}

	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			FreshRSS_Context::userConf()->image_proxy_url = Minz_Request::paramString('image_proxy_url', true) ?: self::PROXY_URL;
			FreshRSS_Context::userConf()->image_proxy_scheme_http = Minz_Request::paramString('image_proxy_scheme_http');
			FreshRSS_Context::userConf()->image_proxy_scheme_https = Minz_Request::paramString('image_proxy_scheme_https');
			FreshRSS_Context::userConf()->image_proxy_scheme_default = Minz_Request::paramString('image_proxy_scheme_default') ?: self::SCHEME_DEFAULT;
			FreshRSS_Context::userConf()->image_proxy_scheme_include = Minz_Request::paramString('image_proxy_scheme_include');
			FreshRSS_Context::userConf()->image_proxy_url_encode = Minz_Request::paramString('image_proxy_url_encode');
			FreshRSS_Context::userConf()->save();
		}
	}

	public static function getProxyImageUri(string $url): string {
		$parsed_url = parse_url($url);
		$scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] : null;
		if ($scheme === 'http') {
			if (!FreshRSS_Context::userConf()->image_proxy_scheme_http) {
				return $url;
			}
			if (!FreshRSS_Context::userConf()->image_proxy_scheme_include) {
				$url = substr($url, 7);  // http://
			}
		} elseif ($scheme === 'https') {
			if (!FreshRSS_Context::userConf()->image_proxy_scheme_https) {
				return $url;
			}
			if (!FreshRSS_Context::userConf()->image_proxy_scheme_include) {
				$url = substr($url, 8);  // https://
			}
		} elseif (empty($scheme)) {
			if (FreshRSS_Context::userConf()->image_proxy_scheme_default === 'auto') {
				if (FreshRSS_Context::userConf()->image_proxy_scheme_include) {
					$url = ((!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https:' : 'http:') . $url;
				}
			} elseif (substr(FreshRSS_Context::userConf()->image_proxy_scheme_default, 0, 4) === 'http') {
				if (FreshRSS_Context::userConf()->image_proxy_scheme_include) {
					$url = FreshRSS_Context::userConf()->image_proxy_scheme_default . ':' . $url;
				}
			} else {  // do not proxy unschemed ("//path/...") URLs
				return $url;
			}
		} else {  // unknown/unsupported (non-http) scheme
			return $url;
		}
		if (FreshRSS_Context::userConf()->image_proxy_url_encode) {
			$url = rawurlencode($url);
		}
		return FreshRSS_Context::userConf()->image_proxy_url . $url;
	}

	/**
	 * @param array<string> $matches
	 * @return string
	 */
	public static function getSrcSetUris(array $matches): string {
		$result = str_replace($matches[1], self::getProxyImageUri($matches[1]), $matches[0]) ?: '';
		return $result;
	}

	public static function swapUris(string $content): string {
		if (empty($content)) {
			return $content;
		}

		$doc = new DOMDocument();
		libxml_use_internal_errors(true); // prevent tag soup errors from showing
		$doc->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
		$imgs = $doc->getElementsByTagName('img');
		foreach ($imgs as $img) {
			if ($img->hasAttribute('src')) {
				$newSrc = self::getProxyImageUri($img->getAttribute('src'));
				$img->setAttribute('src', $newSrc);
			}
			if ($img->hasAttribute('srcset')) {
				$newSrcSet = preg_replace_callback('/(?:([^\s,]+)(\s*(?:\s+\d+[wx])(?:,\s*)?))/', fn($matches) => self::getSrcSetUris($matches), $img->getAttribute('srcset')) ?: '';
				$img->setAttribute('srcset', $newSrcSet);
			}
		}

		return $doc->saveHTML() ?: '';
	}

	public static function setImageProxyHook(FreshRSS_Entry $entry): FreshRSS_Entry {
		$entry->_content(
			self::swapUris($entry->content())
		);

		return $entry;
	}
}
