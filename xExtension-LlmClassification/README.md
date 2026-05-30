# FreshRSS - LLM Classification extension

This FreshRSS extension automatically tags incoming RSS articles by calling an OpenAI-compatible LLM API.
It classifies articles on insertion using a customizable prompt, and applies tags based on the LLM response.

## Features

- Compatible with any OpenAI-compatible API (OpenAI, Ollama, LocalAI, etc.)
- Customizable user prompt with template placeholders for article data
- Tag prefixing (e.g. `llm/`)
- Entry filtering via FreshRSS Boolean search syntax
- Response caching to avoid redundant API calls
- Content truncation to control token usage
- Skips re-classifying articles that the feed republishes without any prompt-relevant change, avoiding duplicate LLM API calls

## Requirements

- FreshRSS 1.28.2+
- An OpenAI-compatible LLM API endpoint

## Installation

To use it, ensure that the `./xExtension-LlmClassification/` directory can be found under FreshRSS `./extensions/` directory, and enable it on the extension panel in FreshRSS.

## Configuration

### API Configuration

| Setting | Default       | Description                                                       |
| :------ | :------------ | :---------------------------------------------------------------- |
| API URL | *(empty)*     | OpenAI-compatible API base URL (e.g. `https://api.openai.com/v1`) |
| API Key | *(empty)*     | Bearer token for API authentication (optional for local APIs)     |
| Model   | `gpt-4o-mini` | Model name                                                        |
| Timeout | `30`          | HTTP request timeout in seconds (1–300)                           |

### Prompts

The **system prompt** is read-only and constrains the LLM to return a JSON object with the structure `{"tags": ["tag1", "tag2"]}`.

The **user prompt** is an editable template. The following placeholders are replaced with article data before each API call:

| Placeholder  | Value                                                |
| :----------- | :--------------------------------------------------- |
| `{title}`    | Article title                                        |
| `{content}`  | Article content (HTML stripped, truncated if needed) |
| `{author}`   | Article author(s)                                    |
| `{url}`      | Article link                                         |
| `{feed_url}` | Feed URL                                             |
| `{feed_name}`| Feed name                                            |
| `{date}`     | Article date                                         |
| `{tags}`     | Existing article tags                                |

**Max content length** (default: `4000` characters): Maximum number of characters for the `{content}` placeholder. Set to `0` for unlimited.

### Tag Classification

| Setting                   | Default   | Description                                                                                                                 |
| :------------------------ | :-------- | :-------------------------------------------------------------------------------------------------------------------------- |
| Enable tag classification | Off       | Master toggle for the extension                                                                                             |
| Tag prefix                | *(empty)* | Prefix prepended to each LLM-generated tag (e.g. `llm/`)                                                                    |
| Allowed tags              | *(empty)* | Whitelist of accepted tags (one per line). If set, only these tags are kept from the LLM response. Empty = all tags allowed |

### Conditions for tagging

**Search filters** (one per line): Only entries matching at least one filter are classified. Uses [FreshRSS Boolean search syntax](https://freshrss.github.io/FreshRSS/en/users/10_filter.html). Leave empty to classify all entries.

**Re-classify when content changes** (default: on): When a feed republishes an existing article, FreshRSS treats it as updated even if nothing semantically changed (e.g. a tweaked date, a reformatted author, a new enclosure attribute). To avoid wasted API calls, the extension stores a hash of the prompt it sent for each classified entry. On a feed update:

- If the prompt would be identical, the previous tags are restored and the LLM is **not** called.
- If the prompt has actually changed and this option is **on**, the LLM is called again to refresh the tags.
- If the prompt has changed and this option is **off**, the previous tags are kept and the LLM is **not** called.

## How it works

1. A new article arrives in FreshRSS
2. The extension checks if tag classification is enabled and the article matches the configured search filters
3. The user prompt is built by replacing placeholders with article data
4. For updates of previously-classified articles, the new prompt is compared against the stored hash; if unchanged, the previous tags are restored and no API call is made
5. Otherwise, the LLM API is called with the system prompt and the user prompt
6. The returned tags are validated (prefix prepended, whitelist enforced) and applied to the article
7. The prompt hash and the list of LLM-assigned tags are stored on the entry so future updates can be deduplicated

## Changelog

- 0.1: Initial version
