<?php

declare(strict_types=1);

include __DIR__ . "/request.php";

enum BODY_TYPE: string {
    case JSON = "json";
    case FORM = "form";
}

enum HTTP_METHOD: string {
    case GET = "GET";
    case POST = "POST";
    case PUT = "PUT";
    case DELETE = "DELETE";
    case PATCH = "PATCH";
    case OPTIONS = "OPTIONS";
    case HEAD = "HEAD";
}

class WebhookExtension extends Minz_Extension {
    public bool $logsEnabled = false;

    public HTTP_METHOD $webhook_method = HTTP_METHOD::POST;
    public BODY_TYPE $webhook_body_type = BODY_TYPE::JSON;

    public string $webhook_url = "http://<WRITE YOUR URL HERE>";

    /** * @var string[] $webhook_headers as array of strings */
    public array $webhook_headers = ["User-Agent: FreshRSS", "Content-Type: application/x-www-form-urlencoded"];
    public string $webhook_body = '{
    "title": "__TITLE__",
    "feed": "__FEED__",
    "url": "__URL__",
    "created": "__DATE_TIMESTAMP__"
}';

    #[\Override]
    public function init(): void {
        $this->registerTranslates();
        $this->registerHook("entry_before_insert", [$this, "processArticle"]);
    }

    public function handleConfigureAction(): void {
        $this->registerTranslates();

        if (Minz_Request::isPost()) {
            $conf = [
                "keywords" => array_filter(Minz_Request::paramTextToArray("keywords", [])),
                "search_in_title" => Minz_Request::paramString("search_in_title"),
                "search_in_feed" => Minz_Request::paramString("search_in_feed"),
                "search_in_authors" => Minz_Request::paramString("search_in_authors"),
                "search_in_content" => Minz_Request::paramString("search_in_content"),
                "mark_as_read" => (bool) Minz_Request::paramString("mark_as_read"),
                "ignore_updated" => (bool) Minz_Request::paramString("ignore_updated"),

                "webhook_url" => Minz_Request::paramString("webhook_url"),
                "webhook_method" => Minz_Request::paramString("webhook_method"),
                "webhook_headers" => array_filter(Minz_Request::paramTextToArray("webhook_headers", [])),
                "webhook_body" => html_entity_decode(Minz_Request::paramString("webhook_body")),
                "webhook_body_type" => Minz_Request::paramString("webhook_body_type"),
                "enable_logging" => (bool) Minz_Request::paramString("enable_logging"),
            ];
            $this->setSystemConfiguration($conf);
            $this->$logsEnabled = $conf["enable_logging"];

            _LOG($this->$logsEnabled, "saved config: ✅ " . json_encode($conf));

            try {
                if (Minz_Request::paramString("test_request")) {
                    sendReq(
                        $conf["webhook_url"],
                        $conf["webhook_method"],
                        $conf["webhook_body_type"],
                        $conf["webhook_body"],
                        $conf["webhook_headers"],
                        $conf["enable_logging"],
                    );
                }
            } catch (Throwable $err) {
                _LOG_ERR($this->$logsEnabled, "Error when sending TEST webhook. " . $err);
            }
        }
    }

    public function processArticle($entry) {
        if (!is_object($entry)) {
            return;
        }
        if ($this->getSystemConfigurationValue("ignore_updated") && $entry->isUpdated()) {
            _LOG(true, "⚠️ ignore_updated: " . $entry->link() . " ♦♦ " . $entry->title());
            return $entry;
        }

        $searchInTitle = $this->getSystemConfigurationValue("search_in_title") ?? false;
        $searchInFeed = $this->getSystemConfigurationValue("search_in_feed") ?? false;
        $searchInAuthors = $this->getSystemConfigurationValue("search_in_authors") ?? false;
        $searchInContent = $this->getSystemConfigurationValue("search_in_content") ?? false;

        $patterns = $this->getSystemConfigurationValue("keywords") ?? [];
        $markAsRead = $this->getSystemConfigurationValue("mark_as_read") ?? false;
        $logsEnabled = (bool) $this->getSystemConfigurationValue("enable_logging") ?? false;
        $this->$logsEnabled = (bool) $this->getSystemConfigurationValue("enable_logging") ?? false;

        //-- do check keywords: ---------------------------
        if (!is_array($patterns)) {
            _LOG_ERR($logsEnabled, "❗️ No keywords defined in Webhook extension settings.");
            return;
        }

        $title = "❗️NOT INITIALIZED";
        $link = "❗️NOT INITIALIZED";
        $additionalLog = "";

		try {
            $title = $entry->title();
			$link = $entry->link();
			foreach ($patterns as $pattern) {
				if ($searchInTitle && self::isPatternFound("/{$pattern}/", $title)) {
					_LOG($logsEnabled, "matched item by title ✔️ \"{$title}\" ❖ link: {$link}");
                    $additionalLog = "✔️ matched item with pattern: /{$pattern}/ ❖ title \"{$title}\" ❖ link: {$link}";
					break;
				}
				if ($searchInFeed && (is_object($entry->feed()) && self::isPatternFound("/{$pattern}/", $entry->feed()->name()))) {
					_LOG($logsEnabled, "matched item with pattern: /{$pattern}/ ❖ feed \"{$entry->feed()->name()}\", (title: \"{$title}\") ❖ link: {$link}");
					$additionalLog = "✔️ matched item with pattern: /{$pattern}/ ❖ feed \"{$entry->feed()->name()}\", (title: \"{$title}\") ❖ link: {$link}";
					break;
				}
				if ($searchInAuthors && self::isPatternFound("/{$pattern}/", $entry->authors(true))) {
					_LOG($logsEnabled, "✔️ matched item with pattern: /{$pattern}/ ❖ authors \"{$entry->authors(true)}\", (title: {$title}) ❖ link: {$link}");
					$additionalLog = "✔️ matched item with pattern: /{$pattern}/ ❖ authors \"{$entry->authors(true)}\", (title: {$title}) ❖ link: {$link}";
					break;
				}
				if ($searchInContent && self::isPatternFound("/{$pattern}/", $entry->content())) {
					_LOG($logsEnabled, "✔️ matched item with pattern: /{$pattern}/ ❖ content (title: \"{$title}\") ❖ link: {$link}");
					$additionalLog = "✔️ matched item with pattern: /{$pattern}/ ❖ content (title: \"{$title}\") ❖ link: {$link}";
					break;
				}
			}

            if ($markAsRead) {
                $entry->_isRead($markAsRead);
            }

			$this->sendArticle($entry, $additionalLog);

		} catch (Throwable $err) {
			_LOG_ERR($logsEnabled, "Error during sending article ({$link} ❖ \"{$title}\") ERROR: {$err}");
		}

		return $entry;
    }

    private function sendArticle($entry, string $additionalLog = ""): void {
		try {
			$webhookBodyType = $this->getSystemConfigurationValue("webhook_body_type");
			$headers = $this->getSystemConfigurationValue("webhook_headers");
			$bodyStr = $this->getSystemConfigurationValue("webhook_body");

			$bodyStr = str_replace("__TITLE__", self::toSafeJsonStr($entry->title()), $bodyStr);
			$bodyStr = str_replace("__FEED__", self::toSafeJsonStr($entry->feed()->name()), $bodyStr);
			$bodyStr = str_replace("__URL__", self::toSafeJsonStr($entry->link()), $bodyStr);
			$bodyStr = str_replace("__CONTENT__", self::toSafeJsonStr($entry->content()), $bodyStr);
			$bodyStr = str_replace("__DATE__", self::toSafeJsonStr($entry->date()), $bodyStr);
			$bodyStr = str_replace("__DATE_TIMESTAMP__", self::toSafeJsonStr($entry->date(true)), $bodyStr);
			$bodyStr = str_replace("__AUTHORS__", self::toSafeJsonStr($entry->authors(true)), $bodyStr);
			$bodyStr = str_replace("__TAGS__", self::toSafeJsonStr($entry->tags(true)), $bodyStr);

			sendReq(
				$this->getSystemConfigurationValue("webhook_url"),
				$this->getSystemConfigurationValue("webhook_method"),
				$this->getSystemConfigurationValue("webhook_body_type"),
				$bodyStr,
				$this->getSystemConfigurationValue("webhook_headers"),
				(bool) $this->getSystemConfigurationValue("enable_logging"),
                $additionalLog,
			);
		} catch (Throwable $err) {
			_LOG_ERR($this->$logsEnabled, "ERROR in sendArticle: {$err}");
		}
    }

    private function toSafeJsonStr(string|int $str): string {
        $output = $str;
        if (is_numeric($str)) {
            $output = "{$str}";
        } else {
            $output = str_replace("/\"/", "", html_entity_decode($output));
        }
        return $output;
    }

    private function isPatternFound(string $pattern, string $text): bool {
        if (empty($text) || empty($pattern)) {
            return false;
        }
		try {
			if (1 === preg_match($pattern, $text)) {
				return true;
			} elseif (strpos($text, $pattern) !== false) {
				return true;
			}
			return false;
		} catch (Throwable $err) {
			_LOG_ERR($this->$logsEnabled, "ERROR in isPatternFound: (pattern: {$pattern}) {$err}");
			return false;
		}
    }

    public function getKeywordsData() {
        return implode(PHP_EOL, $this->getSystemConfigurationValue("keywords") ?? []);
    }

    public function getWebhookHeaders() {
        return implode(
            PHP_EOL,
            $this->getSystemConfigurationValue("webhook_headers") ?? ($this->webhook_headers ?? []),
        );
    }

    public function getWebhookUrl() {
        return $this->getSystemConfigurationValue("webhook_url") ?? $this->webhook_url;
    }

    public function getWebhookBody() {
        $body = $this->getSystemConfigurationValue("webhook_body");
        return !$body || $body === "" ? $this->webhook_body : $body;
    }

    public function getWebhookBodyType() {
        return $this->getSystemConfigurationValue("webhook_body_type") ?? $this->webhook_body_type;
    }
}

function _LOG(bool $logEnabled, $data): void {
    if ($logEnabled) {
        Minz_Log::warning("[WEBHOOK] " . $data);
    }
}

function _LOG_ERR(bool $logEnabled, $data): void {
    if ($logEnabled) {
        Minz_Log::error("[WEBHOOK] ❌ " . $data);
    }
}
