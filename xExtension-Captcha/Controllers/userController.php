<?php
declare(strict_types=1);

class FreshExtension_user_Controller extends FreshRSS_user_Controller {
	/**
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_PermissionDeniedException
	 */
	public function createAction(): void {
		if (!CaptchaExtension::initCaptcha()) {
			return;
		}
		$csp = CaptchaExtension::loadDependencies();
		if (!empty($csp)) $this->_csp($csp);

		parent::createAction();
	}
}

