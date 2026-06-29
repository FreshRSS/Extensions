<?php

final class GridViewExtension extends Minz_Extension {

	/** @var int Default number of columns */
	private const DEFAULT_COLUMNS = 3;

	/** @var int Minimum columns allowed */
	private const MIN_COLUMNS = 2;

	/** @var int Maximum columns allowed */
	private const MAX_COLUMNS = 4;

	/** @var string Default sort field: publication date */
	private const DEFAULT_SORT = 'date';

	/** @var string Default sort direction: newest first */
	private const DEFAULT_SORT_ORDER = 'DESC';

	/**
	 * @throws Minz_PermissionDeniedException
	 */
	#[\Override]
	public function init(): void {
		parent::init();

		// Load CSS and JS
		Minz_View::appendStyle($this->getFileUrl('grid.css'));
		Minz_View::appendScript($this->getFileUrl('grid.js'));

		// Register translations
		$this->registerTranslates();

		// Set default sorting to Publication Date, newest first (if enabled)
		if ($this->isSortByPublicationDateEnabled()) {
			$this->applyDefaultSort();
		}

		// Inject column count and settings as JS variable
		$this->registerHook('js_vars', [$this, 'injectJsVars']);

		// Inject thumbnail URL into content so it's available in all view modes
		// (Reading View doesn't render the .item.thumbnail element)
		$this->registerHook('entry_before_display', [$this, 'injectThumbnailMarker']);

		// Fetch OG image for entries that have no thumbnail (user-configurable)
		if ($this->isOgImageFetchEnabled()) {
			$this->registerHook('entry_before_insert', [$this, 'fetchOgImage']);
			// Instead of blocking page render to fetch OG images, inject a
			// marker that the JS picks up and fetches asynchronously.
			$this->registerHook('entry_before_display', [$this, 'markEntryForOgFetch']);
		}
	}

	/**
	 * Read a single user configuration value.
	 *
	 * Uses the array-based configuration API for compatibility with
	 * FreshRSS 1.28.x and earlier, which lack the typed getters.
	 *
	 * @return mixed
	 */
	private function cfgValue(string $key): mixed {
		/** @phpstan-ignore method.deprecated */
		return $this->getUserConfigurationValue($key);
	}

	/**
	 * Read the full user configuration array.
	 *
	 * @return array<string, mixed>
	 */
	private function cfgAll(): array {
		/** @phpstan-ignore method.deprecated */
		return $this->getUserConfiguration();
	}

	/**
	 * Persist the full user configuration array.
	 *
	 * @param array<string, mixed> $config
	 */
	private function cfgSave(array $config): void {
		/** @phpstan-ignore method.deprecated */
		$this->setUserConfiguration($config);
	}

	/**
	 * Apply default sort order: Publication Date, newest first (9->1).
	 * Only sets it once; the user's explicit choice via the FreshRSS UI
	 * is preserved by checking the extension's own 'sort_applied' flag.
	 *
	 * @throws Minz_PermissionDeniedException
	 */
	private function applyDefaultSort(): void {
		if ($this->cfgValue('sort_applied') === true) {
			return;
		}

		try {
			$userConf = FreshRSS_Context::userConf();
			$userConf->_attribute('sort', self::DEFAULT_SORT);
			$userConf->_attribute('sort_order', self::DEFAULT_SORT_ORDER);
			$userConf->save();

			// Mark as applied so we don't override the user's future changes
			$config = $this->cfgAll();
			$config['sort_applied'] = true;
			$this->cfgSave($config);
		} catch (\Throwable $e) {
			Minz_Log::warning('GridView: Failed to set default sort order: ' . $e->getMessage());
		}
	}

	/**
	 * Inject JavaScript variables for grid configuration
	 * @param array<string, mixed> $vars
	 * @return array<string, mixed>
	 */
	public function injectJsVars(array $vars): array {
		$columnsValue = $this->cfgValue('columns');
		$columns = is_numeric($columnsValue) ? (int) $columnsValue : self::DEFAULT_COLUMNS;
		if ($columns < self::MIN_COLUMNS || $columns > self::MAX_COLUMNS) {
			$columns = self::DEFAULT_COLUMNS;
		}
		$vars['gridview'] = [
			'columns' => $columns,
			'placeholderUrl' => $this->getFileUrl('placeholder.jpg'),
			'ogFetchUrl' => './?c=extension&a=configure&e=' . urlencode($this->getName()),
			'showMobileMenuButton' => $this->isMobileMenuButtonEnabled(),
			'stickyNavEnabled' => $this->isStickyNavEnabled(),
			'shareMenuHtml' => $this->buildShareMenuHtml(),
		];
		return $vars;
	}

	/**
	 * Build the share menu HTML template with placeholders.
	 * Reader view (article.phtml) doesn't render entry_share_menu, so
	 * we generate it here and pass it to JS for use in grid cards.
	 * Placeholders: --entryId--, --link--, --titleText--
	 * @return string
	 */
	private function buildShareMenuHtml(): string {
		if (!FreshRSS_Auth::hasAccess()) {
			return '';
		}

		try {
			$sharing = FreshRSS_Context::userConf()->sharing;
			if (empty($sharing) || !is_array($sharing)) {
				return '';
			}
		} catch (\Throwable $e) {
			return '';
		}

		$html = '<ul class="dropdown-menu">';
		$html .= '<li class="dropdown-header">' . _t('index.share') . '</li>';

		foreach ($sharing as $shareOptions) {
			$share = FreshRSS_Share::get($shareOptions['type'] ?? '');
			if ($share === null) {
				continue;
			}
			$shareOptions['id'] = '--entryId--';
			$shareOptions['link'] = '--link--';
			$shareOptions['title'] = '--titleText--';
			$share->update($shareOptions);

			if ('GET' === $share->method()) {
				$html .= '<li class="item share">';
				$html .= '<a target="_blank" rel="noreferrer" href="'
					. htmlspecialchars($share->url(), ENT_QUOTES, 'UTF-8')
					. '" data-type="' . htmlspecialchars($share->type(), ENT_QUOTES, 'UTF-8')
					. '">' . htmlspecialchars($share->name() ?? '', ENT_QUOTES, 'UTF-8') . '</a>';
				$html .= '</li>';
			} else {
				$html .= '<li class="item share">';
				$html .= '<a href="POST">' . htmlspecialchars($share->name() ?? '', ENT_QUOTES, 'UTF-8') . '</a>';
				$html .= '<form method="POST" action="'
					. htmlspecialchars($share->url(), ENT_QUOTES, 'UTF-8')
					. '" disabled="disabled"><input type="hidden" value="--link--" name="'
					. htmlspecialchars($share->field() ?? '', ENT_QUOTES, 'UTF-8') . '"/></form>';
				$html .= '</li>';
			}
		}

		$html .= '</ul>';
		return $html;
	}

	/**
	 * Handle configuration form submission
	 */
	#[\Override]
	public function handleConfigureAction(): void {
		// Handle AJAX OG image fetch request (returns JSON and exits)
		if (Minz_Request::paramString('ajax') === 'og') {
			$this->ajaxFetchOgImage();
			return;
		}

		parent::init();

		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			$columns = Minz_Request::paramInt('columns');

			// Validate column count
			if ($columns < self::MIN_COLUMNS) {
				$columns = self::MIN_COLUMNS;
			} elseif ($columns > self::MAX_COLUMNS) {
				$columns = self::MAX_COLUMNS;
			}

			$fetchOgImage = Minz_Request::paramBoolean('fetch_og_image');
			$sortByDate = Minz_Request::paramBoolean('sort_by_date');
			$mobileMenuButton = Minz_Request::paramBoolean('mobile_menu_button');
			$stickyNav = Minz_Request::paramBoolean('sticky_nav');

			// Reset sort_applied flag when sort_by_date is toggled so it
			// re-applies (or stops applying) on the next page load
			$sortApplied = $this->cfgValue('sort_applied') === true;

			// setUserConfiguration expects an array of all config values
			$this->cfgSave([
				'columns' => $columns,
				'fetch_og_image' => $fetchOgImage,
				'sort_by_date' => $sortByDate,
				'sort_applied' => $sortByDate ? $sortApplied : false,
				'mobile_menu_button' => $mobileMenuButton,
				'sticky_nav' => $stickyNav,
			]);
		}
	}

	/**
	 * Get current column configuration
	 * @return int
	 */
	public function getColumns(): int {
		$value = $this->cfgValue('columns');
		$columns = is_numeric($value) ? (int) $value : self::DEFAULT_COLUMNS;
		if ($columns < self::MIN_COLUMNS || $columns > self::MAX_COLUMNS) {
			return self::DEFAULT_COLUMNS;
		}
		return $columns;
	}

	/**
	 * Whether sort by publication date is enabled in user configuration.
	 * @return bool
	 */
	public function isSortByPublicationDateEnabled(): bool {
		$value = $this->cfgValue('sort_by_date');
		return $value === true || $value === 1 || $value === '1';
	}

	/**
	 * Whether the mobile menu button is enabled in user configuration.
	 * @return bool
	 */
	public function isMobileMenuButtonEnabled(): bool {
		$value = $this->cfgValue('mobile_menu_button');
		return $value === true || $value === 1 || $value === '1';
	}

	/**
	 * Whether the sticky navigation bar is enabled in user configuration.
	 * @return bool
	 */
	public function isStickyNavEnabled(): bool {
		$value = $this->cfgValue('sticky_nav');
		return $value === true || $value === 1 || $value === '1';
	}

	/**
	 * Whether OG image fetching is enabled in user configuration.
	 * @return bool
	 */
	public function isOgImageFetchEnabled(): bool {
		// Default to false (opt-in) so existing users aren't surprised by slower page loads
		$value = $this->cfgValue('fetch_og_image');
		return $value === true || $value === 1 || $value === '1';
	}

	/**
	 * Inject a hidden thumbnail marker into the entry content.
	 * Reading View doesn't render the .item.thumbnail element, so the
	 * JS grid code can't find the thumbnail. This adds a hidden span
	 * with the URL so getThumbnail() can pick it up in any view.
	 */
	public function injectThumbnailMarker(FreshRSS_Entry $entry): FreshRSS_Entry {
		$thumbnail = $entry->thumbnail(true);
		if (empty($thumbnail['url'])) {
			return $entry;
		}

		$url = htmlspecialchars($thumbnail['url'], ENT_QUOTES, 'UTF-8');
		$marker = '<span class="gridview-thumbnail-marker" data-thumbnail="' . $url . '" style="display:none"></span>';
		$entry->_content($marker . $entry->content(false));

		return $entry;
	}

	/**
	 * Fetch Open Graph image for entries that have no thumbnail.
	 * Called when a new entry is being inserted into the database.
	 *
	 * @throws Minz_PermissionDeniedException
	 */
	public function fetchOgImage(FreshRSS_Entry $entry): FreshRSS_Entry {
		return $this->ensureThumbnail($entry);
	}

	/**
	 * Mark entries that have no thumbnail so the JS can fetch OG images
	 * asynchronously. This replaces the synchronous fetchOgImageOnDisplay
	 * to avoid blocking page rendering.
	 */
	public function markEntryForOgFetch(FreshRSS_Entry $entry): FreshRSS_Entry {
		$thumbnail = $entry->thumbnail(true);
		if (!empty($thumbnail['url']) || $entry->attributeBoolean('og_image_checked')) {
			return $entry;
		}

		// Don't mark if the content already has images (the JS card
		// builder will extract them directly)
		if (preg_match('/<img\s[^>]*src\s*=\s*["\']https?:\/\//i', $entry->content(false))) {
			return $entry;
		}

		$link = $entry->link();
		if (empty($link) || !str_starts_with($link, 'http')) {
			return $entry;
		}

		$escapedLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
		$escapedId = htmlspecialchars($entry->id(), ENT_QUOTES, 'UTF-8');

		$marker = '<span class="gridview-og-fetch" '
			. 'data-og-url="' . $escapedLink . '" '
			. 'data-og-entry-id="' . $escapedId . '" '
			. 'style="display:none"></span>';
		$entry->_content($entry->content(false) . $marker);

		return $entry;
	}

	/**
	 * AJAX endpoint: fetch the OG image for a given URL and return JSON.
	 * Called via ?c=extension&a=configure&e=GridView&ajax=og&url=...
	 */
	private function ajaxFetchOgImage(): void {
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: private, max-age=86400');

		if (!FreshRSS_Auth::hasAccess()) {
			http_response_code(403);
			echo json_encode(['error' => 'Unauthorized']);
			exit;
		}

		$url = Minz_Request::paramString('url');
		if (empty($url) || (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://'))) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid URL']);
			exit;
		}

		$ogImage = $this->extractOgImage($url);

		echo json_encode(['image' => $ogImage]);
		exit;
	}

	/**
	 * Ensure the entry has a thumbnail; if not, try to fetch the OG image.
	 *
	 * @param FreshRSS_Entry $entry
	 * @param bool $persist Whether to persist changes to the database (for display-time fetches)
	 * @return FreshRSS_Entry
	 * @throws Minz_PermissionDeniedException
	 */
	private function ensureThumbnail(FreshRSS_Entry $entry, bool $persist = false): FreshRSS_Entry {
		// Skip if entry already has a thumbnail
		$thumbnail = $entry->thumbnail(true);
		if (!empty($thumbnail['url'])) {
			return $entry;
		}

		// Don't re-attempt if we already checked this entry
		if ($entry->attributeBoolean('og_image_checked')) {
			return $entry;
		}

		// Skip if content already has images
		if (preg_match('/<img\s[^>]*src\s*=\s*["\']https?:\/\//i', $entry->content(false))) {
			return $entry;
		}

		$link = htmlspecialchars_decode($entry->link(), ENT_QUOTES);
		if (empty($link) || !str_starts_with($link, 'http')) {
			return $entry;
		}

		$ogImage = $this->extractOgImage($link);
		if ($ogImage !== null) {
			$entry->_attribute('thumbnail', ['url' => $ogImage]);
		} else {
			$entry->_attribute('og_image_checked', true);
		}

		// Persist to database when called at display time so the image
		// is available on subsequent page loads without re-fetching
		if ($persist) {
			try {
				$entryDAO = FreshRSS_Factory::createEntryDao();
				$entryDAO->updateEntry($entry->toArray());
			} catch (\Throwable $e) {
				Minz_Log::warning('GridView: Failed to persist OG image: ' . $e->getMessage());
			}
		}

		return $entry;
	}

	/**
	 * Fetch a URL and extract the og:image meta tag.
	 *
	 * @param string $url The article URL to fetch
	 * @return string|null The og:image URL or null if not found
	 */
	private function extractOgImage(string $url): ?string {
		$ctx = stream_context_create([
			'http' => [
				'timeout' => 5,
				'method' => 'GET',
				'header' => "User-Agent: FreshRSS/GridView\r\n",
				'follow_location' => 1,
				'max_redirects' => 3,
				'ignore_errors' => true,
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true,
			],
		]);

		// Only fetch the first 50 KB — og:image is in <head>
		$html = @file_get_contents($url, false, $ctx, 0, 50000);
		if ($html === false || $html === '') {
			return null;
		}

		// Try og:image first, then twitter:image (both attribute orderings)
		if (preg_match('/<meta\s[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $matches)) {
			return $this->normalizeImageUrl($matches[1], $url);
		}
		if (preg_match('/<meta\s[^>]*content=["\']([^"\']+)["\'][^>]*property=["\']og:image["\']/i', $html, $matches)) {
			return $this->normalizeImageUrl($matches[1], $url);
		}
		if (preg_match('/<meta\s[^>]*name=["\']twitter:image["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $matches)) {
			return $this->normalizeImageUrl($matches[1], $url);
		}
		if (preg_match('/<meta\s[^>]*content=["\']([^"\']+)["\'][^>]*name=["\']twitter:image["\']/i', $html, $matches)) {
			return $this->normalizeImageUrl($matches[1], $url);
		}

		return null;
	}

	/**
	 * Normalize a potentially relative image URL to absolute.
	 *
	 * @param string $imageUrl The image URL (may be relative)
	 * @param string $pageUrl  The page URL for resolving relative paths
	 * @return string|null Absolute URL or null if invalid
	 */
	private function normalizeImageUrl(string $imageUrl, string $pageUrl): ?string {
		$imageUrl = html_entity_decode($imageUrl, ENT_QUOTES, 'UTF-8');
		$imageUrl = trim($imageUrl);

		if (empty($imageUrl)) {
			return null;
		}

		// Already absolute
		if (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://')) {
			return $imageUrl;
		}

		// Protocol-relative
		if (str_starts_with($imageUrl, '//')) {
			$scheme = parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https';
			return $scheme . ':' . $imageUrl;
		}

		// Relative — resolve against page URL
		$parsed = parse_url($pageUrl);
		if ($parsed === false || empty($parsed['host'])) {
			return null;
		}

		$base = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'];
		if (str_starts_with($imageUrl, '/')) {
			return $base . $imageUrl;
		}

		$path = $parsed['path'] ?? '/';
		$dir = substr($path, 0, (int)strrpos($path, '/') + 1);
		return $base . $dir . $imageUrl;
	}
}
