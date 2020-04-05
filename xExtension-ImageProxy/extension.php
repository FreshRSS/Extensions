<?php

class ImageProxyExtension extends Minz_Extension {
	// Defaults
	private const PROXY_URL = 'https://images.weserv.nl/?url=';
	private const SCHEME_HTTP = '1';
	private const SCHEME_HTTPS = '';
	private const SCHEME_DEFAULT = 'http';
	private const SCHEME_INCLUDE = '';
	private const URL_ENCODE = '1';

	public function init() {
		$this->registerHook('entry_before_display',
		                    array('ImageProxyExtension', 'setImageProxyHook'));
		// Defaults
		$save = false;
		if (is_null(FreshRSS_Context::$user_conf->image_proxy_url)) {
			FreshRSS_Context::$user_conf->image_proxy_url = self::PROXY_URL;
			$save = true;
		}
		if (is_null(FreshRSS_Context::$user_conf->image_proxy_scheme_http)) {
			FreshRSS_Context::$user_conf->image_proxy_scheme_http = self::SCHEME_HTTP;
			$save = true;
		}
		if (is_null(FreshRSS_Context::$user_conf->image_proxy_scheme_https)) {
			FreshRSS_Context::$user_conf->image_proxy_scheme_https = self::SCHEME_HTTPS;
			// Legacy
			if (!is_null(FreshRSS_Context::$user_conf->image_proxy_force)) {
				FreshRSS_Context::$user_conf->image_proxy_scheme_https = FreshRSS_Context::$user_conf->image_proxy_force;
				FreshRSS_Context::$user_conf->image_proxy_force = null;  // Minz -> unset
			}
			$save = true;
		}
		if (is_null(FreshRSS_Context::$user_conf->image_proxy_scheme_default)) {
			FreshRSS_Context::$user_conf->image_proxy_scheme_default = self::SCHEME_DEFAULT;
			$save = true;
		}
		if (is_null(FreshRSS_Context::$user_conf->image_proxy_scheme_include)) {
			FreshRSS_Context::$user_conf->image_proxy_scheme_include = self::SCHEME_INCLUDE;
			$save = true;
		}
		if (is_null(FreshRSS_Context::$user_conf->image_proxy_url_encode)) {
			FreshRSS_Context::$user_conf->image_proxy_url_encode = self::URL_ENCODE;
			$save = true;
		}
		if ($save) {
			FreshRSS_Context::$user_conf->save();
		}
	}

	public function handleConfigureAction() {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			FreshRSS_Context::$user_conf->image_proxy_url = Minz_Request::param('image_proxy_url', self::PROXY_URL);
			FreshRSS_Context::$user_conf->image_proxy_scheme_http = Minz_Request::param('image_proxy_scheme_http', '');
			FreshRSS_Context::$user_conf->image_proxy_scheme_https = Minz_Request::param('image_proxy_scheme_https', '');
			FreshRSS_Context::$user_conf->image_proxy_scheme_default = Minz_Request::param('image_proxy_scheme_default', self::SCHEME_DEFAULT);
			FreshRSS_Context::$user_conf->image_proxy_scheme_include = Minz_Request::param('image_proxy_scheme_include', '');
			FreshRSS_Context::$user_conf->image_proxy_url_encode = Minz_Request::param('image_proxy_url_encode', '');
			FreshRSS_Context::$user_conf->save();
		}
	}

	public static function getProxyImageUri($url) {
		$parsed_url = parse_url($url);
		$scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] : null;
		if ($scheme === 'http') {
			if (!FreshRSS_Context::$user_conf->image_proxy_scheme_http) return $url;
			if (!FreshRSS_Context::$user_conf->image_proxy_scheme_include) {
				$url = substr($url, 7);  // http://
			}
		}
		else if ($scheme === 'https') {
			if (!FreshRSS_Context::$user_conf->image_proxy_scheme_https) return $url;
			if (!FreshRSS_Context::$user_conf->image_proxy_scheme_include) {
				$url = substr($url, 8);  // https://
			}
		}
		else if (empty($scheme)) {
			if (substr(FreshRSS_Context::$user_conf->image_proxy_scheme_default,0, 4) !== 'http') return $url;
			if (FreshRSS_Context::$user_conf->image_proxy_scheme_include) {
				$url = FreshRSS_Context::$user_conf->image_proxy_scheme_default . '://' . $url;
			}
		}
		else {  // unknown/unsupported (non-http) scheme
			return $url;
		}
		if (FreshRSS_Context::$user_conf->image_proxy_url_encode) {
			$url = rawurlencode($url);
		}
		return FreshRSS_Context::$user_conf->image_proxy_url . $url;
	}

	public static function getSrcSetUris($matches) {
		return str_replace($matches[1], self::getProxyImageUri($matches[1]), $matches[0]);
	}

	public static function swapUris($content) {
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
				$newSrcSet = preg_replace_callback('/(?:([^\s,]+)(\s*(?:\s+\d+[wx])(?:,\s*)?))/', 'self::getSrcSetUris', $img->getAttribute('srcset'));
				$img->setAttribute('srcset', $newSrcSet);
			}
		}

		return $doc->saveHTML();
	}

	public static function setImageProxyHook($entry) {
		$entry->_content(
			self::swapUris($entry->content())
		);

		return $entry;
	}
}
