# FreshRSS - AI Summarizer extension

This FreshRSS extension automatically generates AI-powered summaries for RSS articles and displays them at the top of each article. It addresses [Feature Request #240](https://github.com/FreshRSS/Extensions/issues/240) by using OpenAI-compatible LLM APIs to create concise summaries that help readers quickly decide if an article is worth their time.

## Features

- **Automatic summarization**: Generates AI summaries displayed at the top of articles
- **OpenAI-compatible API support**: Works with OpenAI, Ollama, LocalAI, LiteLLM, and any OpenAI-compatible endpoint
- **Customizable prompts**: Full control over the summarization prompt with template placeholders
- **Multiple display styles**: Choose from blockquote, styled info box, or simple italic formatting
- **Smart caching**: Summaries are cached per entry to avoid redundant API calls
- **Conditional processing**: Filter which articles get summarized using FreshRSS Boolean search syntax
- **Unread-only mode**: Optionally only summarize unread articles to save API costs
- **Retry logic**: Automatic retry with exponential backoff for transient API failures
- **Content truncation**: Control token usage by limiting content length sent to the API

## Requirements

- FreshRSS 1.28.2+
- An OpenAI-compatible LLM API endpoint (OpenAI, Ollama, etc.)

## Installation

1. Download or clone this extension into your FreshRSS `./extensions/` directory:
   ```bash
   cd /path/to/FreshRSS/extensions
   git clone https://github.com/FreshRSS/Extensions.git
   ```

2. Navigate to **Settings** → **Extensions** in FreshRSS

3. Enable the **AI Summarizer** extension

4. Click **Configure** to set up your API endpoint and preferences

## Configuration

### API Configuration

| Setting | Default | Description |
|:--------|:--------|:------------|
| API URL | *(empty)* | OpenAI-compatible API base URL (e.g., `https://api.openai.com/v1` for OpenAI, `http://localhost:11434/v1` for Ollama) |
| API Key | *(empty)* | Bearer token for API authentication (optional for local APIs like Ollama) |
| Model | `gpt-4o-mini` | Model name (e.g., `gpt-4o-mini`, `llama3`, `gemini-pro`) |
| Timeout | `30` | HTTP request timeout in seconds (1–300) |

### Summarization Settings

| Setting | Default | Description |
|:--------|:--------|:------------|
| Enable AI summarization | Off | Master toggle for the extension |
| Summary display style | `blockquote` | How to display the summary: `blockquote` (default), `info-box` (styled with background), or `simple` (italic) |
| Only summarize unread articles | On | Skip summarization for articles you've already read to save API costs |
| Max content length | `8000` | Maximum characters for the `{content}` placeholder (0 = unlimited) |
| Max summary tokens | `512` | Maximum tokens for the summary response (0 = unlimited) |
| Max API retries | `2` | Number of retry attempts for failed API calls (0–5) |

### Summarization Prompt Template

The user prompt is a customizable template with placeholders that are replaced with article data:

| Placeholder | Value |
|:------------|:------|
| `{title}` | Article title |
| `{content}` | Article content (HTML stripped, truncated if needed) |
| `{author}` | Article author(s) |
| `{url}` | Article link |
| `{feed_url}` | Feed URL |
| `{feed_name}` | Feed name |
| `{date}` | Article date |

**Default prompt:**
```
Summarize the following article in 2-3 sentences, focusing on the key points:

Title: {title}
Content: {content}
```

### Conditions for Summarization

**Search filters** (one per line): Only entries matching at least one filter are summarized. Uses [FreshRSS Boolean search syntax](https://freshrss.github.io/FreshRSS/en/users/10_filter.html). Leave empty to summarize all entries.

Example filters:
```
intitle:AI intitle:Machine Learning
author:TechCrunch
inurl:arxiv.org
```

## How It Works

1. When an article is displayed (`EntryBeforeDisplay` hook), the extension checks:
   - Is AI summarization enabled?
   - Does the entry match the configured search filters?
   - Is it unread (if "only unread" is enabled)?
   - Is there a cached summary for this entry?

2. If no cached summary exists:
   - The user prompt template is populated with article data
   - The LLM API is called with the system prompt and user prompt
   - The returned summary is cached and prepended to the article content

3. The summary is formatted according to the selected display style and shown at the top of the article

## Example Display Styles

### Blockquote (default)
```
> 📝 AI Summary: This article discusses the latest advances in AI...
___
[Original article content...]
```

### Info Box
```
┌─────────────────────────────────────┐
│ 📝 AI Summary: This article...     │
│ [Styled with blue background]       │
└─────────────────────────────────────┘
[Original article content...]
```

### Simple
```
Summary: This article discusses the latest advances in AI...
___
[Original article content...]
```

## Using with Ollama (Local LLM)

For privacy-conscious users or to avoid API costs, you can run Ollama locally:

1. Install and start Ollama: https://ollama.ai/
2. Pull a model: `ollama pull llama3`
3. Configure the extension:
   - API URL: `http://localhost:11434/v1`
   - API Key: *(leave empty)*
   - Model: `llama3`

## Performance Considerations

- **Caching**: Summaries are cached per entry, so each article is only summarized once
- **Unread-only mode**: Enable "Only summarize unread articles" to avoid re-summarizing on every view
- **Content length**: Reduce `Max content length` to control token usage and costs
- **Search filters**: Use filters to only summarize high-value articles (e.g., from specific feeds)

## Privacy

- Article content is sent to the configured API endpoint
- Use a local LLM (Ollama, LocalAI) if you don't want to send article content to third-party APIs
- Summaries are stored in FreshRSS user configuration (not shared between users)

## Troubleshooting

**Summaries not appearing:**
- Check that "Enable AI summarization" is checked
- Verify your API URL and API Key are correct
- Check FreshRSS logs for API error messages
- Test your API endpoint with curl: `curl -X POST https://api.openai.com/v1/chat/completions -H "Authorization: Bearer YOUR_KEY" -H "Content-Type: application/json" -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"Hello"}]}'`

**Slow performance:**
- Reduce `Max content length` to send less data to the API
- Enable "Only summarize unread articles"
- Use a faster/local model (e.g., Ollama)
- Reduce `Timeout` value (may cause failures for slow APIs)

**High API costs:**
- Use a cheaper model (e.g., `gpt-4o-mini` instead of `gpt-4o`)
- Enable "Only summarize unread articles"
- Reduce `Max content length`
- Use search filters to only summarize important feeds
- Switch to a local LLM (Ollama, LocalAI)

## Related Projects

This extension complements other AI-powered FreshRSS extensions:
- [LLM Classification](https://github.com/FreshRSS/Extensions/tree/main/xExtension-LlmClassification) - Auto-tag articles using LLMs
- [freshrss-ai-assistant](https://github.com/cvlc/freshrss-ai-assistant) - Retitle, auto-tag, and generate category digests
- [xExtension-OllamaSummarizer](https://github.com/fspv/xExtension-OllamaSummarizer) - Summarize with Ollama (different approach using Chrome DevTools Protocol)

## Changelog

**v0.1** (2026-06-02)
- Initial release
- OpenAI-compatible API support
- Configurable display styles
- Per-entry caching
- Unread-only mode
- Search filters
- Retry logic with exponential backoff

## Contributing

This extension is part of the official FreshRSS Extensions repository. Contributions are welcome! Please submit issues and pull requests to https://github.com/FreshRSS/Extensions.

## License

This extension is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0), the same license as FreshRSS.
