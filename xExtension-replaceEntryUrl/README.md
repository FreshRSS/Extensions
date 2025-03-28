# replaceEntryUrls extension

This extension is designed for FreshRSS to intercept articles during entry insertion through hooks. It checks whether their links belong to a list of domains allowed for replacement, configured by users in JSON format. If a match is found, the extension uses curl to download the webpage content, extracts the main content using a specified XPath expression, and replaces the original entry's content with the extracted content.
Usually used to handle cases where the content retrieved via XPath from an article only includes a single title.

To use it, upload this entire directory to the FreshRSS `./extensions` directory on your server and enable it on the extension panel in FreshRSS.

Note: If an XPath expression error causes content to not be found, return the entire webpage retrieved by cURL by default.
## Changelog

* 0.2.1		The test can be used normally.

## Configuration settings

* `replace domain` (default: `[]`):This option is a JSON object used to match the domains that need to be replaced. 
- For example: 
  - {"example.com":"//article"}
  - {"example.com":"//div[contains(@class,'content-area')]","zzz.com":"//div",...}






