<?php

declare(strict_types=1);

include __DIR__ . '/request.php';

/**
 * Enumeration for HTTP request body types
 *
 * Defines the supported content types for webhook request bodies.
 */
enum BODY_TYPE: string {
	case JSON = 'json';
	case FORM = 'form';
}

/**
 * Enumeration for HTTP methods
 *
 * Defines the supported HTTP methods for webhook requests.
 */
enum HTTP_METHOD: string {
	case GET = 'GET';
	case POST = 'POST';
	case PUT = 'PUT';
	case DELETE = 'DELETE';
	case PATCH = 'PATCH';
	case OPTIONS = 'OPTIONS';
	case HEAD = 'HEAD';
}

/**
 * FreshRSS Webhook Extension
 *
 * This extension allows sending webhook notifications when RSS entries match
 * specified keywords. It supports pattern matching in titles, feeds, authors,
 * and content, with configurable HTTP methods and request formats.
 *
 * @author Lukas Melega, Ryahn
 * @version 0.1.1
 * @since FreshRSS 1.20.0
 */
class WebhookExtension extends Minz_Extension {
	/**
	 * Whether logging is enabled for this extension
	 *
	 * @var bool
	 */
	public bool $logsEnabled = false;

	/**
	 * Default HTTP method for webhook requests
	 *
	 * @var HTTP_METHOD
	 */
	public HTTP_METHOD $webhook_method = HTTP_METHOD::POST;

	/**
	 * Default body type for webhook requests
	 *
	 * @var BODY_TYPE
	 */
	public BODY_TYPE $webhook_body_type = BODY_TYPE::JSON;

	/**
	 * Default webhook URL
	 *
	 * @var string
	 */
	public string $webhook_url = 'http://<WRITE YOUR URL HERE>';

	/**
	 * Default HTTP headers for webhook requests
	 *
	 * @var string[]
	 */
	public array $webhook_headers = ['User-Agent: FreshRSS', 'Content-Type: application/x-www-form-urlencoded'];

	/**
	 * Default webhook request body template
	 *
	 * Supports placeholders like __TITLE__, __FEED__, __URL__, etc.
	 *
	 * @var string
	 */
	public string $webhook_body = <<<'JSON'
	{
		"title": "__TITLE__",
		"feed": "__FEED__",
		"url": "__URL__",
		"created": "__DATE_TIMESTAMP__"
	}
	JSON;

	/**
	 * Initialize the extension
	 *
	 * Registers translation files and hooks into FreshRSS entry processing.
	 *
	 * @return void
	 */
	#[\Override]
	public function init(): void {
		$this->registerTranslates();
		$this->registerHook('entry_before_insert', [$this, 'processArticle']);
	}

	/**
	 * Handle configuration form submission
	 *
	 * Processes configuration form data, saves settings, and optionally
	 * sends a test webhook request.
	 *
	 * @return void
	 * @throws Minz_PermissionDeniedException
	 */
	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			$this->setUserConfigurationValue('keywords', array_filter(Minz_Request::paramTextToArray('keywords'), static fn(string $v): bool => $v !== ''));
			$this->setUserConfigurationValue('search_in_title', Minz_Request::paramBoolean('search_in_title'));
			$this->setUserConfigurationValue('search_in_feed', Minz_Request::paramBoolean('search_in_feed'));
			$this->setUserConfigurationValue('search_in_authors', Minz_Request::paramBoolean('search_in_authors'));
			$this->setUserConfigurationValue('search_in_content', Minz_Request::paramBoolean('search_in_content'));
			$this->setUserConfigurationValue('mark_as_read', Minz_Request::paramBoolean('mark_as_read'));
			$this->setUserConfigurationValue('ignore_updated', Minz_Request::paramBoolean('ignore_updated'));
			$this->setUserConfigurationValue('webhook_url', Minz_Request::paramString('webhook_url'));
			$this->setUserConfigurationValue('webhook_method', Minz_Request::paramString('webhook_method'));
			$this->setUserConfigurationValue('webhook_headers',
				array_filter(Minz_Request::paramTextToArray('webhook_headers'), static fn(string $v): bool => $v !== ''));
			$this->setUserConfigurationValue('webhook_body', html_entity_decode(Minz_Request::paramString('webhook_body')));
			$this->setUserConfigurationValue('webhook_body_type', Minz_Request::paramString('webhook_body_type'));
			$this->setUserConfigurationValue('enable_logging', Minz_Request::paramBoolean('enable_logging'));

			$logsEnabled = $this->getUserConfigurationBool('enable_logging') ?? false;
			$this->logsEnabled = $logsEnabled;

			logWarning($logsEnabled, 'saved config: ✅');

			if (Minz_Request::paramString('test_request') !== '') {
				try {
					sendReq(
						$this->getUserConfigurationString('webhook_url') ?? '',
						$this->getUserConfigurationString('webhook_method') ?? '',
						$this->getUserConfigurationString('webhook_body_type') ?? '',
						$this->getUserConfigurationString('webhook_body') ?? '',
						array_values(array_filter($this->getUserConfigurationArray('webhook_headers') ?? [], 'is_string')),
						$logsEnabled,
						'Test request from configuration'
					);
				} catch (Throwable $err) {
					logError($logsEnabled, "Test request failed: {$err->getMessage()}");
				}
			}
		}
	}

	/**
	 * Process article and send webhook if patterns match
	 *
	 * Analyzes RSS entries against configured keyword patterns and sends
	 * webhook notifications for matching entries. Supports pattern matching
	 * in titles, feeds, authors, and content.
	 *
	 * @param FreshRSS_Entry $entry The RSS entry to process
	 *
	 * @throws Minz_PermissionDeniedException
	 *
	 * @return FreshRSS_Entry The processed entry (potentially marked as read)
	 */
	public function processArticle($entry): FreshRSS_Entry {
		if (!is_object($entry)) {
			return $entry;
		}

		if ($this->getUserConfigurationBool('ignore_updated') && $entry->isUpdated()) {
			logWarning(true, '⚠️ ignore_updated: ' . $entry->link() . ' ♦♦ ' . $entry->title());
			return $entry;
		}

		$searchInTitle = $this->getUserConfigurationBool('search_in_title') ?? false;
		$searchInFeed = $this->getUserConfigurationBool('search_in_feed') ?? false;
		$searchInAuthors = $this->getUserConfigurationBool('search_in_authors') ?? false;
		$searchInContent = $this->getUserConfigurationBool('search_in_content') ?? false;

		$patterns = array_values(array_filter($this->getUserConfigurationArray('keywords') ?? [], 'is_string'));
		$markAsRead = $this->getUserConfigurationBool('mark_as_read') ?? false;
		$logsEnabled = $this->getUserConfigurationBool('enable_logging') ?? false;
		$this->logsEnabled = $logsEnabled;

		// Validate patterns
		if (empty($patterns)) {
			logError($logsEnabled, '❗️ No keywords defined in Webhook extension settings.');
			return $entry;
		}

		$title = '❗️ NOT INITIALIZED';
		$link = '❗️ NOT INITIALIZED';
		$additionalLog = '';

		try {
			$title = $entry->title();
			$link = $entry->link();

			foreach ($patterns as $pattern) {
				$matchFound = false;

				if ($searchInTitle && $this->isPatternFound("/{$pattern}/", $title)) {
					logWarning($logsEnabled, "matched item by title ✔️ \"{$title}\" ❖ link: {$link}");
					$additionalLog = "✔️ matched item with pattern: /{$pattern}/ ❖ title \"{$title}\" ❖ link: {$link}";
					$matchFound = true;
				}

				if (!$matchFound && $searchInFeed && is_object($entry->feed()) && $this->isPatternFound("/{$pattern}/", $entry->feed()->name())) {
					logWarning($logsEnabled, "matched item with pattern: /{$pattern}/ ❖ feed \"{$entry->feed()->name()}\", (title: \"{$title}\") ❖ link: {$link}");
					$additionalLog = "✔️ matched item with pattern: /{$pattern}/ ❖ feed \"{$entry->feed()->name()}\", (title: \"{$title}\") ❖ link: {$link}";
					$matchFound = true;
				}

				if (!$matchFound && $searchInAuthors && $this->isPatternFound("/{$pattern}/", $entry->authors(true))) {
					logWarning($logsEnabled, "✔️ matched item with pattern: /{$pattern}/ ❖ authors \"{$entry->authors(true)}\", (title: {$title}) ❖ link: {$link}");
					$additionalLog = "✔️ matched item with pattern: /{$pattern}/ ❖ authors \"{$entry->authors(true)}\", (title: {$title}) ❖ link: {$link}";
					$matchFound = true;
				}

				if (!$matchFound && $searchInContent && $this->isPatternFound("/{$pattern}/", $entry->content())) {
					logWarning($logsEnabled, "✔️ matched item with pattern: /{$pattern}/ ❖ content (title: \"{$title}\") ❖ link: {$link}");
					$additionalLog = "✔️ matched item with pattern: /{$pattern}/ ❖ content (title: \"{$title}\") ❖ link: {$link}";
					$matchFound = true;
				}

				if ($matchFound) {
					break;
				}
			}

			if ($markAsRead) {
				$entry->_isRead($markAsRead);
			}

			// Only send webhook if a pattern was matched
			if (!empty($additionalLog)) {
				$this->sendArticle($entry, $additionalLog);
			}
		} catch (Throwable $err) {
			logError($logsEnabled, "Error during processing article ({$link} ❖ \"{$title}\") ERROR: {$err->getMessage()}");
		}

		return $entry;
	}

	/**
	 * Send article data via webhook
	 *
	 * Prepares and sends webhook notification with article data.
	 * Replaces template placeholders with actual entry values.
	 *
	 * @param FreshRSS_Entry $entry The RSS entry to send
	 * @param string $additionalLog Additional context for logging
	 *
	 * @throws Minz_PermissionDeniedException
	 *
	 * @return void
	 */
	private function sendArticle(FreshRSS_Entry $entry, string $additionalLog = ''): void {
		try {
			$bodyStr = $this->getUserConfigurationString('webhook_body') ?? '';

			// Replace placeholders with actual values
			$replacements = [
				'__TITLE__' => $this->toSafeJsonStr($entry->title()),
				'__FEED__' => $this->toSafeJsonStr($entry->feed()?->name() ?? ''),
				'__URL__' => $this->toSafeJsonStr($entry->link()),
				'__CONTENT__' => $this->toSafeJsonStr($entry->content()),
				'__DATE__' => $this->toSafeJsonStr($entry->date()),
				'__DATE_TIMESTAMP__' => $this->toSafeJsonStr($entry->date(true)),
				'__AUTHORS__' => $this->toSafeJsonStr($entry->authors(true)),
				'__TAGS__' => $this->toSafeJsonStr($entry->tags(true)),
			];

			$bodyStr = str_replace(array_keys($replacements), array_values($replacements), $bodyStr);

			sendReq(
				$this->getUserConfigurationString('webhook_url') ?? '',
				$this->getUserConfigurationString('webhook_method') ?? '',
				$this->getUserConfigurationString('webhook_body_type') ?? '',
				$bodyStr,
				array_values(array_filter($this->getUserConfigurationArray('webhook_headers') ?? [], 'is_string')),
				$this->getUserConfigurationBool('enable_logging') ?? false,
				$additionalLog,
			);
		} catch (Throwable $err) {
			logError($this->logsEnabled, "ERROR in sendArticle: {$err->getMessage()}");
		}
	}

	/**
	 * Convert string/int to safe JSON string
	 *
	 * Sanitizes input values for safe inclusion in JSON payloads
	 * by removing quotes and decoding HTML entities.
	 *
	 * @param string|int $str Input value to sanitize
	 *
	 * @return string Sanitized string safe for JSON inclusion
	 */
	private function toSafeJsonStr(string|int $str): string {
		if (is_numeric($str)) {
			return (string)$str;
		}

		// Remove quotes and decode HTML entities
		return str_replace('"', '', html_entity_decode($str));
	}

	/**
	 * Check if pattern is found in text
	 *
	 * Attempts regex matching first, then falls back to simple string search.
	 * Handles regex errors gracefully and logs issues.
	 *
	 * @param string $pattern Search pattern (may include regex delimiters)
	 * @param string $text Text to search in
	 *
	 * @return bool True if pattern is found, false otherwise
	 */
	private function isPatternFound(string $pattern, string $text): bool {
		if (empty($text) || empty($pattern)) {
			return false;
		}

		// Try regex match first
		if (preg_match($pattern, $text) === 1) {
			return true;
		}

		// Fallback to string search (remove regex delimiters)
		$cleanPattern = trim($pattern, '/');
		return str_contains($text, $cleanPattern);
	}

	/**
	 * Get keywords configuration as formatted string
	 *
	 * Returns the configured keywords as a newline-separated string
	 * for display in the configuration form.
	 *
	 * @return string Keywords separated by newlines
	 */
	public function getKeywordsData(): string {
		$keywords = array_values(array_filter($this->getUserConfigurationArray('keywords') ?? [], 'is_string'));
		return implode(PHP_EOL, $keywords);
	}

	/**
	 * Get webhook headers configuration as formatted string
	 *
	 * Returns the configured HTTP headers as a newline-separated string
	 * for display in the configuration form.
	 *
	 * @return string HTTP headers separated by newlines
	 */
	public function getWebhookHeaders(): string {
		$headers = array_values(array_filter($this->getUserConfigurationArray('webhook_headers') ?? $this->webhook_headers, 'is_string'));
		return implode(PHP_EOL, $headers);
	}

	/**
	 * Get configured webhook URL
	 *
	 * Returns the configured webhook URL or the default if none is set.
	 *
	 * @return string The webhook URL
	 */
	public function getWebhookUrl(): string {
		return $this->getUserConfigurationString('webhook_url') ?? $this->webhook_url;
	}

	/**
	 * Get configured webhook body template
	 *
	 * Returns the configured webhook body template or the default if none is set.
	 *
	 * @return string The webhook body template
	 */
	public function getWebhookBody(): string {
		$body = $this->getUserConfigurationString('webhook_body');
		return ($body === null || $body === '') ? $this->webhook_body : $body;
	}

	/**
	 * Get configured webhook body type
	 *
	 * Returns the configured body type (json/form) or the default if none is set.
	 *
	 * @return string The webhook body type
	 */
	public function getWebhookBodyType(): string {
		return $this->getUserConfigurationString('webhook_body_type') ?? $this->webhook_body_type->value;
	}
}
