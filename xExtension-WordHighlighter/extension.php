<?php

declare(strict_types=1);

final class WordHighlighterExtension extends Minz_Extension
{
	public string $word_highlighter_conf = '';
	public bool $enable_in_article = false;
	public bool $enable_logs = false;
	public bool $case_sensitive = false;
	public bool $separate_word_search = false;

	#[\Override]
	public function init(): void {
		$this->registerTranslates();
		$this->registerHook(Minz_HookType::JsVars, $this->jsVars(...));

		// register CSS for WordHighlighter:
		Minz_View::appendStyle($this->getFileUrl('style.css'));

		Minz_View::appendScript($this->getFileUrl('mark.min.js'), cond: false, defer: false, async: false);

		Minz_View::appendScript($this->getFileUrl('word-highlighter.js'));
	}

	/**
	 * @param array<string,mixed> $vars
	 * @return array<string,mixed>
	 */
	public function jsVars(array $vars): array {
		$name = $this->getName();
		// $vars values are mixed; copy an existing array before adding the configuration key.
		$extensionVars = [];
		if (isset($vars[$name]) && is_array($vars[$name])) {
			$extensionVars = $vars[$name];
		}
		$extensionVars['configuration'] = $this->getWordHighlighterConfiguration();
		$vars[$name] = $extensionVars;
		return $vars;
	}

	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			// setUserConfigurationValue() saves on every call. Edge has no multi-key
			// write, so the flat keys are stored one at a time.
			$this->setUserConfigurationValue(
				'words',
				$this->normalizeWords(preg_split('/\r\n|\n|\r/', Minz_Request::paramString('words_list', true)) ?: [])
			);
			$this->setUserConfigurationValue('enable_in_article', Minz_Request::paramBoolean('enable-in-article'));
			$this->setUserConfigurationValue('enable_logs', Minz_Request::paramBoolean('enable_logs'));
			$this->setUserConfigurationValue('case_sensitive', Minz_Request::paramBoolean('case_sensitive'));
			$this->setUserConfigurationValue('separate_word_search', Minz_Request::paramBoolean('separate_word_search'));
		}

		$configuration = $this->getWordHighlighterConfiguration();
		$this->word_highlighter_conf = implode("\n", $configuration['words']);
		$this->enable_in_article = $configuration['enable_in_article'];
		$this->enable_logs = $configuration['enable_logs'];
		$this->case_sensitive = $configuration['case_sensitive'];
		$this->separate_word_search = $configuration['separate_word_search'];
	}

	/**
	 * Settings from the user configuration, or from the legacy per-user JSON file
	 * (static/config.<user>.json, written by versions up to 0.0.4) until they are saved once.
	 * @return array{words:list<string>,enable_in_article:bool,enable_logs:bool,case_sensitive:bool,separate_word_search:bool}
	 */
	private function getWordHighlighterConfiguration(): array {
		$storedWords = $this->getUserConfigurationArray('words');
		if ($storedWords === null) {
			$configuration = $this->readLegacyConfiguration();
		} else {
			$configuration = [
				'words' => $storedWords,
				'enable_in_article' => $this->getUserConfigurationBool('enable_in_article'),
				'enable_logs' => $this->getUserConfigurationBool('enable_logs'),
				'case_sensitive' => $this->getUserConfigurationBool('case_sensitive'),
				'separate_word_search' => $this->getUserConfigurationBool('separate_word_search'),
			];
		}

		return [
			'words' => $this->normalizeWords(is_array($configuration['words'] ?? null) ? $configuration['words'] : []),
			'enable_in_article' => ($configuration['enable_in_article'] ?? false) === true,
			'enable_logs' => ($configuration['enable_logs'] ?? false) === true,
			'case_sensitive' => ($configuration['case_sensitive'] ?? false) === true,
			'separate_word_search' => ($configuration['separate_word_search'] ?? false) === true,
		];
	}

	/**
	 * Legacy per-user file from versions up to 0.0.4. Read-only; never written.
	 * @return array<mixed>
	 */
	private function readLegacyConfiguration(): array {
		$current_user = Minz_User::name() ?? '';
		if ($current_user === '') {
			$current_user = Minz_Session::paramString('currentUser');
		}
		if (preg_match('/\A[A-Za-z0-9_.-]+\z/', $current_user) !== 1) {
			return [];
		}

		$configFileJson = join_path($this->getPath(), 'static', 'config.' . $current_user . '.json');
		if (!is_file($configFileJson) || !is_readable($configFileJson)) {
			return [];
		}

		try {
			$legacyConfiguration = json_decode(file_get_contents($configFileJson) ?: '', true, 8, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Ignore invalid legacy configuration.
			return [];
		}

		return is_array($legacyConfiguration) ? $legacyConfiguration : [];
	}

	/**
	 * @param array<mixed> $words
	 * @return list<string>
	 */
	private function normalizeWords(array $words): array {
		$result = [];
		foreach ($words as $word) {
			if (is_string($word) && trim($word) !== '') {
				$result[] = trim($word);
			}
		}
		return array_values(array_unique($result));
	}
}
