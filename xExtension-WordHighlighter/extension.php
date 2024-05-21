<?php

declare(strict_types=1);

final class WordHighlighterExtension extends Minz_Extension
{
	public string $word_highlighter_conf;
	public string $permission_problem = '';

	#[\Override]
	public function init(): void
	{
		$this->registerTranslates();

		// register CSS for WordHighlighter:
		Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));

		// register script for highlighting functionality:
		Minz_View::appendScript($this->getFileUrl('word-highlighter.js', 'js'));

		$current_user = Minz_Session::paramString('currentUser');
		// $filename = 'config-words.' . $current_user . '.js';
		// $configFileWithWords = join_path($this->getPath(), 'static', $filename);

		$staticPath = join_path($this->getPath(), 'static');
		$configFileJs = join_path($staticPath, ('config-words.' . $current_user . '.js'));

		if (file_exists($configFileJs)) {
			Minz_View::appendScript($this->getFileUrl(('config-words.' . $current_user . '.js'), 'js'));
		}
	}

	#[\Override]
	public function handleConfigureAction(): void
	{
		$this->registerTranslates();

		$current_user = Minz_Session::paramString('currentUser');
		$filename = 'config-words.' . $current_user . '.txt';
		$staticPath = join_path($this->getPath(), 'static');
		$configFileWithWords = join_path($staticPath, $filename);

		if (!file_exists($configFileWithWords) && !is_writable($staticPath)) {
			$tmpPath = explode(EXTENSIONS_PATH . '/', $staticPath);
			$this->permission_problem = $tmpPath[1] . '/';

		} elseif (file_exists($configFileWithWords) && !is_writable($configFileWithWords)) {
			$tmpPath = explode(EXTENSIONS_PATH . '/', $configFileWithWords);
			$this->permission_problem = $tmpPath[1];

		} elseif (Minz_Request::isPost()) {
			$config = html_entity_decode(Minz_Request::paramString('word-highlighter-conf'));
			file_put_contents($configFileWithWords, $config);
			file_put_contents(join_path($staticPath, ('config-words.' . $current_user . '.js')), $this->toJSArray($config));
		}

		$this->word_highlighter_conf = '';
		if (file_exists($configFileWithWords)) {
			$this->word_highlighter_conf = htmlentities(file_get_contents($configFileWithWords)) ?: '';
		}
	}

	private function toJSArray($inputString)
	{
		$array = explode("\n", $inputString);
		$jsArray = array();
		foreach ($array as $item) {
			$trimmedItem = trim($item);
			if (strlen($trimmedItem) > 1) {
				array_push($jsArray, "'" . addslashes(trim($item)) . "',");
			}
		}
		$js = "window.WordHighlighterConf = [\n" .
			implode("\n", $jsArray) .
			"\n]; console.log({ wordsLoaded: window.WHConfig })";
		return $js;
	}
}
