<?php

declare(strict_types=1);

final class ShareByEmailExtension extends Minz_Extension {

	/**
	 * @throws Minz_ConfigurationException
	 */
	#[\Override]
	public function init(): void {
		$this->registerTranslates();

		$this->registerController('shareByEmail');
		$this->registerViews();

		FreshRSS_Share::register([
			'type' => 'email',
			'url' => Minz_Url::display(['c' => 'shareByEmail', 'a' => 'share']) . '&amp;id=~ID~',
			'transform' => [],
			'form' => 'simple',
			'method' => 'GET',
		]);

		spl_autoload_register(array($this, 'loader'));
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (Minz_Request::isPost()) {
			$defaultRecipient = Minz_Request::paramString('share_by_email_default_recipient', plaintext: true);
			if ($defaultRecipient !== '' && !self::isValidEmailAddress($defaultRecipient)) {
				Minz_Request::bad(_t('user.email.feedback.invalid'), [
					'c' => 'extension',
					'a' => 'configure',
					'params' => [
						'e' => $this->getName(),
					],
				]);
				return;
			}
			FreshRSS_Context::userConf()->_attribute('share_by_email_default_recipient', $defaultRecipient);
			FreshRSS_Context::userConf()->save();
		}
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 */
	public function getDefaultRecipient(): string {
		return FreshRSS_Context::userConf()->attributeString('share_by_email_default_recipient') ?? '';
	}

	private static function isValidEmailAddress(string $email): bool {
		$mailer = new PHPMailer\PHPMailer\PHPMailer();
		$mailer->CharSet = 'utf-8';
		$punyemail = $mailer->punyencodeAddress($email);
		return PHPMailer\PHPMailer\PHPMailer::validateAddress($punyemail, 'html5');
	}

	public function loader(string $class_name): void {
		if (strpos($class_name, 'ShareByEmail') === 0) {
			$class_name = substr($class_name, 13);
			$base_path = $this->getPath() . '/';
			include($base_path . str_replace('\\', '/', $class_name) . '.php');
		}
	}
}
