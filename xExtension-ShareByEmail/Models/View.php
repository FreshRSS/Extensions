<?php

declare(strict_types=1);

namespace ShareByEmail\Models;

final class View extends \FreshRSS_View {

	public ?\FreshRSS_Entry $entry = null;
	public string $content = '';
	public string $subject = '';
	public string $to = '';
}
