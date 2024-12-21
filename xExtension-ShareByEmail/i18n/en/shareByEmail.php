<?php

return [
	'share' => [
		'feedback' => [
			'failed' => 'The email cannot be sent, please contact your administrator.',
			'fields_required' => 'All the fields are required.',
			'sent' => 'The email has been sent.',
		],
		'form' => [
			'cancel' => 'Cancel',
			'content' => 'Content',
			'content_default' => "Hi,\n\nYou might find this article quite interesting!\n\n%s – %s\n\n---\n\nThis email has been sent by %s via %s ( %s )",
			'send' => 'Send',
			'subject' => 'Subject',
			'subject_default' => 'I found this article interesting!',
			'to' => 'To',
		],
		'intro' => 'You are about to share this article by email: “<strong>%s</strong>”',
		'title' => 'Share an article by email',
		'manage' => [
			'mailer' => 'Mailing system',
			'mail' => 'PHP <code>mail()</code>',
			'smtp' => 'SMTP (send from %s)',
			'error' => 'Error',
			'help' => 'Switch PHP <code>mail()</code>/SMTP connection in <kbd>config.php</kbd>: see <a href="https://freshrss.github.io/FreshRSS/en/admins/05_Configuring_email_validation.html#configure-the-smtp-server" target="_blank">documentation</a>'
		],
	],
];
