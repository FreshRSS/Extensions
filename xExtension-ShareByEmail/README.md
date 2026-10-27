# xExtension-ShareByEmail

This FreshRSS extension allows to share articles by email, in a more powerful way than the simple `mailto` solution.

## How to install

To install this extension, you must upload this directory in your `./extensions` directory and enable it on the extension panel in FreshRSS.

## How to configure

After the installation the `Email` sharing service will added to the list of available sharing services. You need to add the `Email` sharing service to your individual sharing service list before it is available in the sharing menu.

You will have to configure the mailing system in FreshRSS. [See the documentation](https://freshrss.github.io/FreshRSS/en/admins/05_Configuring_email_validation.html#configure-the-smtp-server)

Optionally, you can set a default recipient in the extension configuration, which will prefill the “To” field of the sharing form.

## Changelog

- 0.5.0 Optional default recipient to prefill the sharing form
- 0.3.2 With the new feature, Turkish language deficiencies have been corrected.
- 0.3.0 detail information about the mail system shown in the extension config
- 0.2.3 Turkish language support added
- 0.1 initial version
