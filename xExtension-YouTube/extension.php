<?php

declare(strict_types=1);

/**
 * Class YouTubeExtension
 *
 * @author Kevin Papst, Inverle
 */
final class YouTubeExtension extends Minz_Extension
{
	/**
	 * Video player width
	 */
	private int $width = 560;
	/**
	 * Video player height
	 */
	private int $height = 315;
	/**
	 * Whether we display the original feed content
	 */
	private bool $showContent = false;
	/**
	 * Whether channel icons should be automatically downloaded and set for feeds
	 */
	private bool $downloadIcons = false;
	/**
	 * Switch to enable the Youtube No-Cookie domain
	 */
	private bool $useNoCookie = false;

	/**
	 * Initialize this extension
	 */
	#[\Override]
	public function init(): void
	{
		$this->registerHook('entry_before_display', [$this, 'embedYouTubeVideo']);
		$this->registerHook('check_url_before_add', [self::class, 'convertYoutubeFeedUrl']);
		$this->registerHook('custom_favicon_hash', [$this, 'iconHashParams']);
		$this->registerHook('custom_favicon_btn_url', [$this, 'iconBtnUrl']);
		$this->registerHook('feed_before_insert', [$this, 'feedBeforeInsert']);
		if (Minz_Request::controllerName() === 'extension') {
			$this->registerHook('js_vars', [self::class, 'jsVars']);
			Minz_View::appendScript($this->getFileUrl('fetchIcons.js'));
		}
		$this->registerTranslates();
	}

	/**
	 * @param array<string,mixed> $vars
	 * @return array<string,mixed>
	 */
	public static function jsVars(array $vars): array {
		$vars['yt_i18n'] = [
			'fetching_icons' => _t('ext.yt_videos.fetching_icons'),
		];
		return $vars;
	}

	public function isYtFeed(string $website): bool {
		return str_starts_with($website, 'https://www.youtube.com/');
	}

	public function isShort(string $website): bool {
		return str_starts_with($website, 'https://www.youtube.com/shorts');
	}
	public function convertShortToWatch(string $shortUrl): string {
		$prefix = 'https://www.youtube.com/shorts/';

		if (str_starts_with($shortUrl, $prefix)) {
			$videoId = str_replace($prefix, '', $shortUrl);
			return 'https://www.youtube.com/watch?v=' . $videoId;
		}

		return $shortUrl;
	}

	public function iconBtnUrl(FreshRSS_Feed $feed): ?string {
		if (!$this->isYtFeed($feed->website()) || $feed->attributeString('customFaviconExt') === $this->getName()) {
			return null;
		}
		return _url('extension', 'configure', 'e', urlencode($this->getName()));
	}

	public function iconHashParams(FreshRSS_Feed $feed): ?string {
		if ($feed->customFaviconExt() !== $this->getName()) {
			return null;
		}
		return 'yt' . $feed->website() . $feed->proxyParam();
	}

	/**
	 * @throws Minz_PDOConnectionException
	 * @throws Minz_ConfigurationNamespaceException
	 */
	public function ajaxGetYtFeeds(): void {
		$feedDAO = FreshRSS_Factory::createFeedDao();
		$ids = $feedDAO->listFeedsIds();

		$feeds = [];

		foreach ($ids as $feedId) {
			$feed = $feedDAO->searchById($feedId);
			if ($feed === null) {
				continue;
			}
			if ($this->isYtFeed($feed->website())) {
				$feeds[] = [
					'id' => $feed->id(),
					'title' => $feed->name(true),
				];
			}
		}

		header('Content-Type: application/json; charset=UTF-8');
		exit(json_encode($feeds));
	}

	/**
	 * @throws Minz_PDOConnectionException
	 * @throws Minz_ConfigurationNamespaceException
	 * @throws Minz_PermissionDeniedException
	 * @throws FreshRSS_UnsupportedImageFormat_Exception
	 * @throws FreshRSS_Context_Exception
	 */
	public function ajaxFetchIcon(): void {
		$feedDAO = FreshRSS_Factory::createFeedDao();

		$feed = $feedDAO->searchById(Minz_Request::paramInt('id'));
		if ($feed === null) {
			Minz_Error::error(404);
			return;
		}
		$this->setIconForFeed($feed, setValues: true);

		exit('OK');
	}

	/**
	 * @throws Minz_PDOConnectionException
	 * @throws Minz_ConfigurationNamespaceException
	 * @throws Minz_PermissionDeniedException
	 */
	public function resetAllIcons(): void {
		$feedDAO = FreshRSS_Factory::createFeedDao();
		$ids = $feedDAO->listFeedsIds();

		foreach ($ids as $feedId) {
			$feed = $feedDAO->searchById($feedId);
			if ($feed === null) {
				continue;
			}
			if ($feed->customFaviconExt() === $this->getName()) {
				$v = [];
				try {
					$feed->resetCustomFavicon(values: $v);
				} catch (FreshRSS_Feed_Exception $_) {
					$this->warnLog('Failed to reset favicon for feed “' . $feed->name(true) . '”: feed error!');
				}
			}
		}
	}

	/**
	 * @throws Minz_PermissionDeniedException
	 */
	public function warnLog(string $s): void {
		Minz_Log::warning('[' . $this->getName() . '] ' . $s);
	}
	/**
	 * @throws Minz_PermissionDeniedException
	 */
	public function debugLog(string $s): void {
		Minz_Log::debug('[' . $this->getName() . '] ' . $s);
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_PermissionDeniedException
	 * @throws FreshRSS_UnsupportedImageFormat_Exception
	 */
	public function feedBeforeInsert(FreshRSS_Feed $feed): FreshRSS_Feed {
		$this->loadConfigValues();

		if ($this->downloadIcons) {
			return $this->setIconForFeed($feed);
		}

		return $feed;
	}

	/**
	 * @throws Minz_PermissionDeniedException
	 * @throws FreshRSS_UnsupportedImageFormat_Exception
	 * @throws FreshRSS_Context_Exception
	 */
	public function setIconForFeed(FreshRSS_Feed $feed, bool $setValues = false): FreshRSS_Feed {
		if (!$this->isYtFeed($feed->website())) {
			return $feed;
		}

		// Return early if the icon had already been downloaded before
		$v = $setValues ? [] : null;
		$oldAttributes = $feed->attributes();
		try {
			$path = $feed->setCustomFavicon(extName: $this->getName(), disallowDelete: true, values: $v);
			if ($path === null) {
				$feed->_attributes($oldAttributes);
				return $feed;
			} elseif (file_exists($path)) {
				$this->debugLog('Icon had already been downloaded before for feed “' . $feed->name(true) . '”: returning early!');
				return $feed;
			}
		} catch (FreshRSS_Feed_Exception $_) {
			$this->warnLog('Failed to set custom favicon for feed “' . $feed->name(true) . '”: feed error!');
			$feed->_attributes($oldAttributes);
			return $feed;
		}

		$feed->_attributes($oldAttributes);
		$this->debugLog('downloading icon for feed “' . $feed->name(true) . '"');

		$url = $feed->website();
		/** @var array<int, bool|int|string> */
		$curlOptions = $feed->attributeArray('curl_params') ?? [];

		$ch = curl_init();
		if ($ch === false) {
			return $feed;
		}

		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_USERAGENT => FRESHRSS_USERAGENT,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
		]);
		curl_setopt_array($ch, FreshRSS_Context::systemConf()->curl_options);
		curl_setopt_array($ch, $curlOptions);

		$html = curl_exec($ch);

		$dom = new DOMDocument();

		if (!is_string($html) || !@$dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
			$this->warnLog('Fail while downloading icon for feed “' . $feed->name(true) . '”: failed to load HTML!');
			return $feed;
		}

		$xpath = new DOMXPath($dom);
		$metaElem = $xpath->query('//meta[@name="twitter:image"]');

		if ($metaElem === false) {
			$this->warnLog('Fail while downloading icon for feed “' . $feed->name(true) . '”: icon URL couldn’t be found!');
			return $feed;
		}
		$iconElem = $metaElem->item(0);

		if (!($iconElem instanceof DOMElement)) {
			$this->warnLog('Fail while downloading icon for feed “' . $feed->name(true) . '”: icon URL couldn’t be found!');
			return $feed;
		}

		$iconUrl = $iconElem->getAttribute('content');
		if ($iconUrl == '') {
			$this->warnLog('Fail while downloading icon for feed “' . $feed->name(true) . '”: icon URL is empty!');
			return $feed;
		}

		curl_setopt($ch, CURLOPT_URL, $iconUrl);
		$contents = curl_exec($ch);
		if (!is_string($contents)) {
			$this->warnLog('Fail while downloading icon for feed “' . $feed->name(true) . '”: empty contents!');
			return $feed;
		}

		try {
			$feed->setCustomFavicon($contents, extName: $this->getName(), disallowDelete: true, values: $v, overrideCustomIcon: true);
		} catch (FreshRSS_UnsupportedImageFormat_Exception $_) {
			$this->warnLog('Failed to set custom favicon for feed “' . $feed->name(true) . '”: unsupported image format!');
			return $feed;
		} catch (FreshRSS_Feed_Exception $_) {
			$this->warnLog('Failed to set custom favicon for feed “' . $feed->name(true) . '”: feed error!');
			return $feed;
		}

		return $feed;
	}

	public static function convertYoutubeFeedUrl(string $url): string
	{
		$matches = [];

		if (preg_match('#^https?://www\.youtube\.com/channel/([0-9a-zA-Z_-]{6,36})#', $url, $matches) === 1) {
			return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $matches[1];
		}

		if (preg_match('#^https?://www\.youtube\.com/user/([0-9a-zA-Z_-]{6,36})#', $url, $matches) === 1) {
			return 'https://www.youtube.com/feeds/videos.xml?user=' . $matches[1];
		}

		return $url;
	}

	/**
	 * Initializes the extension configuration, if the user context is available.
	 * Do not call that in your extensions init() method, it can't be used there.
	 * @throws FreshRSS_Context_Exception
	 */
	public function loadConfigValues(): void
	{
		if (!class_exists('FreshRSS_Context', false) || !FreshRSS_Context::hasUserConf()) {
			return;
		}

		$width = FreshRSS_Context::userConf()->attributeInt('yt_player_width');
		if ($width !== null) {
			$this->width = $width;
		}

		$height = FreshRSS_Context::userConf()->attributeInt('yt_player_height');
		if ($height !== null) {
			$this->height = $height;
		}

		$showContent = FreshRSS_Context::userConf()->attributeBool('yt_show_content');
		if ($showContent !== null) {
			$this->showContent = $showContent;
		}

		$downloadIcons = FreshRSS_Context::userConf()->attributeBool('yt_download_channel_icons');
		if ($downloadIcons !== null) {
			$this->downloadIcons = $downloadIcons;
		}

		$noCookie = FreshRSS_Context::userConf()->attributeBool('yt_nocookie');
		if ($noCookie !== null) {
			$this->useNoCookie = $noCookie;
		}
	}

	/**
	 * Returns the width in pixel for the YouTube player iframe.
	 * You have to call loadConfigValues() before this one, otherwise you get default values.
	 */
	public function getWidth(): int
	{
		return $this->width;
	}

	/**
	 * Returns the height in pixel for the YouTube player iframe.
	 * You have to call loadConfigValues() before this one, otherwise you get default values.
	 */
	public function getHeight(): int
	{
		return $this->height;
	}

	/**
	 * Returns whether this extension displays the content of the YouTube feed.
	 * You have to call loadConfigValues() before this one, otherwise you get default values.
	 */
	public function isShowContent(): bool
	{
		return $this->showContent;
	}

	/**
	 * Returns whether the automatic icon download option is enabled.
	 * You have to call loadConfigValues() before this one, otherwise you get default values.
	 */
	public function isDownloadIcons(): bool
	{
		return $this->downloadIcons;
	}

	/**
	 * Returns if this extension should use youtube-nocookie.com instead of youtube.com.
	 * You have to call loadConfigValues() before this one, otherwise you get default values.
	 */
	public function isUseNoCookieDomain(): bool
	{
		return $this->useNoCookie;
	}

	/**
	 * Inserts the YouTube video iframe into the content of an entry, if the entries link points to a YouTube watch URL.
	 * @throws FreshRSS_Context_Exception
	 */
	public function embedYouTubeVideo(FreshRSS_Entry $entry): FreshRSS_Entry
	{
		$link = $entry->link();

		if ($this->isShort($link)) {
			$link = $this->convertShortToWatch($link);
		}

		if (preg_match('#^https?://www\.youtube\.com/watch\?v=|/videos/watch/[0-9a-f-]{36}$#', $link) !== 1) {
			return $entry;
		}

		$this->loadConfigValues();

		if (stripos($entry->content(), '<iframe class="youtube-plugin-video"') !== false) {
			return $entry;
		}

		if (stripos($link, 'www.youtube.com/watch?v=') !== false) {
			$html = $this->getHtmlContentForLink($entry, $link);
		}
		else { //peertube
			$html = $this->getHtmlPeerTubeContentForLink($entry, $link);
		}

		$entry->_content($html);
		return $entry;
	}

	/**
	 * Returns an HTML <iframe> for a given Youtube watch URL (www.youtube.com/watch?v=)
	 */
	public function getHtmlContentForLink(FreshRSS_Entry $entry, string $link): string
	{
		$domain = 'www.youtube.com';
		if ($this->useNoCookie) {
			$domain = 'www.youtube-nocookie.com';
		}
		$url = str_replace('//www.youtube.com/watch?v=', '//'.$domain.'/embed/', $link);
		$url = str_replace('http://', 'https://', $url);

		return $this->getHtml($entry, $url);
	}

	/**
	* Returns an HTML <iframe> for a given PeerTube watch URL
	*/
	public function getHtmlPeerTubeContentForLink(FreshRSS_Entry $entry, string $link): string
	{
		$url = str_replace('/watch', '/embed', $link);

		return $this->getHtml($entry, $url);
	}

	/**
	 * Returns an HTML <iframe> for a given URL for the configured width and height, with content ignored, appended or formatted.
	 */
	public function getHtml(FreshRSS_Entry $entry, string $url): string
	{
		$content = '';

		$iframe = '<iframe class="youtube-plugin-video"
				style="height: ' . $this->height . 'px; width: ' . $this->width . 'px;"
				width="' . $this->width . '"
				height="' . $this->height . '"
				src="' . $url . '"
				frameborder="0"
				allowFullScreen></iframe>';

		if ($this->showContent) {
			$doc = new DOMDocument();
			$doc->encoding = 'UTF-8';
			$doc->recover = true;
			$doc->strictErrorChecking = false;

			if ($doc->loadHTML('<?xml encoding="utf-8" ?>' . $entry->content()))
			{
				$xpath = new DOMXPath($doc);

				/** @var DOMNodeList<DOMElement> $titles */
				$titles = $xpath->evaluate("//*[@class='enclosure-title']");
				/** @var DOMNodeList<DOMElement> $thumbnails */
				$thumbnails = $xpath->evaluate("//*[@class='enclosure-thumbnail']/@src");
				/** @var DOMNodeList<DOMElement> $descriptions */
				$descriptions = $xpath->evaluate("//*[@class='enclosure-description']");

				$content = '<div class="enclosure">';

				// We hide the title so it doesn't appear in the final article, which would be redundant with the RSS article title,
				// but we keep it in the content anyway, so RSS clients can extract it if needed.
				if ($titles->length > 0 && $titles[0] instanceof DOMNode) {
					$content .= '<p class="enclosure-title" hidden>' . $titles[0]->nodeValue . '</p>';
				}

				// We hide the thumbnail so it doesn't appear in the final article, which would be redundant with the YouTube player preview,
				// but we keep it in the content anyway, so RSS clients can extract it to display a preview where it wants (in article listing,
				// by example, like with Reeder).
				if ($thumbnails->length > 0 && $thumbnails[0] instanceof DOMNode) {
					$content .= '<p hidden><img class="enclosure-thumbnail" src="' . $thumbnails[0]->nodeValue . '" alt="" /></p>';
				}

				$content .= $iframe;

				if ($descriptions->length > 0 && $descriptions[0] instanceof DOMNode) {
					$content .= '<p class="enclosure-description">' . nl2br(htmlspecialchars($descriptions[0]->nodeValue ?? '', ENT_COMPAT, 'UTF-8'), use_xhtml: true) . '</p>';
				}

				$content .= "</div>\n";
			}
			else {
				$content = $iframe . $entry->content();
			}
		}
		else {
			$content = $iframe;
		}

		return $content;
	}

	/**
	 * This function is called by FreshRSS when the configuration page is loaded, and when configuration is saved.
	 *  - We save configuration in case of a post.
	 *  - We (re)load configuration in all case, so they are in-sync after a save and before a page load.
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_PDOConnectionException
	 * @throws Minz_ConfigurationNamespaceException
	 * @throws FreshRSS_UnsupportedImageFormat_Exception
	 * @throws Minz_PermissionDeniedException
	 */
	#[\Override]
	public function handleConfigureAction(): void
	{
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			// for handling requests from `custom_favicon_btn_url` hook
			$extAction = Minz_Request::paramStringNull('extAction');
			if ($extAction !== null) {
				$feedDAO = FreshRSS_Factory::createFeedDao();
				$feed = $feedDAO->searchById(Minz_Request::paramInt('id'));
				if ($feed === null || !$this->isYtFeed($feed->website())) {
					Minz_Error::error(404);
					return;
				}

				$this->setIconForFeed($feed, setValues: $extAction === 'update_icon');
				if ($extAction === 'query_icon_info') {
					header('Content-Type: application/json; charset=UTF-8');
					exit(json_encode([
						'extName' => $this->getName(),
						'iconUrl' => $feed->favicon(),
					]));
				}

				exit('OK');
			}

			// for handling configure page
			switch (Minz_Request::paramString('yt_action_btn')) {
				case 'ajaxGetYtFeeds':
					$this->ajaxGetYtFeeds();
					return;
				case 'ajaxFetchIcon':
					$this->ajaxFetchIcon();
					return;
				// non-ajax actions
				case 'iconFetchFinish': // called after final ajaxFetchIcon call
					Minz_Request::good(_t('ext.yt_videos.finished_fetching_icons'), ['c' => 'extension']);
					break;
				case 'resetIcons':
					$this->resetAllIcons();
					break;
			}
			FreshRSS_Context::userConf()->_attribute('yt_player_height', Minz_Request::paramInt('yt_height'));
			FreshRSS_Context::userConf()->_attribute('yt_player_width', Minz_Request::paramInt('yt_width'));
			FreshRSS_Context::userConf()->_attribute('yt_show_content', Minz_Request::paramBoolean('yt_show_content'));
			FreshRSS_Context::userConf()->_attribute('yt_download_channel_icons', Minz_Request::paramBoolean('yt_download_channel_icons'));
			FreshRSS_Context::userConf()->_attribute('yt_nocookie', Minz_Request::paramBoolean('yt_nocookie'));
			FreshRSS_Context::userConf()->save();
		}

		$this->loadConfigValues();
	}
}
