<?php

declare(strict_types=1);

namespace ShareByEmail\mailers;

final class Share extends \Minz_Mailer {

	/** @var View */
	protected $view;

	public function __construct() {
		parent::__construct(View::class);
	}

	public function send_article(string $to, string $subject, string $content): bool {
		$this->view->_path('share_mailer/article.txt.php');

		$this->view->content = $content;

		if (isset(\FreshRSS_Context::$system_conf)) {
			$subject_prefix = '[' . \FreshRSS_Context::$system_conf->title . ']';
		} else {
			$subject_prefix = '';
		}
		return $this->mail($to, $subject_prefix . ' ' . $subject);
	}
}
