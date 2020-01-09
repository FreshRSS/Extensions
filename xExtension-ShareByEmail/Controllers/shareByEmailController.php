<?php

class FreshExtension_shareByEmail_Controller extends Minz_ActionController {
	public function init() {
		$this->extension = Minz_ExtensionManager::findExtension('Share By Email');
	}

	public function shareAction() {
		if (!FreshRSS_Auth::hasAccess()) {
			Minz_Error::error(403);
		}

		$id = Minz_Request::param('id', null);
		if (null === $id) {
			Minz_Error::error(404);
		}

		$entryDAO = FreshRSS_Factory::createEntryDao();
		$entry = $entryDAO->searchById($id);
		if (null === $entry) {
			Minz_Error::error(404);
		}

		$username = Minz_Session::param('currentUser', '_');
		$service_name = FreshRSS_Context::$system_conf->title;
		$service_url = FreshRSS_Context::$system_conf->base_url;

		Minz_View::prependTitle(_t('shareByEmail.share.title') . ' · ');
		Minz_View::appendStyle($this->extension->getFileUrl('shareByEmail.css', 'css'));
		$this->view->_layout('simple');
		$this->view->entry = $entry;
		$this->view->to = '';
		$this->view->subject = _t('shareByEmail.share.form.subject_default');
		$this->view->content = _t(
			'shareByEmail.share.form.content_default',
			$entry->title(),
			$entry->link(),
			$username,
			$service_name,
			$service_url
		);

		if (Minz_Request::isPost()) {
			$this->view->to = $to = Minz_Request::param('to', '');
			$this->view->subject = $subject = Minz_Request::param('subject', '');
			$this->view->content = $content = Minz_Request::param('content', '');

			if ($to == "" || $subject == "" || $content == "") {
				Minz_Request::bad(_t('shareByEmail.share.feedback.fields_required'), [
					'c' => 'shareByEmail',
					'a' => 'share',
					'params' => [
						'id' => $id,
					],
				]);
			}

			$mailer = new \ShareByEmail\mailers\Share();
			$sent = $mailer->send_article($to, $subject, $content);

			if ($sent) {
				Minz_Request::good(_t('shareByEmail.share.feedback.sent'), [
					'c' => 'index',
					'a' => 'index',
				]);
			} else {
				Minz_Request::bad(_t('shareByEmail.share.feedback.failed'), [
					'c' => 'shareByEmail',
					'a' => 'share',
					'params' => [
						'id' => $id,
					],
				]);
			}
		}
	}
}
