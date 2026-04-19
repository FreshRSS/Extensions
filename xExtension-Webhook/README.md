# FreshRSS Webhook Extension

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![FreshRSS](https://img.shields.io/badge/FreshRSS-1.20.0+-green.svg)](https://freshrss.org/)

A powerful FreshRSS extension that automatically sends webhook notifications when RSS entries match configured search filters. Perfect for integrating with Discord, Slack, Telegram, or any service that supports webhooks.

## 🚀 Features

- **Automated Notifications**: Automatically sends webhooks when new RSS entries match your search filters
- **Search Filters**: FreshRSS native search filter syntax for precise matching
- **Multiple HTTP Methods**: Supports GET, POST, PUT, DELETE, PATCH, OPTIONS, and HEAD
- **Configurable Formats**: Send data as JSON or form-encoded
- **Template System**: Customizable webhook payloads with placeholders
- **Comprehensive Logging**: Detailed logging for debugging and monitoring
- **Error Handling**: Robust error handling with graceful fallbacks
- **Test Functionality**: Built-in test feature to verify webhook configuration

## 📋 Requirements

- FreshRSS 1.28.2+

## 🔧 Installation

1. Download the extension files
2. Upload the `xExtension-Webhook` folder to your FreshRSS `extensions` directory
3. Enable the extension in FreshRSS admin panel under Extensions

## ⚙️ Configuration

### Basic Setup

1. Go to **Administration** → **Extensions** → **Webhook**
2. Configure the following settings:

#### Search Filter

Use [FreshRSS search filter syntax](https://freshrss.github.io/FreshRSS/en/users/10_filter.html) to match entries. Each line is an OR condition — if any line matches, the webhook fires. Leave empty to match all entries.

```text
intitle:breaking news
intitle:security alert
#your-project-name
```

#### Webhook Settings

- **Webhook URL**: Your webhook endpoint URL
- **HTTP Method**: Choose from GET, POST, PUT, DELETE, etc.
- **Body Type**: JSON or Form-encoded
- **Headers**: Custom HTTP headers (one per line)

### Webhook Body Template

Customize the webhook payload using placeholders:

```json
{
    "title": "{title}",
    "feed": "{feed_name}",
    "url": "{url}",
    "content": "{content}",
    "date": "{date}",
    "timestamp": "{date_timestamp}",
    "author": "{author}",
    "tags": "{tags}"
}
```

#### Available Placeholders

| Placeholder | Description |
| ----------- | ----------- |
| `{title}` | Article title |
| `{url}` | Article URL |
| `{content}` | Article content (HTML) |
| `{date}` | Publication date (string) |
| `{date_timestamp}` | Publication date as Unix timestamp |
| `{author}` | Article authors |
| `{feed_name}` | Feed name |
| `{feed_url}` | Feed URL |
| `{thumbnail_url}` | Thumbnail (image) URL |
| `{tags}` | Article tags (separated by " #") |

## 🎯 Use Cases

### Discord Webhook

```json
{
    "content": "New article: **{title}**",
    "embeds": [{
        "title": "{title}",
        "url": "{url}",
        "description": "{content}",
        "color": 3447003,
        "footer": {
            "text": "{feed_name}"
        }
    }]
}
```

### Slack Webhook

```json
{
    "text": "New article from {feed_name}",
    "attachments": [{
        "title": "{title}",
        "title_link": "{url}",
        "text": "{content}",
        "color": "good"
    }]
}
```

### Custom API Integration

```json
{
    "event": "new_article",
    "data": {
        "title": "{title}",
        "url": "{url}",
        "feed": "{feed_name}",
        "timestamp": "{date_timestamp}"
    }
}
```

## 🔍 Search Filters

The extension uses [FreshRSS search filter syntax](https://freshrss.github.io/FreshRSS/en/users/10_filter.html) to match entries:

### Filter by Field

```text
intitle:security
inurl:example.com
author:John
```

### Tags and Feeds

```text
#breaking-news
f:TechCrunch
```

### Boolean Logic

```text
intitle:urgent OR intitle:critical
intitle:release -intitle:beta
```

## 🛠️ Advanced Configuration

### Custom Headers

Add authentication or custom headers:

```text
Authorization: Bearer your-token-here
X-Custom-Header: custom-value
User-Agent: FreshRSS-Webhook/1.0
```

### Error Handling

- Failed webhooks are logged for debugging
- Network timeouts are handled gracefully
- Invalid configurations are validated

### Performance

- Only sends webhooks when filters match
- Efficient filter evaluation via FreshRSS core
- Minimal impact on RSS processing

## 🐛 Troubleshooting

### Common Issues

**Webhooks not sending:**
- Check that a search filter is configured (or leave empty to match all)
- Verify webhook URL is accessible
- Enable logging to see detailed information

**Filter not matching:**
- Try simple filters first (e.g., `intitle:test`)
- Refer to the [FreshRSS filter documentation](https://freshrss.github.io/FreshRSS/en/users/10_filter.html) for syntax
- Enable logging to see which entries are evaluated

**Authentication errors:**
- Check custom headers configuration
- Verify webhook endpoint accepts your format

### Debugging

Enable logging in the extension settings to see detailed information about:
- Filter matching results
- HTTP request details
- Response codes and errors

## 📝 Changelog

### Version 0.1.1

- Initial release
- Automated webhook notifications
- Pattern matching in multiple fields
- Configurable HTTP methods and formats
- Comprehensive error handling and logging
- Template-based webhook payloads

## 🤝 Contributing

This extension was developed to address [FreshRSS Issue #1513](https://github.com/FreshRSS/FreshRSS/issues/1513).

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Follow FreshRSS coding standards
4. Add tests for new functionality
5. Submit a pull request

## 📄 License

This extension is licensed under the [GNU Affero General Public License v3.0](LICENSE).

## 🙏 Acknowledgments

- FreshRSS development team for the excellent extension system
- Community members who requested and tested this feature
- Contributors to the original feature request

## 📞 Support

- [FreshRSS Documentation](https://freshrss.github.io/FreshRSS/)
- [GitHub Issues](https://github.com/FreshRSS/Extensions/issues)
- [FreshRSS Community](https://github.com/FreshRSS/FreshRSS/discussions)
