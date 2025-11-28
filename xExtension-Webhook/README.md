# FreshRSS Webhook

A FreshRSS extension for sending custom webhooks when new article appears (and matches custom criteria)

## Installation / Usage

Please follow official README: https://github.com/FreshRSS/Extensions?tab=readme-ov-file

## Documentation

You can define keywords to be used for matching new incoming article.
When article contains at least one defined keyword, the webhook will be sent.

Each line is checked individually. In addition to normal texts, RegEx expressions can also be defined. These must be able to be evaluated using the PHP function `preg_match`.

Examples:

```text
some keyword
important
/\p{Latin}/i
```

In addition, you can choose whether the matched articles will not be inserted into the database or whether they will be inserted into the database but marked as read (default).

## How it works

```
┌──────────────┐          ┌────────────────────────────────────┐          ┌───────┐
│              │          │             FreshRSS               │          │       │
│              │          │                                    │          │ some  │
│   INTERNET   │          │  ┌────────┐         ┌─────↓─────┐  │          │       │
│              │          │  │FreshRSS│         │• Webhook •│  │          │service│
│              │          │  │  core  │         │ extension │  │          │       │
└────┬─────────┘          └──┴──┬─────┴─────────┴─────┬─────┴──┘          └─────┬─┘
     │                          │                     │                         │
     │       checks RSS         │                     │                         │
     │     for new articles     │                     │                         │
     │◄─────────────────────────┤                     │                         │
     │                          │                     │ if some new article     │
     ├─────────────────────────►│                     │ matches custom criteria │
     │       new articles       ├────────────────────►│                         │
     │                          │     new articles    ├────────────────────────►│
     │                          │                     │      HTTP request       │
     │                          │                     │                         │
     │      checks RSS          │                     │                         │
     │     or new articles      │                     │                         │
     │◄─────────────────────────┤                     │                         │
     │                          │                     │ if no new article       │
     ├─────────────────────────►│                     │ matches custom criteria │
     │      new articles        ├────────────────────►│ no request will be sent │
     │                          │     new articles    │                         │
     │                          │                     │                         │
     │                          │                     │                         │
     ▼                          ▼                     ▼                         ▼
```

- for every new article that matches custom criteria new HTTP request will be sent

- see also discussion: https://github.com/FreshRSS/FreshRSS/discussions/6480

## ⚠️ Limitations

- currently only GET, POST and PUT methods are supported
- there is no validation for configuration
- it's not fully tested and translated yet

## Special Thanks

- inspired by extension [**FilterTitle**](https://github.com/cn-tools/cntools_FreshRssExtensions/tree/master/xExtension-FilterTitle)
by [@cn-tools](https://github.com/cn-tools)
