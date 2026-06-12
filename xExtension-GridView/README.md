# Grid View Extension for FreshRSS

A card/grid view extension for [FreshRSS](https://github.com/FreshRSS/FreshRSS) that displays feed entries in a responsive multi-column layout with prominent images.

## Features

- **Card-based layout**: Displays entries as cards with thumbnails, titles, source info, and descriptions
- **Configurable columns**: Choose between 2, 3, or 4 columns via the extension settings
- **Responsive design**: Automatically adjusts to fewer columns on tablets and mobile
- **Smart thumbnail extraction**: Uses feed thumbnails when available, falls back to extracting images from content (filters out small images <400x400, logos, icons, and theme assets)
- **Open Graph image fetching**: Optionally fetches OG images from article pages that have no thumbnail in the RSS feed (async with max 3 concurrent fetches)
- **Category/feed header**: Shows the current category or feed name at the top of the grid ("Main Stream" when viewing all feeds)
- **Action bar overlay**: Action buttons (mark read, star, share, open) appear as a transparent overlay on the card thumbnail on hover (desktop) or tap (mobile)
- **Mobile-friendly**: Tapping the article title opens the link directly without requiring card selection first; tapping elsewhere on the card toggles the action bar
- **Mobile sidebar toggle**: Optional floating hamburger button on mobile screens to open the FreshRSS sidebar without scrolling to the top
- **Browser Reader Mode**: Opens articles in Firefox Reader Mode (or equivalent) for distraction-free reading
- **FreshRSS Native Share**: Integrated share dropdown using your configured sharing services
- **Star/Favorite support**: Mark articles as favorites directly from the card with AJAX sync
- **Sort by publication date**: Optional setting to default sorting to publication date (newest first)
- **Keyboard shortcut**: Press "G" to toggle grid view on/off
- **Dark theme support**: Works seamlessly with FreshRSS dark themes
- **Persistent preference**: Your grid view state is saved locally across sessions
- **No flash of list view**: Seamless transitions during AJAX feed navigation with FOLV prevention

## Installation

1. Download or clone this repository
2. Copy the `xExtension-GridView` folder to your FreshRSS `extensions/` directory
3. In FreshRSS, go to **Settings → Extensions**
4. Enable the "Grid View" extension

## Usage

1. After enabling the extension, go to its configuration page to set your preferred options
2. Click the **grid icon (▦)** in the header area or press **"G"** on your keyboard to toggle grid view
3. Click on any card to open the article in browser Reader Mode (Firefox) or a new tab
4. Hover over a card (or tap on mobile) to reveal the action buttons:
   - **✓ Mark as Read**: Toggle read/unread state
   - **★ Star**: Mark the article as a favorite (syncs with FreshRSS)
   - **🔗 Share**: Opens FreshRSS native share dropdown with your configured sharing services
   - **↗ Open**: Open the original article in a new tab
5. Articles are automatically marked as read when you click on them

Your grid view preference is saved locally and persists across sessions.

## Configuration

| Option | Description | Default |
| ------ | ----------- | ------- |
| Number of columns | How many columns to display (2-4) | 3 |
| Thumbnail fetching | Fetch Open Graph images from article pages that lack thumbnails | Off |
| Default sorting | Sort by publication date, newest first | Off |
| Mobile menu button | Show a floating sidebar toggle button on mobile screens | Off |
| Sticky navigation bar | Keep the top navigation bar visible while scrolling | On |

## Requirements

- FreshRSS 1.20.0 or later
- PHP 8.1 or later

## Responsive Breakpoints

| Screen Width | Columns |
| ------------ | ------- |
| > 1200px | Configured value (2-4) |
| 900px - 1200px | Up to 3 |
| 600px - 900px | 2 |
| < 600px | 1 |

## Development

### File Structure

```text
xExtension-GridView/
├── metadata.json		 # Extension metadata
├── extension.php		 # Main PHP class (hooks, OG image fetching, config)
├── configure.phtml		 # Configuration form
├── static/
│	├── grid.css		 # Grid layout styles
│	├── grid.js			 # Card transformation + state sync logic
│	└── placeholder.jpg	 # Fallback thumbnail image
├── i18n/
│	├── en/ext.php		 # English translations
│	├── fr/ext.php		 # French translations
│	└── de/ext.php		 # German translations
├── LICENSE
└── README.md
```

### How It Works

1. The extension registers hooks during `init()`: `js_vars` injects configuration, `entry_before_display` injects thumbnail markers, and optionally `entry_before_insert` fetches OG images
2. JavaScript adds a toggle button and listens for the "G" keyboard shortcut
3. When grid view is enabled, the stream container gets a `.grid` class and a `gridview-active` class is added to `body` (for FOLV prevention)
4. A context header is inserted showing the current category, feed name, or "Main Stream"
5. FreshRSS date separator `.transition` elements are hidden in grid mode
6. JavaScript transforms existing `.flux` elements into card format with:
   - Thumbnail extraction (with smart filtering for size and type)
   - Action bar overlay on the thumbnail (visible on hover/tap)
   - Star button with AJAX sync via FreshRSS bookmark links
   - Share button using FreshRSS native share dropdown
7. CSS applies the grid layout using CSS Grid with `--gridview-columns` custom property
8. MutationObservers watch for dynamically loaded entries, stream replacements, and state changes (read/unread/favorite)
9. Click handlers open articles in browser Reader Mode and mark as read
10. On mobile, tapping the article title opens the link directly; tapping elsewhere toggles the action bar
11. When the mobile menu button setting is enabled, a floating hamburger button appears at the bottom-left on screens under 841px, toggling the FreshRSS sidebar via `toggle_aside_click()`

## License

AGPL-3.0 - See [LICENSE](LICENSE) for details.

## Credits

- Built for [FreshRSS](https://github.com/FreshRSS/FreshRSS)
