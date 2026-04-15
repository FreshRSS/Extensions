<?php

declare(strict_types=1);

final class LlmClassificationExtension extends Minz_Extension {
	private const DEFAULT_MODEL = 'gpt-4o-mini';
	private const DEFAULT_TIMEOUT = 30;
	private const DEFAULT_MAX_CONTENT_LENGTH = 4000;
	private const PROMPT_FILENAME = 'prompt.md';

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
		$this->registerHook(Minz_HookType::EntryBeforeInsert, [$this, 'classifyEntry']);

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
		if ($this->getUserConfigurationBool('enable_tags') === null) {
			$this->setUserConfigurationValue('enable_tags', false);
		}
		if ($this->getUserConfigurationString('tag_prefix') === null) {
			$this->setUserConfigurationValue('tag_prefix', '');
		}
		if ($this->getUserConfigurationString('allowed_tags') === null) {
			$this->setUserConfigurationValue('allowed_tags', '');
		}
		if ($this->getUserConfigurationString('search_filter') === null) {
			$this->setUserConfigurationValue('search_filter', '');
		}
	}

	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			$this->setUserConfigurationValue('api_url',
				trim(Minz_Request::paramString('api_url', plaintext: true)));
			$this->setUserConfigurationValue('api_key',
				trim(Minz_Request::paramString('api_key', plaintext: true)));
			$this->setUserConfigurationValue('model',
				trim(Minz_Request::paramString('model', plaintext: true)) ?: self::DEFAULT_MODEL);
			$userPrompt = trim(Minz_Request::paramString('user_prompt', plaintext: true))
				?: _t('ext.llm_classification.default_prompt');
			$this->saveFile(self::PROMPT_FILENAME, $userPrompt);
			$this->setUserConfigurationValue('max_content_length',
				Minz_Request::paramInt('max_content_length') ?: self::DEFAULT_MAX_CONTENT_LENGTH);
			$this->setUserConfigurationValue('timeout',
				Minz_Request::paramInt('timeout') ?: self::DEFAULT_TIMEOUT);

			$this->setUserConfigurationValue('enable_tags',
				Minz_Request::paramBoolean('enable_tags'));
			$this->setUserConfigurationValue('tag_prefix',
				trim(Minz_Request::paramString('tag_prefix', plaintext: true)));
			$this->setUserConfigurationValue('allowed_tags',
				trim(Minz_Request::paramString('allowed_tags', plaintext: true)));

			$this->setUserConfigurationValue('search_filter',
				trim(Minz_Request::paramString('search_filter', plaintext: true)));
		}

		$this->user_prompt = '';
		if ($this->hasFile(self::PROMPT_FILENAME)) {
			$this->user_prompt = $this->getFile(self::PROMPT_FILENAME) ?? '';
		}
	}

	/**
	 * Build the system prompt that constrains the LLM to return a specific JSON structure.
	 * @throws FreshRSS_Context_Exception
	 */
	public function getSystemPrompt(): string {
		$prompt = <<<'PROMPT'
			You are a classification assistant. Analyse the article provided by the user and respond with a JSON object.
			Your response MUST be valid JSON with exactly this structure: `{"tags": ["tag1", "tag2"]}`
			Rules:
			- "tags" is an array of short classification labels (UTF-8). It can be empty.
			- Do NOT include any text outside the JSON object.

			PROMPT;

		$allowedTags = $this->getUserConfigurationString('allowed_tags') ?? '';
		if ($allowedTags !== '') {
			$tagList = array_filter(array_map('trim', explode("\n", $allowedTags)), static fn(string $tag) => $tag !== '');
			if ($tagList !== []) {
				$prompt .= '- You MUST only use tags from this list: ' . implode(', ', $tagList) . "\n";
			}
		}

		return $prompt;
	}

	/**
	 * Build the user prompt by replacing placeholders with entry values.
	 * @throws FreshRSS_Context_Exception
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
			'{tags}' => $entry->tags(true),
		];

		return strtr($template, $replacements);
	}

	/**
	 * Check whether the entry matches the configured search filter.
	 * Returns true if no filter is configured or the entry matches at least one filter line.
	 * @throws FreshRSS_Context_Exception
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
	 * Call the LLM API and return the parsed classification result.
	 * @return array{tags:array<string>}|null
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_PermissionDeniedException
	 */
	private function callLlm(string $systemPrompt, string $userPrompt): ?array {
		$apiUrl = trim($this->getUserConfigurationString('api_url') ?? '');
		$apiKey = trim($this->getUserConfigurationString('api_key') ?? '');
		$model = trim($this->getUserConfigurationString('model') ?? '') ?: self::DEFAULT_MODEL;
		$timeout = $this->getUserConfigurationInt('timeout') ?? self::DEFAULT_TIMEOUT;

		if ($apiUrl === '') {
			return null;
		}

		$url = rtrim($apiUrl, '/') . '/chat/completions';

		$requestBody = json_encode([
			'model' => $model,
			'messages' => [
				['role' => 'system', 'content' => $systemPrompt],
				['role' => 'user', 'content' => $userPrompt],
			],
			'response_format' => ['type' => 'json_object'],
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if ($requestBody === false) {
			Minz_Log::warning('LlmClassification: Failed to encode request body');
			return null;
		}

		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
		];
		if ($apiKey !== '') {
			$headers[] = 'Authorization: Bearer ' . $apiKey;
		}

		$cachePath = CACHE_PATH . '/llm_classification_' . sha1($apiUrl . $requestBody) . '.json';

		$response = FreshRSS_http_Util::httpGet($url, $cachePath, type: 'json', curl_options: [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $requestBody,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => $timeout,
		]);

		if ($response['fail'] || $response['body'] === '') {
			Minz_Log::warning('LlmClassification: API call failed for ' . $url);
			return null;
		}

		$responseData = json_decode($response['body'], true);
		if (!is_array($responseData)) {
			Minz_Log::warning('LlmClassification: Invalid JSON response from API');
			return null;
		}

		$choices = $responseData['choices'] ?? null;
		$content = is_array($choices) && is_array($choices[0] ?? null) && is_array($choices[0]['message'] ?? null)
			? ($choices[0]['message']['content'] ?? null)
			: null;
		if (!is_string($content)) {
			Minz_Log::warning('LlmClassification: Missing choices[0].message.content in API response');
			return null;
		}

		$classification = json_decode($content, true);
		if (is_array($classification) && is_array($classification['tags'] ?? null)) {
			return [
				'tags' => array_filter($classification['tags'], static fn($tag) => is_string($tag) && $tag !== ''),
			];
		}

		Minz_Log::warning('LlmClassification: LLM returned invalid JSON: ' . $content);
		return null;
	}

	/**
	 * Apply classification results to an entry.
	 * @param array<string,mixed> $classification
	 * @throws FreshRSS_Context_Exception
	 */
	private function applyClassification(FreshRSS_Entry $entry, array $classification, bool $removeOldTags): FreshRSS_Entry {
		if (is_array($classification['tags'] ?? null)) {
			$prefix = $this->getUserConfigurationString('tag_prefix') ?? '';
			$allowedTagsStr = $this->getUserConfigurationString('allowed_tags') ?? '';
			$allowedTags = $allowedTagsStr !== ''
				? array_filter(array_map('trim', explode("\n", $allowedTagsStr)), static fn(string $tag) => $tag !== '')
				: [];

			$existingTags = $entry->tags();

			if ($removeOldTags && $prefix !== '') {
				$existingTags = array_values(array_filter(
					$existingTags,
					static fn(string $tag) => !str_starts_with($tag, $prefix)
				));
			}

			$newTags = [];
			foreach ($classification['tags'] as $tag) {
				if (!is_string($tag)) {
					continue;
				}
				$tag = trim($tag);
				if ($tag === '') {
					continue;
				}
				if (!empty($allowedTags) && !in_array($tag, $allowedTags, true)) {
					continue;
				}
				$newTags[] = htmlspecialchars($prefix . $tag, ENT_COMPAT, 'UTF-8');
			}

			$entry->_tags(array_unique(array_merge($existingTags, $newTags)));
		}

		return $entry;
	}

	/**
	 * Hook for EntryBeforeInsert: classify a new entry.
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_PermissionDeniedException
	 */
	public function classifyEntry(FreshRSS_Entry $entry): FreshRSS_Entry {
		$enableTags = $this->getUserConfigurationBool('enable_tags') ?? false;
		$apiUrl = $this->getUserConfigurationString('api_url') ?? '';
		if (!$enableTags || $apiUrl === '' || !$this->hasFile(self::PROMPT_FILENAME)) {
			return $entry;
		}

		if (!$this->entryMatchesSearchFilter($entry)) {
			return $entry;
		}

		$systemPrompt = $this->getSystemPrompt();
		$userPrompt = $this->buildUserPrompt($entry);
		if ($userPrompt === '') {
			return $entry;
		}

		$classification = $this->callLlm($systemPrompt, $userPrompt);
		if ($classification === null) {
			return $entry;
		}

		return $this->applyClassification($entry, $classification, removeOldTags: true);
	}
}
