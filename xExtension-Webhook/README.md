# FreshRSS Webhook Extension

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![FreshRSS](https://img.shields.io/badge/FreshRSS-1.20.0+-green.svg)](https://freshrss.org/)

A powerful FreshRSS extension that automatically sends webhook notifications when RSS entries match specified keywords. Perfect for integrating with Discord, Slack, Telegram, or any service that supports webhooks.

## 🚀 Features

- **Automated Notifications**: Automatically sends webhooks when new RSS entries match your keywords
- **Flexible Pattern Matching**: Search in titles, feed names, authors, or content
- **Multiple HTTP Methods**: Supports GET, POST, PUT, DELETE, PATCH, OPTIONS, and HEAD
- **Configurable Formats**: Send data as JSON or form-encoded
- **Template System**: Customizable webhook payloads with placeholders
- **Comprehensive Logging**: Detailed logging for debugging and monitoring
- **Error Handling**: Robust error handling with graceful fallbacks
- **Test Functionality**: Built-in test feature to verify webhook configuration

## 📋 Requirements

- FreshRSS 1.20.0 or later
- PHP 8.1 or later
- cURL extension enabled

## 🔧 Installation

1. Download the extension files
2. Upload the `xExtension-Webhook` folder to your FreshRSS `extensions` directory
3. Enable the extension in FreshRSS admin panel under Extensions

## ⚙️ Configuration

### Basic Setup

1. Go to **Administration** → **Extensions** → **Webhook**
2. Configure the following settings:

#### Keywords
Enter keywords to match against RSS entries (one per line):
```
breaking news
security alert
your-project-name
```

#### Search Options
- **Search in Title**: Match keywords in article titles
- **Search in Feed**: Match keywords in feed names
- **Search in Authors**: Match keywords in author names  
- **Search in Content**: Match keywords in article content

#### Webhook Settings
- **Webhook URL**: Your webhook endpoint URL
- **HTTP Method**: Choose from GET, POST, PUT, DELETE, etc.
- **Body Type**: JSON or Form-encoded
- **Headers**: Custom HTTP headers (one per line)

### Webhook Body Template

Customize the webhook payload using placeholders:

```json
{
    "title": "__TITLE__",
    "feed": "__FEED__",
    "url": "__URL__",
    "content": "__CONTENT__",
    "date": "__DATE__",
    "timestamp": "__DATE_TIMESTAMP__",
    "authors": "__AUTHORS__",
    "tags": "__TAGS__"
}
```

#### Available Placeholders

| Placeholder | Description |
|-------------|-------------|
| `__TITLE__` | Article title |
| `__FEED__` | Feed name |
| `__URL__` | Article URL |
| `__CONTENT__` | Article content |
| `__DATE__` | Publication date |
| `__DATE_TIMESTAMP__` | Unix timestamp |
| `__AUTHORS__` | Article authors |
| `__TAGS__` | Article tags |

## 🎯 Use Cases

### Discord Webhook
```json
{
    "content": "New article: **__TITLE__**",
    "embeds": [{
        "title": "__TITLE__",
        "url": "__URL__",
        "description": "__CONTENT__",
        "color": 3447003,
        "footer": {
            "text": "__FEED__"
        }
    }]
}
```

### Slack Webhook
```json
{
    "text": "New article from __FEED__",
    "attachments": [{
        "title": "__TITLE__",
        "title_link": "__URL__",
        "text": "__CONTENT__",
        "color": "good"
    }]
}
```

### Custom API Integration
```json
{
    "event": "new_article",
    "data": {
        "title": "__TITLE__",
        "url": "__URL__",
        "feed": "__FEED__",
        "timestamp": "__DATE_TIMESTAMP__"
    }
}
```

## 🔍 Pattern Matching

The extension supports both regex patterns and simple string matching:

### Regex Patterns
```
/security.*/i
/\b(urgent|critical)\b/i
```

### Simple Strings
```
breaking news
security alert
```

## 🛠️ Advanced Configuration

### Custom Headers
Add authentication or custom headers:
```
Authorization: Bearer your-token-here
X-Custom-Header: custom-value
User-Agent: FreshRSS-Webhook/1.0
```

### Error Handling
- Failed webhooks are logged for debugging
- Network timeouts are handled gracefully
- Invalid configurations are validated

### Performance
- Only sends webhooks when patterns match
- Efficient pattern matching with fallbacks
- Minimal impact on RSS processing

## 🐛 Troubleshooting

### Common Issues

**Webhooks not sending:**
- Check that keywords are configured
- Verify webhook URL is accessible
- Enable logging to see detailed information

**Pattern not matching:**
- Test with simple string patterns first
- Check regex syntax if using regex patterns
- Verify search options are enabled

**Authentication errors:**
- Check custom headers configuration
- Verify webhook endpoint accepts your format

### Debugging

Enable logging in the extension settings to see detailed information about:
- Pattern matching results
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
