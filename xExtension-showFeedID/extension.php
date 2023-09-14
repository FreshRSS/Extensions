<?php
class ShowFeedIdExtension extends Minz_Extension {
	public function init() {
		Minz_View::appendScript($this->getFileUrl('showfeedid.js', 'js'));
	}
}
