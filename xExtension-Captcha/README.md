# Form Captcha extension

Protect register/login forms with captcha

Currently the following CAPTCHA providers are supported:
* [Cloudflare Turnstile](https://www.cloudflare.com/application-services/products/turnstile/)
* [reCAPTCHA v2/v3](https://developers.google.com/recaptcha)
* [hCaptcha](https://www.hcaptcha.com/)

The extension is especially useful if you're running a public instance and want to protect it from bots.
To see failed captcha solve attempts, look at the logs in: `data/users/_/log.txt` (admin log)

---

*Warning: if you're protecting the login page and you have unsafe autologin enabled, it can allow anyone to bypass the captcha - it's recommended to disable this option*

---

Available configuration settings:
* Protected pages
* CAPTCHA provider
* Site Key
* Secret Key
* Send client IP address?

<details>
<summary>Show configuration screenshot</summary>
![configuration](./screenshot.png)
</details>

## Changelog

* 1.0.0
	* Initial release
