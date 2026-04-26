<?php

declare(strict_types=1);

/**
 * FreshRSS Webhook Extension
 *
 * This extension allows sending webhook notifications when RSS entries match
 * configured search filters. It supports FreshRSS native search filter syntax
 * with configurable HTTP methods and request formats.
 *
 * @author Lukas Melega, Ryahn
 */
class WebhookExtension extends Minz_Extension {
	/**
	 * Default HTTP method for webhook requests
	 */
	public string $webhook_method = 'POST';

	/**
	 * Default body type for webhook requests
	 */
	public string $webhook_body_type = 'custom';

	/**
	 * Default webhook URL
	 *
	 * @var string
	 */
	public string $webhook_url = 'https://example.net/webhook';

	/**
	 * Default HTTP headers for webhook requests
	 *
	 * @var list<string>
	 */
	public array $webhook_headers = [];

	/**
	 * Default webhook request body template
	 *
	 * Supports placeholders like {title}, {url}, {feed_name}, etc.
	 *
	 * @var array<string,string>
	 */
	public array $webhook_body = [
		'title' => '{title}',
		'feed' => '{feed_name}',
		'url' => '{url}',
		'created' => '{date_published}',
	];

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
	 * @throws Minz_ConfigurationException
	 * @throws Minz_PermissionDeniedException
	 */
	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			$this->setUserConfigurationValue('search_filter', trim(Minz_Request::paramString('search_filter', plaintext: true)));
			$this->setUserConfigurationValue('ignore_updated', Minz_Request::paramBoolean('ignore_updated'));
			$this->setUserConfigurationValue('webhook_url', trim(Minz_Request::paramString('webhook_url', plaintext: true)));
			$this->setUserConfigurationValue('webhook_method', trim(Minz_Request::paramString('webhook_method', plaintext: true)));
			$this->setUserConfigurationValue('webhook_headers',
				array_filter(Minz_Request::paramTextToArray('webhook_headers'), static fn(string $v): bool => $v !== ''));
			$webhookBodyJson = trim(Minz_Request::paramString('webhook_body', plaintext: true));
			$webhookBodyArray = $webhookBodyJson === '' ? [] : json_decode($webhookBodyJson, true, 256, JSON_INVALID_UTF8_SUBSTITUTE);
			$this->setUserConfigurationValue('webhook_body', is_array($webhookBodyArray) ? $webhookBodyArray : []);
			$this->setUserConfigurationValue('webhook_body_type', trim(Minz_Request::paramString('webhook_body_type', plaintext: true)));
			$this->setUserConfigurationValue('webhook_content_type', trim(Minz_Request::paramString('webhook_content_type', plaintext: true)));

			if (Minz_Request::paramString('test_request', plaintext: true) !== '') {
				try {
					$this->sendRequest(
						$this->getUserConfigurationString('webhook_url') ?? '',
						$this->getUserConfigurationString('webhook_method') ?? '',
						$this->getUserConfigurationString('webhook_content_type') ?? 'json',
						$this->getUserConfigurationArray('webhook_body') ?? [],
						array_values(array_filter($this->getUserConfigurationArray('webhook_headers') ?? [], 'is_string')),
					);
				} catch (Throwable $err) {
					Minz_Log::warning('[Webhook] Test request failed: ' . $err->getMessage());
				}
			}
		}
	}

	/**
	 * Process article and send webhook if search filter matches
	 *
	 * Evaluates RSS entries against configured search filters and sends
	 * webhook notifications for matching entries.
	 *
	 * @param FreshRSS_Entry $entry The RSS entry to process
	 * @throws Minz_PermissionDeniedException
	 * @return FreshRSS_Entry The processed entry (potentially marked as read)
	 */
	public function processArticle(FreshRSS_Entry $entry): FreshRSS_Entry {
		if ($this->getUserConfigurationBool('ignore_updated') && $entry->isUpdated()) {
			return $entry;
		}

		try {
			if (!$this->entryMatchesSearchFilter($entry)) {
				return $entry;
			}
			$this->sendArticle($entry);
		} catch (Throwable $err) {
			Minz_Log::warning('[Webhook] Error processing article: ' . $err->getMessage());
		}

		return $entry;
	}

	/**
	 * Check if entry matches the configured search filter
	 *
	 * Evaluates the entry against each line of the search filter.
	 * Lines act as OR conditions — the first match returns true.
	 * An empty filter matches all entries.
	 *
	 * @param FreshRSS_Entry $entry The RSS entry to check
	 * @return bool True if the entry matches any filter line, or if no filter is configured
	 */
	private function entryMatchesSearchFilter(FreshRSS_Entry $entry): bool {
		$searchFilter = $this->getUserConfigurationString('search_filter') ?? '';
		if ($searchFilter === '') {
			return true;
		}

		$lines = array_filter(array_map('trim', explode("\n", $searchFilter)), static fn(string $line): bool => $line !== '');
		foreach ($lines as $line) {
			$booleanSearch = new FreshRSS_BooleanSearch($line);
			if ($entry->matches($booleanSearch)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively replace placeholders in an array structure
	 *
	 * Walks the array and applies placeholder replacement to string leaf values.
	 * If a replacement value is null and the placeholder is the entire string value,
	 * the value becomes null (preserving null semantics in JSON output).
	 *
	 * @param array<array-key,mixed> $data The array to process
	 * @param array<string,string|null> $replacements Placeholder => replacement value map
	 * @return array<array-key,mixed> The array with placeholders replaced
	 */
	private function replacePlaceholdersRecursive(array $data, array $replacements): array {
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$data[$key] = $this->replacePlaceholdersRecursive($value, $replacements);
			} elseif (is_string($value)) {
				// If the entire value is a single placeholder that maps to null, keep null
				if (isset($replacements[$value]) || (array_key_exists($value, $replacements) && $replacements[$value] === null)) {
					$data[$key] = $replacements[$value];
				} else {
					$data[$key] = strtr($value, array_filter($replacements, static fn($v): bool => $v !== null));
				}
			}
		}
		return $data;
	}

	/**
	 * Send article data via webhook
	 *
	 * Prepares and sends webhook notification with article data.
	 * Supports custom body templates, GReader API JSON, and RSS XML formats.
	 *
	 * @param FreshRSS_Entry $entry The RSS entry to send
	 * @throws \RuntimeException
	 * @throws Minz_ConfigurationException
	 */
	private function sendArticle(FreshRSS_Entry $entry): void {
		$bodyType = $this->getUserConfigurationString('webhook_body_type') ?? '';

		switch ($bodyType) {
			case 'greader':
				$body = $entry->toGReader();
				$contentType = 'json';
				break;
			case 'rss':
				$body = $this->renderEntryAsRss($entry);
				$contentType = 'rss';
				break;
			default:
				$contentType = $this->getUserConfigurationString('webhook_content_type') ?? 'json';
				$body = $this->getUserConfigurationArray('webhook_body') ?? $this->webhook_body;

				// Replace placeholders with actual values
				$replacements = [
					'{title}' => htmlspecialchars_decode($entry->title(), ENT_QUOTES),
					'{feed_name}' => htmlspecialchars_decode($entry->feed()?->name() ?? '', ENT_QUOTES),
					'{feed_url}' => htmlspecialchars_decode($entry->feed()?->url() ?? '', ENT_QUOTES),
					'{url}' => htmlspecialchars_decode($entry->link(), ENT_QUOTES),
					'{content}' => htmlspecialchars_decode($entry->content(), ENT_QUOTES),
					'{date_published}' => timestampToMachineDate($entry->date(raw: true)),
					'{date_received}' => timestampToMachineDate($entry->dateAdded(raw: true)),
					'{date_modified}' => $entry->lastModified() === null ? null : timestampToMachineDate($entry->lastModified()),
					'{date_user_modified}' => $entry->lastUserModified() === null ? null : timestampToMachineDate($entry->lastUserModified()),
					'{author}' => htmlspecialchars_decode($entry->authors(true), ENT_QUOTES),
					'{tags}' => htmlspecialchars_decode($entry->tags(true), ENT_QUOTES),
				];

				$body = $this->replacePlaceholdersRecursive($body, $replacements);
				break;
		}

		$this->sendRequest(
			$this->getUserConfigurationString('webhook_url') ?? '',
			$this->getUserConfigurationString('webhook_method') ?? '',
			$contentType,
			$body,
			array_values(array_filter($this->getUserConfigurationArray('webhook_headers') ?? [], 'is_string')),
		);
	}

	/**
	 * Render an entry as RSS XML using the FreshRSS RSS view template
	 *
	 * @param FreshRSS_Entry $entry The RSS entry to render
	 * @return string The rendered RSS XML string
	 * @throws Minz_ConfigurationException
	 */
	private function renderEntryAsRss(FreshRSS_Entry $entry): string {
		$view = new FreshRSS_View();
		$view->entries = [$entry];
		$view->internal_rendering = true;
		$view->publishLabelsInsteadOfTags = false;
		$view->entryIdsTagNames = [];
		$view->rss_base = '';
		$view->image_url = '';

		$feed = $entry->feed();
		$view->rss_title = $feed !== null ? htmlspecialchars_decode($feed->name(), ENT_QUOTES) : '';
		$view->html_url = $feed !== null ? htmlspecialchars_decode($feed->website()) : '';
		$view->rss_url = $feed !== null ? htmlspecialchars_decode($feed->url()) : '';
		$view->description = '';

		$view->_layout(null);
		$view->_path('index/rss.phtml');
		return $view->renderToString();
	}

	/**
	 * Send an HTTP request via the FreshRSS HTTP utility
	 *
	 * @param string $url Target URL
	 * @param string $method HTTP method (GET, POST, PUT, etc.)
	 * @param string $contentType Content type ('json', 'form', or 'rss')
	 * @param array<array-key,mixed>|string $body Request body as an array or string
	 * @param list<string> $headers HTTP headers
	 * @throws \RuntimeException
	 */
	private function sendRequest(string $url, string $method, string $contentType, array|string $body, array $headers = []): void {
		if ($url === '') {
			throw new RuntimeException('Webhook URL is empty');
		}

		$processedBody = null;
		if ($body !== '' && $body !== [] && $method !== 'GET') {
			if (is_string($body)) {
				$processedBody = $body;
			} else {
				$processedBody = match ($contentType) {
					'form' => http_build_query($body),
					default => json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
				};
			}
		}

		if (empty($headers)) {
			$headers = match ($contentType) {
				'form' => ['Content-Type: application/x-www-form-urlencoded'],
				'rss' => ['Content-Type: application/rss+xml; charset=utf-8'],
				default => ['Content-Type: application/json'],
			};
		}

		$curlOptions = [
			CURLOPT_HTTPHEADER => array_values($headers),
			CURLOPT_TIMEOUT => 10,
		];

		if ($method === 'POST') {
			$curlOptions[CURLOPT_POST] = true;
		} elseif ($method !== 'GET') {
			$curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
		}

		if ($processedBody !== null && $method !== 'GET') {
			$curlOptions[CURLOPT_POSTFIELDS] = $processedBody;
		}

		$response = FreshRSS_http_Util::httpGet($url, cachePath: null, type: 'json', curl_options: $curlOptions);

		if ($response['fail'] ?? false) {
			throw new RuntimeException('HTTP request failed for URL: ' . $url);
		}
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
}
