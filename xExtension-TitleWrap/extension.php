<?php

declare(strict_types=1);

final class TitleWrapExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		if (version_compare(FRESHRSS_VERSION, "1.23.1") > 0) {
			Minz_View::appendStyle($this->getFileUrl('title_wrap.css'));
			if (version_compare(FRESHRSS_VERSION, '1.30.0') >= 0) {
				// The article row uses a CSS grid layout since FreshRSS 1.30.0
				Minz_View::appendStyle($this->getFileUrl('title_wrap_grid.css'));
			}
		} else {
			// legacy <1.24.0 (= 1.23.2-dev)
			Minz_View::appendStyle($this->getFileUrl('title_wrap_legacy.css'));
		}
	}
}
