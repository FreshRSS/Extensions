<?php

declare(strict_types=1);

final class TitleWrapExtension extends Minz_Extension {
	#[Override]
	public function init(): void {
		Minz_View::appendStyle($this->getFileUrl('title_wrap.css', 'css'));
	}
}
