<?php

namespace ShareByEmail\mailers;

class Share extends \Minz_Mailer {
	public function send_article($to, $subject, $content) {
		$this->view->_path('share_mailer/article.txt');

		$this->view->content = $content;

		$subject_prefix = '[' . \FreshRSS_Context::$system_conf->title . ']';
		return $this->mail($to, $subject_prefix . ' ' . $subject);
	}
}
