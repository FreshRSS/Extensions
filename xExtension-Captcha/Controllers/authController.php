<?php
declare(strict_types=1);

class FreshExtension_auth_Controller extends FreshRSS_auth_Controller {
	/**
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_PermissionDeniedException
	 */
	public function formLoginAction(): void {
		if (!CaptchaExtension::initCaptcha()) {
			return;
		}
		$csp = CaptchaExtension::loadDependencies();
		if (!empty($csp)) $this->_csp($csp);

		parent::formLoginAction();
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	public function registerAction(): void {
		// Checking for valid captcha is not needed here since this isn't a POST action
		$csp = CaptchaExtension::loadDependencies();
		if (!empty($csp)) $this->_csp($csp);

		parent::registerAction();
	}
}
