<?php

return [
	'share' => [
		'feedback' => [
			'failed' => 'Die Mail konnte nicht gesendet werden. Bitte kontaktiere deinen Administrator.',
			'fields_required' => 'Alle Felder sind Pflichtfelder.',
			'sent' => 'Die Mail wurde gesendet.',
		],
		'form' => [
			'cancel' => 'Abbrechen',
			'content' => 'Inhalt',
			'content_default' => "Hi,\n\nIch glaube dieser Artikel ist interessant für dich!\n\n%s – %s\n\n---\n\nDiese Mail wurde von %s gesendet über %s ( %s )",
			'send' => 'Senden',
			'subject' => 'Betreff',
			'subject_default' => 'Interessanter Artikel für dich!',
			'to' => 'An',
		],
		'intro' => 'Diesen Artikel per Mail versenden: “<strong>%s</strong>”',
		'title' => 'Einen Artikel per Mail teilen.',
		'manage' => [
			'mailer' => 'E-Mail-Versand',
			'mail' => 'via PHP',
			'smtp' => 'via SMTP (versendet von %s)',
			'error' => 'Fehler',
			'help' => 'Versand zwischen PHP und SMTP in <kbd>config.php</kbd> wechseln: siehe <a href="https://freshrss.github.io/FreshRSS/en/admins/05_Configuring_email_validation.html#configure-the-smtp-server" target="_blank">Dokumentation</a>',
		]
	],
];
