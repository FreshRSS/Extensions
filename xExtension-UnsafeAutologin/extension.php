<?php
declare(strict_types=1);

final class UnsafeAutologinExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		$this->registerController('auth');
	}
}
