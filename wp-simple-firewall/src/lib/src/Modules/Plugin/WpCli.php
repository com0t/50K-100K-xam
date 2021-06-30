<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

class WpCli extends Base\WpCli {

	/**
	 * @inheritDoc
	 */
	protected function getCmdHandlers() :array {
		return [
			new Plugin\WpCli\ForceOff(),
			new Plugin\WpCli\Reset(),
			new Plugin\WpCli\Export(),
			new Plugin\WpCli\Import(),
		];
	}
}