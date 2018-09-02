# Image Proxy extension

This FreshRSS extension allows you to get rid of insecure content warnings or disappearing images when you use an encrypted connection to FreshRSS. An encrypted connection can be [very easily enabled](http://fransdejonge.com/2016/05/lets-encrypt-on-debianjessie/) thanks to the [Let's Encrypt](https://letsencrypt.org/) initiative.

To use it, upload this entire directory to the FreshRSS `./extensions` directory on your server and enable it on the extension panel in FreshRSS.

## Proxy Settings

By default this extension will use the [images.weserv.nl](https://images.weserv.nl) image caching and resizing proxy, but instead you can supply your own proxy URL in the settings. The source code for the images.weserv.nl proxy can be found at [github.com/andrieslouw/imagesweserv](https://github.com/andrieslouw/imagesweserv), but of course other methods are available. For example, in Apache you could [use `mod_rewrite` to set up a simple proxy](https://httpd.apache.org/docs/2.2/rewrite/proxy.html) and similar methods are available in nginx and lighttpd. Alternatively you could use a simple PHP script, [along these lines](https://github.com/Alexxz/Simple-php-proxy-script). Keep in mind that too simple a proxy could introduce security risks, which is why the default proxy processes the images.

By ticking the dedicated checkbox, you can also force the use of the proxy, even for images coming through an encrypted channel. This makes the server that hosts your FreshRSS instance the only point of entry for images, preventing your client from connecting directly to the RSS sources to recover them (which could be a privacy concern in extreme cases).
