<?php

class ImageProxyExtension extends Minz_Extension {
	public function init() {
		$this->registerHook('entry_before_display',
							array('ImageProxyExtension', 'setImageProxyHook'));

		if (FreshRSS_Context::$user_conf->image_proxy_url != '') {
			self::$proxy_url = FreshRSS_Context::$user_conf->image_proxy_url;
		}
	}

	public static $proxy_url = 'https://images.weserv.nl/?url=';

	public function handleConfigureAction() {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			FreshRSS_Context::$user_conf->image_proxy_url = Minz_Request::param('image_proxy_url', '');
			FreshRSS_Context::$user_conf->image_proxy_force = Minz_Request::param('image_proxy_force', '');
			FreshRSS_Context::$user_conf->save();
		}
	}

	public static function getProxyImageUri($url) {
		$parsed_url = parse_url($url);
		if (isset($parsed_url['scheme']) && $parsed_url['scheme'] === 'http') {
			$url = self::$proxy_url . rawurlencode(substr($url, strlen('http://')));
		}
		// force proxy even with https, if set by the user
		else if (isset($parsed_url['scheme']) &&
				$parsed_url['scheme'] === 'https' &&
				FreshRSS_Context::$user_conf->image_proxy_force) {
			$url = self::$proxy_url . rawurlencode(substr($url, strlen('https://')));
		}
		// oddly enough there are protocol-less IMG SRC attributes that don't actually work with HTTPS
		// so I guess we should just run 'em all through the proxy
		else if (empty($parsed_url['scheme'])) {
			$url = self::$proxy_url . rawurlencode($url);
		}

		return $url;
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
