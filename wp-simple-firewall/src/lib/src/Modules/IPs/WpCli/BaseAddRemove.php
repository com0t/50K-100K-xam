<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;

class BaseAddRemove extends BaseWpCliCmd {

	/**
	 * @return array[]
	 */
	protected function getCommonIpCmdArgs() :array {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'ip',
				'optional'    => false,
				'description' => 'The IP address.',
			],
			[
				'type'        => 'assoc',
				'name'        => 'list',
				'optional'    => false,
				'options'     => [
					'bypass',
					'block',
					'white',
					'black',
				],
				'description' => 'The IP list to update.',
			],
		];
	}
}