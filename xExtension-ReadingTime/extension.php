<?php
class ReadingTimeExtension extends Minz_Extension {
	public function init() {
		Minz_View::appendScript($this->getFileUrl('readingtime.js', 'js'));
	}
}
