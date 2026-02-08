<?php

declare(strict_types=1);

include __DIR__ . "/request.php";

/**
 * Enumeration for HTTP request body types
 *
 * Defines the supported content types for webhook request bodies.
 */
enum BODY_TYPE: string {
	case JSON = "json";
	case FORM = "form";
}

/**
 * Enumeration for HTTP methods
 *
 * Defines the supported HTTP methods for webhook requests.
 */
enum HTTP_METHOD: string {
	case GET = "GET";
	case POST = "POST";
	case PUT = "PUT";
	case DELETE = "DELETE";
	case PATCH = "PATCH";
	case OPTIONS = "OPTIONS";
	case HEAD = "HEAD";
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
	public string $webhook_url = "http://<WRITE YOUR URL HERE>";

	/**
	 * Default HTTP headers for webhook requests
	 *
	 * @var string[]
	 */
	public array $webhook_headers = ["User-Agent: FreshRSS", "Content-Type: application/x-www-form-urlencoded"];

	/**
	 * Default webhook request body template
	 *
	 * Supports placeholders like __TITLE__, __FEED__, __URL__, etc.
	 *
	 * @var string
	 */
	public string $webhook_body = '{
	"title": "__TITLE__",
	"feed": "__FEED__",
	"url": "__URL__",
	"created": "__DATE_TIMESTAMP__"
}';

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
		$this->registerHook("entry_before_insert", [$this, "processArticle"]);
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
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			$conf = [
				"keywords" => array_filter(Minz_Request::paramTextToArray("keywords")),
				"search_in_title" => Minz_Request::paramString("search_in_title"),
				"search_in_feed" => Minz_Request::paramString("search_in_feed"),
				"search_in_authors" => Minz_Request::paramString("search_in_authors"),
				"search_in_content" => Minz_Request::paramString("search_in_content"),
				"mark_as_read" => Minz_Request::paramBoolean("mark_as_read"),
				"ignore_updated" => Minz_Request::paramBoolean("ignore_updated"),

				"webhook_url" => Minz_Request::paramString("webhook_url"),
				"webhook_method" => Minz_Request::paramString("webhook_method"),
				"webhook_headers" => array_filter(Minz_Request::paramTextToArray("webhook_headers")),
				"webhook_body" => html_entity_decode(Minz_Request::paramString("webhook_body")),
				"webhook_body_type" => Minz_Request::paramString("webhook_body_type"),
				"enable_logging" => Minz_Request::paramBoolean("enable_logging"),
			];
			$this->setSystemConfiguration($conf);
			$logsEnabled = $conf["enable_logging"];
			$this->logsEnabled = $conf["enable_logging"];

			logWarning($logsEnabled, "saved config: ✅ " . json_encode($conf));

			if (Minz_Request::paramString("test_request")) {
				try {
					sendReq(
						$conf["webhook_url"],
						$conf["webhook_method"],
						$conf["webhook_body_type"],
						$conf["webhook_body"],
						$conf["webhook_headers"],
						$conf["enable_logging"],
						"Test request from configuration"
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
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_PermissionDeniedException
	 *
	 * @return FreshRSS_Entry The processed entry (potentially marked as read)
	 */
	public function processArticle($entry): FreshRSS_Entry {
		if (!is_object($entry)) {
			return $entry;
		}

		if (FreshRSS_Context::userConf()->attributeBool('ignore_updated') && $entry->isUpdated()) {
			logWarning(true, "⚠️ ignore_updated: " . $entry->link() . " ♦♦ " . $entry->title());
			return $entry;
		}

		$searchInTitle = FreshRSS_Context::userConf()->attributeBool('search_in_title') ?? false;
		$searchInFeed = FreshRSS_Context::userConf()->attributeBool('search_in_feed') ?? false;
		$searchInAuthors = FreshRSS_Context::userConf()->attributeBool('search_in_authors') ?? false;
		$searchInContent = FreshRSS_Context::userConf()->attributeBool('search_in_content') ?? false;

		$patterns = FreshRSS_Context::userConf()->attributeArray('keywords') ?? [];
		$markAsRead = FreshRSS_Context::userConf()->attributeBool('mark_as_read') ?? false;
		$logsEnabled = FreshRSS_Context::userConf()->attributeBool('enable_logging') ?? false;
		$this->logsEnabled = $logsEnabled;

		// Validate patterns
		if (!is_array($patterns) || empty($patterns)) {
			logError($logsEnabled, "❗️ No keywords defined in Webhook extension settings.");
			return $entry;
		}

		$title = "❗️NOT INITIALIZED";
		$link = "❗️NOT INITIALIZED";
		$additionalLog = "";

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
	private function sendArticle(FreshRSS_Entry $entry, string $additionalLog = ""): void {
		try {
			$bodyStr = FreshRSS_Context::userConf()->attributeString('webhook_body');

			// Replace placeholders with actual values
			$replacements = [
				"__TITLE__" => $this->toSafeJsonStr($entry->title()),
				"__FEED__" => $this->toSafeJsonStr($entry->feed()->name()),
				"__URL__" => $this->toSafeJsonStr($entry->link()),
				"__CONTENT__" => $this->toSafeJsonStr($entry->content()),
				"__DATE__" => $this->toSafeJsonStr($entry->date()),
				"__DATE_TIMESTAMP__" => $this->toSafeJsonStr($entry->date(true)),
				"__AUTHORS__" => $this->toSafeJsonStr($entry->authors(true)),
				"__TAGS__" => $this->toSafeJsonStr($entry->tags(true)),
			];

			$bodyStr = str_replace(array_keys($replacements), array_values($replacements), $bodyStr);

			sendReq(
				FreshRSS_Context::userConf()->attributeString('webhook_url'),
				FreshRSS_Context::userConf()->attributeString('webhook_method'),
				FreshRSS_Context::userConf()->attributeString('webhook_body_type'),
				$bodyStr,
				FreshRSS_Context::userConf()->attributeArray('webhook_headers'),
				FreshRSS_Context::userConf()->attributeBool('enable_logging'),
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
		return str_replace('"', '', html_entity_decode((string)$str));
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

		try {
			// Try regex match first
			if (preg_match($pattern, $text) === 1) {
				return true;
			}

			// Fallback to string search (remove regex delimiters)
			$cleanPattern = trim($pattern, '/');
			return str_contains($text, $cleanPattern);

		} catch (Throwable $err) {
			logError($this->logsEnabled, "ERROR in isPatternFound: (pattern: {$pattern}) {$err->getMessage()}");
			return false;
		}
	}

	/**
	 * Get keywords configuration as formatted string
	 *
	 * Returns the configured keywords as a newline-separated string
	 * for display in the configuration form.
	 *
	 * @throws FreshRSS_Context_Exception
	 *
	 * @return string Keywords separated by newlines
	 */
	public function getKeywordsData(): string {
		$keywords = FreshRSS_Context::userConf()->attributeArray('keywords') ?? [];
		return implode(PHP_EOL, $keywords);
	}

	/**
	 * Get webhook headers configuration as formatted string
	 *
	 * Returns the configured HTTP headers as a newline-separated string
	 * for display in the configuration form.
	 *
	 * @throws FreshRSS_Context_Exception
	 *
	 * @return string HTTP headers separated by newlines
	 */
	public function getWebhookHeaders(): string {
		$headers = FreshRSS_Context::userConf()->attributeArray('webhook_headers');
		return implode(
			PHP_EOL,
			is_array($headers) ? $headers : ($this->webhook_headers ?? []),
		);
	}

	/**
	 * Get configured webhook URL
	 *
	 * Returns the configured webhook URL or the default if none is set.
	 *
	 * @throws FreshRSS_Context_Exception
	 *
	 * @return string The webhook URL
	 */
	public function getWebhookUrl(): string {
		return FreshRSS_Context::userConf()->attributeString('webhook_url') ?? $this->webhook_url;
	}

	/**
	 * Get configured webhook body template
	 *
	 * Returns the configured webhook body template or the default if none is set.
	 *
	 * @throws FreshRSS_Context_Exception
	 *
	 * @return string The webhook body template
	 */
	public function getWebhookBody(): string {
		$body = FreshRSS_Context::userConf()->attributeString('webhook_body');
		return ($body === null || $body === '') ? $this->webhook_body : $body;
	}

	/**
	 * Get configured webhook body type
	 *
	 * Returns the configured body type (json/form) or the default if none is set.
	 *
	 * @throws FreshRSS_Context_Exception
	 *
	 * @return string The webhook body type
	 */
	public function getWebhookBodyType(): string {
		return FreshRSS_Context::userConf()->attributeString('webhook_body_type') ?? $this->webhook_body_type->value;
	}
}

/**
 * Backward compatibility alias for logWarning function
 *
 * @deprecated Use logWarning() instead
 * @param bool $logEnabled Whether logging is enabled
 * @param mixed $data Data to log
 *
 * @throws Minz_PermissionDeniedException
 *
 * @return void
 */
function _LOG(bool $logEnabled, $data): void {
	logWarning($logEnabled, $data);
}

/**
 * Backward compatibility alias for logError function
 *
 * @deprecated Use logError() instead
 * @param bool $logEnabled Whether logging is enabled
 * @param mixed $data Data to log
 *
 * @throws Minz_PermissionDeniedException
 *
 * @return void
 */
function _LOG_ERR(bool $logEnabled, $data): void {
	logError($logEnabled, $data);
}
