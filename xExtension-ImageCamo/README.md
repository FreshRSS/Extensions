# Image Camo Proxy Extension for FreshRSS

This extension allows FreshRSS to proxy images through a Camo server (eg. [go-camo](https://github.com/cactus/go-camo)), providing secure image delivery without mixed content warnings on HTTPS sites and without running an open proxy.

## Features

- Proxy HTTP and/or HTTPS images through go-camo
- Support for both Base64 and Hex URL encoding (go-camo compatible)
- Configurable scheme handling for protocol-relative URLs
- Preserves original URLs in data attributes for FreshRSS compatibility
- Supports responsive images with srcset attributes

## Requirements

- A running camo server instance (eg. go-camo)
- HMAC key configured in camo server

## Configuration

1. **Camo Proxy URL**: The base URL of your camo server (e.g., `https://your-camo-instance.example.com`)
2. **HMAC Key**: The shared secret key used to sign URLs (must match your camo server configuration)
3. **URL Encoding**: Choose between Base64 (recommended, shorter URLs) or Hex encoding
4. **Proxy HTTP images**: Enable/disable proxying of HTTP images
5. **Proxy HTTPS images**: Enable/disable proxying of HTTPS images (usually disabled for performance)
6. **Proxy protocol-relative URLs**: How to handle URLs starting with `//`
7. **Include http*:// in URL**: Whether to include the protocol scheme in the proxied URL

## How it works

1. The extension intercepts image URLs in RSS feed content
2. For each image URL that matches the configured criteria:
   - Generates an HMAC-SHA1 signature using the configured key
   - Encodes both the signature and URL (Base64 or Hex)
   - Constructs a go-camo compatible URL: `{camo-url}/{signature}/{encoded-url}`
3. The original URLs are preserved in data attributes

## Security Considerations

- Keep your HMAC key secret and secure
- Use a strong, random HMAC key

## Based on

This extension is based on the [xExtension-ImageProxy](https://github.com/FreshRSS/Extensions/tree/master/xExtension-ImageProxy) extension but specifically designed for go-camo compatibility.
