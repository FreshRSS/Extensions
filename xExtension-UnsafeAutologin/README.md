# Unsafe Autologin

You can install this extension to bring back unsafe autologin functionality after enabling it.

This extension should not be used on public multi-user instances.
It is especially advised to not use this extension, unless only as a last resort, since your password may end up in plaintext in server logs.

## Changelog

* 1.0.1 [2026-03-14]
	* Ensure controller is hooked properly, even when other extensions are also hooking the same controller, by using new `Minz_HookType::ActionExecute` hook
* 1.0.0 [2025-11-23]
	* Initial release
