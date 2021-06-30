<?php

namespace FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless;

/**
 * Class Ping
 * @package FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless
 */
class Ping extends Base {

	const API_ACTION = 'ping';

	/**
	 * @return bool
	 */
	public function ping() {
		$pong = '';

		$raw = $this->sendReq();
		if ( is_array( $raw ) && !empty( $raw[ 'keyless' ] ) && !empty( $raw[ 'keyless' ][ self::API_ACTION ] ) ) {
			$pong = $raw[ 'keyless' ][ self::API_ACTION ];
		}

		return $pong === 'pong';
	}
}