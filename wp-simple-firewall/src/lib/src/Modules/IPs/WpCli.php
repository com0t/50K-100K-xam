<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class WpCli extends Base\WpCli {

	/**
	 * @inheritDoc
	 */
	protected function getCmdHandlers() :array {
		return [
			new IPs\WpCli\Add(),
			new IPs\WpCli\Remove(),
			new IPs\WpCli\Enumerate(),
		];
	}
}