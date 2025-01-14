<?php

return [
	'share' => [
		'feedback' => [
			'failed' => 'E-posta gönderilemiyor, lütfen yöneticinizle iletişime geçin.',
			'fields_required' => 'Tüm alanların doldurulması zorunludur.',
			'sent' => 'E-posta gönderildi.',
		],
		'form' => [
			'cancel' => 'İptal',
			'content' => 'İçerik',
			'content_default' => "Merhaba,\n\nBu makaleyi oldukça ilginç bulabilirsiniz!\n\n%s – %s\n\n---\n\nBu e-posta %s tarafından %s ( %s ) aracılığıyla gönderildi",
			'send' => 'Gönder',
			'subject' => 'Konu',
			'subject_default' => 'Bu makaleyi ilginç buldum!',
			'to' => 'Kime',
		],
		'intro' => 'Bu makaleyi e-posta yoluyla paylaşmak üzeresiniz: “<strong>%s</strong>”',
		'title' => 'Bir makaleyi e-posta ile paylaşın',
		'manage' => [
			'mailer' => 'Mail sistemi',
			'mail' => 'PHP <code>mail()</code>',
			'smtp' => 'SMTP (%s kaynağından gönder)',
			'error' => 'Hata',
			'help' => '<kbd>config.php</kbd> dosyasındaki PHP <code>mail()</code>/SMTP bağlantısını değiştirin: <a href="https://freshrss.github.io/FreshRSS/en/admins/05_Configuring_email_validation.html#configure-the-smtp-server" target="_blank">belgelere</a> bakın',
		]
	],
];
