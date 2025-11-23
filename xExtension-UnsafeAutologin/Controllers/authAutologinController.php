<?php
declare(strict_types=1);

class FreshExtension_auth_Controller extends FreshRSS_auth_Controller {
	/**
	 * @throws Minz_ConfigurationException
	 */
	private static function redirectFormLogin(): void {
		if (FreshRSS_Auth::hasAccess()) {
			Minz_Request::forward(['c' => 'index', 'a' => 'index'], true);
			return;
		}
		Minz_Request::forward(['c' => 'auth', 'a' => 'formLogin']);
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_ConfigurationNamespaceException
	 * @throws Minz_ConfigurationException
	 * @throws Minz_PermissionDeniedException
	 */
	#[\Override]
	public function loginAction(): void {
		if (FreshRSS_Context::systemConf()->auth_type !== 'form') {
			parent::loginAction();
			return;
		}

		$username = Minz_Request::paramString('u');
		$password = Minz_Request::paramString('p', plaintext: true);

		if ($username === '' || $password === '') {
			self::redirectFormLogin();
			return;
		}

		if (!FreshRSS_user_Controller::checkUsername($username) || !FreshRSS_user_Controller::userExists($username)) {
			Minz_Request::bad(
				_t('feedback.auth.login.invalid'),
				['c' => 'index', 'a' => 'index']
			);
			return;
		}

		$config = FreshRSS_UserConfiguration::getForUser($username);

		$s = $config->passwordHash ?? '';
		$ok = password_verify($password, $s);

		if ($ok) {
			FreshRSS_Context::initUser($username);
			FreshRSS_FormAuth::deleteCookie();
			Minz_Session::regenerateID('FreshRSS');
			Minz_Session::_params([
				Minz_User::CURRENT_USER => $username,
				'passwordHash' => $s,
				'lastReauth' => false,
				'csrf' => false,
			]);
			FreshRSS_Auth::giveAccess();

			Minz_Translate::init(FreshRSS_Context::userConf()->language);

			FreshRSS_UserDAO::touch();

			Minz_Request::good(_t('feedback.auth.login.success'), ['c' => 'index', 'a' => 'index']);
			return;
		}

		Minz_Log::warning('Unsafe password mismatch for user ' . $username, USERS_PATH . '/' . $username . '/log.txt');
		Minz_Request::bad(
			_t('feedback.auth.login.invalid'),
			['c' => 'index', 'a' => 'index']
		);
	}
}
