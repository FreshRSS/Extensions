<?php

class ColorfulListExtension extends Minz_Extension {

    public function init():void {
        Minz_View::appendScript($this->getFileUrl('script.js', 'js'),'','','');       
    }
}
