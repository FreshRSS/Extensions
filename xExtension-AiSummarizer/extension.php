<?php

declare(strict_types=1);

final class AiSummarizerExtension extends Minz_Extension {
	private const DEFAULT_MODEL = 'gpt-4o-mini';
	private const DEFAULT_TIMEOUT = 30;
	private const DEFAULT_MAX_CONTENT_LENGTH = 8000;
	private const DEFAULT_MAX_TOKENS = 512;
	private const DEFAULT_MAX_RETRIES = 2;
	private const RETRYABLE_HTTP_STATUSES = [429, 500, 502, 503, 504];
	private const PROMPT_FILENAME = 'prompt.md';
	private const SUMMARY_CACHE_KEY_PREFIX = 'ai_summary_';

	public string $user_prompt = '';

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	#[\Override]
	public function init(): void {
		if (!FreshRSS_Context::hasSystemConf()) {
			throw new FreshRSS_Context_Exception('System configuration not initialised!');
		}
		$this->registerTranslates();
		$this->registerHook(Minz_HookType::EntryBeforeDisplay, [$this, 'summarizeEntry']);

		if ($this->getUserConfigurationString('api_url') === null) {
			$this->setUserConfigurationValue('api_url', '');
		}
		if ($this->getUserConfigurationString('api_key') === null) {
			$this->setUserConfigurationValue('api_key', '');
		}
		if ($this->getUserConfigurationString('model') === null) {
			$this->setUserConfigurationValue('model', self::DEFAULT_MODEL);
		}
		if ($this->getUserConfigurationInt('max_content_length') === null) {
			$this->setUserConfigurationValue('max_content_length', self::DEFAULT_MAX_CONTENT_LENGTH);
		}
		if ($this->getUserConfigurationInt('timeout') === null) {
			$this->setUserConfigurationValue('timeout', self::DEFAULT_TIMEOUT);
		}
		if ($this->getUserConfigurationBool('enable_summary') === null) {
			$this->setUserConfigurationValue('enable_summary', false);
		}
		if ($this->getUserConfigurationInt('max_tokens') === null) {
			$this->setUserConfigurationValue('max_tokens', self::DEFAULT_MAX_TOKENS);
		}
		if ($this->getUserConfigurationInt('max_retries') === null) {
			$this->setUserConfigurationValue('max_retries', self::DEFAULT_MAX_RETRIES);
		}
		if ($this->getUserConfigurationString('search_filter') === null) {
			$this->setUserConfigurationValue('search_filter', '');
		}
		if ($this->getUserConfigurationString('summary_style') === null) {
			$this->setUserConfigurationValue('summary_style', 'blockquote');
		}
		if ($this->getUserConfigurationBool('only_unread') === null) {
			$this->setUserConfigurationValue('only_unread', true);
		}
	}

	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			$apiUrl = trim(Minz_Request::paramString('api_url', plaintext: true));
			$apiUrl = preg_replace('#/chat/completions/?$#i', '', $apiUrl) ?? $apiUrl;
			$this->setUserConfigurationValue('api_url', $apiUrl);
			$this->setUserConfigurationValue('api_key',
				trim(Minz_Request::paramString('api_key', plaintext: true)));
			$this->setUserConfigurationValue('model', trim(Minz_Request::paramString('model', plaintext: true)));
			$userPrompt = trim(Minz_Request::paramString('user_prompt', plaintext: true))
				?: 'Summarize the following article in 2-3 sentences, focusing on the key points:\n\nTitle: {title}\nContent: {content}';
			$this->saveFile(self::PROMPT_FILENAME, $userPrompt);
			$this->setUserConfigurationValue('max_content_length', Minz_Request::paramInt('max_content_length'));
			$this->setUserConfigurationValue('timeout',
				Minz_Request::paramInt('timeout') ?: self::DEFAULT_TIMEOUT);
			$this->setUserConfigurationValue('max_tokens', Minz_Request::paramInt('max_tokens'));
			$this->setUserConfigurationValue('max_retries',
				max(0, min(5, Minz_Request::paramInt('max_retries'))));

			$this->setUserConfigurationValue('enable_summary',
				Minz_Request::paramBoolean('enable_summary'));
			$this->setUserConfigurationValue('summary_style',
				trim(Minz_Request::paramString('summary_style', plaintext: true)));
			$this->setUserConfigurationValue('only_unread',
				Minz_Request::paramBoolean('only_unread'));

			$this->setUserConfigurationValue('search_filter',
				trim(Minz_Request::paramString('search_filter', plaintext: true)));
		}

		$this->user_prompt = '';
		if ($this->hasFile(self::PROMPT_FILENAME)) {
			$this->user_prompt = $this->getFile(self::PROMPT_FILENAME) ?? '';
		}
	}

	/**
	 * Build the system prompt for summarization.
	 */
	public function getSystemPrompt(): string {
		return <<<'PROMPT'
			You are a summarization assistant.
			Create a concise, informative summary of the article.
			Focus on the main points and key takeaways.
			Return only the summary text without any preamble or conclusion.
			PROMPT;
	}

	/**
	 * Build the user prompt by replacing placeholders with entry values.
	 */
	private function buildUserPrompt(FreshRSS_Entry $entry): string {
		$template = $this->getFile(self::PROMPT_FILENAME) ?? '';
		if ($template === '') {
			return '';
		}

		$content = strip_tags($entry->content());
		$maxLength = $this->getUserConfigurationInt('max_content_length') ?? self::DEFAULT_MAX_CONTENT_LENGTH;
		if ($maxLength > 0 && mb_strlen($content) > $maxLength) {
			$content = mb_substr($content, 0, $maxLength) . '…';
		}

		$feed = $entry->feed();

		$replacements = [
			'{title}' => $entry->title(),
			'{content}' => $content,
			'{author}' => $entry->authors(true),
			'{url}' => $entry->link(),
			'{feed_url}' => $feed !== null ? $feed->url() : '',
			'{feed_name}' => $feed !== null ? $feed->name() : '',
			'{date}' => $entry->date(),
		];

		return strtr($template, $replacements);
	}

	/**
	 * Check whether the entry matches the configured search filter.
	 */
	private function entryMatchesSearchFilter(FreshRSS_Entry $entry): bool {
		$filterStr = $this->getUserConfigurationString('search_filter') ?? '';
		if ($filterStr === '') {
			return true;
		}
		$lines = array_filter(array_map('trim', explode("\n", $filterStr)), static fn(string $line) => $line !== '');
		foreach ($lines as $line) {
			$booleanSearch = new FreshRSS_BooleanSearch($line);
			if ($entry->matches($booleanSearch)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Determine whether an HTTP failure is transient and worth retrying.
	 * @param array{fail:bool,status:int,error:string} $response
	 */
	private static function isRetryableFailure(array $response): bool {
		if (!($response['fail'] ?? false)) {
			return false;
		}
		if (($response['status'] ?? 0) === 0 && ($response['error'] ?? '') !== '') {
			return true;
		}
		return in_array($response['status'] ?? 0, self::RETRYABLE_HTTP_STATUSES, true);
	}

	/**
	 * Call the LLM API and return the summary.
	 * @throws Minz_PermissionDeniedException
	 */
	private function callLlm(string $systemPrompt, string $userPrompt): ?string {
		$apiUrl = trim($this->getUserConfigurationString('api_url') ?? '');
		$apiKey = trim($this->getUserConfigurationString('api_key') ?? '');
		$model = trim($this->getUserConfigurationString('model') ?? '') ?: self::DEFAULT_MODEL;
		$timeout = $this->getUserConfigurationInt('timeout') ?? self::DEFAULT_TIMEOUT;

		if ($apiUrl === '') {
			return null;
		}

		$url = rtrim($apiUrl, '/') . '/chat/completions';

		$body = [
			'model' => $model,
			'messages' => [
				['role' => 'system', 'content' => $systemPrompt],
				['role' => 'user', 'content' => $userPrompt],
			],
		];

		$maxTokens = $this->getUserConfigurationInt('max_tokens') ?? self::DEFAULT_MAX_TOKENS;
		if ($maxTokens > 0) {
			$body['max_completion_tokens'] = $maxTokens;
		}

		$requestBody = json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if ($requestBody === false) {
			Minz_Log::warning('AiSummarizer: Failed to encode request body');
			return null;
		}

		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
		];
		if ($apiKey !== '') {
			$headers[] = 'Authorization: Bearer ' . $apiKey;
		}

		$cachePath = CACHE_PATH . '/ai_summarizer_' . sha1($apiUrl . $requestBody) . '.json';

		$maxRetries = $this->getUserConfigurationInt('max_retries') ?? self::DEFAULT_MAX_RETRIES;
		$response = null;

		for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
			$response = FreshRSS_http_Util::httpGet($url, $cachePath, type: 'json', curl_options: [
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $requestBody,
				CURLOPT_HTTPHEADER => $headers,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT => $timeout,
			]);

			if (!($response['fail'] ?? false) && ($response['body'] ?? '') !== '') {
				break;	// Success
			}

			if ($attempt < $maxRetries && self::isRetryableFailure($response)) {
				$delay = (int)pow(2, $attempt);	// Exponential backoff: 1s, 2s, 4s...
				Minz_Log::warning('AiSummarizer: API call failed (HTTP ' . ($response['status'] ?? 0)
					. (($response['error'] ?? '') !== '' ? '; ' . ($response['error'] ?? '') : '')
					. '), retry ' . ($attempt + 1) . '/' . $maxRetries . ' after ' . $delay . 's');
				sleep($delay);
				@unlink($cachePath);
				continue;
			}

			Minz_Log::warning('AiSummarizer: API call failed for ' . $url
				. ' (HTTP ' . ($response['status'] ?? 0)
				. (($response['error'] ?? '') !== '' ? '; ' . ($response['error'] ?? '') : '')
				. '), not retrying');
			return null;
		}

		if ($response === null || ($response['fail'] ?? false) || !is_string($response['body'] ?? null) || ($response['body'] ?? '') === '') {
			Minz_Log::warning('AiSummarizer: API call failed after ' . (1 + $maxRetries) . ' attempt(s) for ' . $url);
			return null;
		}

		$responseData = json_decode($response['body'], true);
		if (!is_array($responseData)) {
			Minz_Log::warning('AiSummarizer: Invalid JSON response from API');
			return null;
		}

		$choices = $responseData['choices'] ?? null;
		$content = is_array($choices) && is_array($choices[0] ?? null) && is_array($choices[0]['message'] ?? null)
			? ($choices[0]['message']['content'] ?? null)
			: null;
		if (!is_string($content)) {
			Minz_Log::warning('AiSummarizer: Missing choices[0].message.content in API response');
			return null;
		}

		return trim($content);
	}

	/**
	 * Get cached summary for an entry or null if not cached.
	 */
	private function getCachedSummary(FreshRSS_Entry $entry): ?string {
		$cacheKey = self::SUMMARY_CACHE_KEY_PREFIX . $entry->id();
		$cached = $this->getUserConfigurationString($cacheKey);
		return $cached !== null && $cached !== '' ? $cached : null;
	}

	/**
	 * Cache a summary for an entry.
	 */
	private function cacheSummary(FreshRSS_Entry $entry, string $summary): void {
		$cacheKey = self::SUMMARY_CACHE_KEY_PREFIX . $entry->id();
		$this->setUserConfigurationValue($cacheKey, $summary);
	}

	/**
	 * Format the summary according to the configured style.
	 */
	private function formatSummary(string $summary): string {
		$style = $this->getUserConfigurationString('summary_style') ?? 'blockquote';
		$escaped = htmlspecialchars($summary, ENT_COMPAT, 'UTF-8');

		return match($style) {
			'blockquote' => '<blockquote class="ai-summary"><strong>📝 AI Summary:</strong> ' . $escaped . '</blockquote><hr/>',
			'info-box' => '<div class="ai-summary" style="background: #f0f8ff; border-left: 4px solid #0066cc; padding: 12px; margin-bottom: 16px;"><strong>📝 AI Summary:</strong> ' . $escaped . '</div>',
			'simple' => '<p class="ai-summary"><em><strong>Summary:</strong> ' . $escaped . '</em></p><hr/>',
			default => '<blockquote class="ai-summary"><strong>📝 AI Summary:</strong> ' . $escaped . '</blockquote><hr/>',
		};
	}

	/**
	 * Hook for EntryBeforeDisplay: add AI summary to entry content.
	 * @throws Minz_PermissionDeniedException
	 */
	public function summarizeEntry(FreshRSS_Entry $entry): FreshRSS_Entry {
		$enableSummary = $this->getUserConfigurationBool('enable_summary') ?? false;
		$apiUrl = $this->getUserConfigurationString('api_url') ?? '';
		if (!$enableSummary || $apiUrl === '' || !$this->hasFile(self::PROMPT_FILENAME)) {
			return $entry;
		}

		// Skip read entries if configured
		$onlyUnread = $this->getUserConfigurationBool('only_unread') ?? true;
		if ($onlyUnread && $entry->isRead()) {
			return $entry;
		}

		if (!$this->entryMatchesSearchFilter($entry)) {
			return $entry;
		}

		// Check cache first
		$cachedSummary = $this->getCachedSummary($entry);
		if ($cachedSummary !== null) {
			$entry->_content($this->formatSummary($cachedSummary) . $entry->content());
			return $entry;
		}

		// Generate summary
		$systemPrompt = $this->getSystemPrompt();
		$userPrompt = $this->buildUserPrompt($entry);
		if ($userPrompt === '') {
			return $entry;
		}

		$summary = $this->callLlm($systemPrompt, $userPrompt);
		if ($summary === null || $summary === '') {
			return $entry;
		}

		// Cache and prepend summary
		$this->cacheSummary($entry, $summary);
		$entry->_content($this->formatSummary($summary) . $entry->content());

		return $entry;
	}
}
