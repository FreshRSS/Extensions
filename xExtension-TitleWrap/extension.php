<?php

declare(strict_types=1);

class TitleWrapExtension extends Minz_Extension {
	public function init() {
                Minz_View::appendStyle($this->getFileUrl('title_wrap.css', 'css'));
	}
}
