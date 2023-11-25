<?php

declare(strict_types=1);

namespace ShareByEmail\mailers;

class View extends \Minz_View {

	public ?\FreshRSS_Entry $entry = null;
	public string $content = '';
	public string $subject = '';
	public string $to = '';
}
