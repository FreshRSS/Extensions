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
	private const EXCLUDED_DOMAINS = '';

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	#[\Override]
	public function init(): void {
		if (!FreshRSS_Context::hasSystemConf()) {
			throw new FreshRSS_Context_Exception('System configuration not initialised!');
		}
		$this->registerHook('entry_before_display', self::setImageProxyHook(...));
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
		if (FreshRSS_Context::userConf()->attributeString('image_proxy_excluded_domains') === null) {
			FreshRSS_Context::userConf()->_attribute('image_proxy_excluded_domains', self::EXCLUDED_DOMAINS);
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
			FreshRSS_Context::userConf()->_attribute('image_proxy_scheme_default',
				Minz_Request::paramString('image_proxy_scheme_default', plaintext: true) ?: self::SCHEME_DEFAULT);
			FreshRSS_Context::userConf()->_attribute('image_proxy_scheme_include', Minz_Request::paramBoolean('image_proxy_scheme_include'));
			FreshRSS_Context::userConf()->_attribute('image_proxy_url_encode', Minz_Request::paramBoolean('image_proxy_url_encode'));
			FreshRSS_Context::userConf()->_attribute('image_proxy_excluded_domains', Minz_Request::paramString('image_proxy_excluded_domains', plaintext: true));
			FreshRSS_Context::userConf()->save();
		}
	}

	/**
	 * Whether the host is one of the domains excluded from proxying, or a subdomain of one of them.
	 * @throws FreshRSS_Context_Exception
	 */
	private static function isExcludedHost(string $host): bool {
		$host = trim(strtolower($host), '[].');
		if ($host === '') {
			return false;
		}
		$excludedDomains = strtolower(FreshRSS_Context::userConf()->attributeString('image_proxy_excluded_domains') ?? '');
		$domains = preg_split('/\s+/', $excludedDomains, flags: PREG_SPLIT_NO_EMPTY) ?: [];
		foreach ($domains as $domain) {
			$domain = ltrim(trim($domain, '[]'), '*.');	// Accept `*.example.org`, `.example.org`, and `[::1]`
			if ($domain !== '' && ($host === $domain || str_ends_with($host, '.' . $domain))) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	public static function getProxyImageUri(string $url): string {
		$parsed_url = parse_url($url);
		if (isset($parsed_url['host']) && self::isExcludedHost($parsed_url['host'])) {
			return $url;
		}
		$scheme = $parsed_url['scheme'] ?? '';
		if ($scheme === 'http') {
			if (!FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_http')) {
				return $url;
			}
			if (!FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_include')) {
				$url = substr($url, 7);	// http://
			}
		} elseif ($scheme === 'https') {
			if (!FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_https')) {
				return $url;
			}
			if (!FreshRSS_Context::userConf()->attributeBool('image_proxy_scheme_include')) {
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
	 * Same as getProxyImageUri() for the HTML-encoded URLs that FreshRSS stores, e.g. the ones of thumbnails.
	 * @throws FreshRSS_Context_Exception
	 */
	public static function getProxyImageHtmlUri(string $url): string {
		return htmlspecialchars(self::getProxyImageUri(htmlspecialchars_decode($url, ENT_QUOTES)), ENT_COMPAT, 'UTF-8');
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
		$content = mb_encode_numericentity($content, [0x80, 0x10FFFF, 0, ~0], 'UTF-8');
		$doc->loadHTML($content);
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
				Adding data-xextension-imageproxy-original-src / srcset ensures that
				original URLs are present in the content for the renderer check FreshRSS_Entry->containsLink.
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

		// Video posters are images too; keep the original URL for the same reason as above
		$videos = $doc->getElementsByTagName('video');
		foreach ($videos as $video) {
			if (!($video instanceof DOMElement) || !$video->hasAttribute('poster')) {
				continue;
			}
			$poster = $video->getAttribute('poster');
			$video->setAttribute('data-xextension-imageproxy-original-poster', $poster);
			$video->setAttribute('poster', self::getProxyImageUri($poster));
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
		// The enclosures become part of the content, so only include them if the feed displays them
		// We most likely already have the feed object in cache, otherwise make a request
		$feed = FreshRSS_Category::findFeed(FreshRSS_Context::categories(), $entry->feedId()) ?? $entry->feed();
		$withEnclosures = $feed?->attributeBoolean('display_enclosures') ?? true;
		$entry->_content(self::swapUris($entry->content($withEnclosures)));
		self::swapThumbnailUris($entry, $withEnclosures);

		return $entry;
	}

	/**
	 * Proxy the thumbnails of the entry and of its enclosures, which are shown in the list of articles.
	 * @throws FreshRSS_Context_Exception
	 */
	private static function swapThumbnailUris(FreshRSS_Entry $entry, bool $withEnclosures): void {
		$thumbnail = $entry->attributeArray('thumbnail');
		if (is_string($thumbnail['url'] ?? null) && $thumbnail['url'] !== '') {
			$url = self::getProxyImageHtmlUri($thumbnail['url']);
			// When displaying the enclosures, FreshRSS appends the thumbnail to the content unless its URL is found there.
			// The content set above already includes the thumbnail, so to avoid showing it twice,
			// only proxy it if the proxied URL is found in the content as well (e.g. not if the content only links to it).
			if (!$withEnclosures || preg_match('/([\'"])' . preg_quote($url, '/') . '\1/', $entry->content(false)) === 1) {
				$thumbnail['url'] = $url;
				$entry->_attribute('thumbnail', $thumbnail);
			}
		}

		// The thumbnails of the enclosures are not compared to the content, so they can always be proxied
		$enclosures = $entry->attributeArray('enclosures') ?? [];
		foreach ($enclosures as $key => $enclosure) {
			if (is_array($enclosure) && is_array($enclosure['thumbnails'] ?? null)) {
				$enclosure['thumbnails'] = array_map(
					fn (mixed $src) => is_string($src) ? self::getProxyImageHtmlUri($src) : $src,
					$enclosure['thumbnails']
				);
				$enclosures[$key] = $enclosure;
			}
		}
		if ($enclosures !== []) {
			$entry->_attribute('enclosures', $enclosures);
		}
	}
}
