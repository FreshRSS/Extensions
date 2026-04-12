<?php
declare(strict_types=1);

final class UnsafeAutologinExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		$this->registerHook(Minz_HookType::ActionExecute, [$this, 'handleLogin']);
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_ConfigurationNamespaceException
	 * @throws Minz_ConfigurationException
	 * @throws Minz_PermissionDeniedException
	 */
	public function handleLogin(): bool {
		if (!Minz_Request::is('auth', 'login') || FreshRSS_Context::systemConf()->auth_type !== 'form') {
			return true;
		}

		$username = Minz_Request::paramString('u');
		$password = Minz_Request::paramString('p', plaintext: true);

		if ($username === '' || $password === '') {
			return true;
		}

		if (!FreshRSS_user_Controller::checkUsername($username) || !FreshRSS_user_Controller::userExists($username)) {
			Minz_Request::bad(
				_t('feedback.auth.login.invalid'),
				['c' => 'index', 'a' => 'index']
			);
			return false;
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
			return false;
		}

		Minz_Log::warning('Unsafe password mismatch for user ' . $username, USERS_PATH . '/' . $username . '/log.txt');
		Minz_Request::bad(
			_t('feedback.auth.login.invalid'),
			['c' => 'index', 'a' => 'index']
		);
		return false;
	}
}
