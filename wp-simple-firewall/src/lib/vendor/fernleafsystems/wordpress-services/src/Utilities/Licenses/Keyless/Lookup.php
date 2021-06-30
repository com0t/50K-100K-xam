<?php

namespace FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Licenses\EddLicenseVO;

/**
 * Class Lookup
 * @package FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless
 * @property int    $item_id
 * @property string $install_id
 * @property string $url
 * @property string $nonce
 * @property array  $meta
 */
class Lookup extends Base {

	const API_ACTION = 'lookup';

	public function lookup() :EddLicenseVO {
		if ( empty( $this->url ) ) {
			$this->url = Services::WpGeneral()->getHomeUrl( '', true );
		}

		$raw = $this->sendReq();
		if ( is_array( $raw ) && !empty( $raw[ 'keyless' ] ) && !empty( $raw[ 'keyless' ][ 'license' ] ) ) {
			$info = $raw[ 'keyless' ][ 'license' ];
		}
		else {
			$info = [];
		}

		$lic = ( new EddLicenseVO() )->applyFromArray( $info );
		$lic->last_request_at = Services::Request()->ts();
		return $lic;
	}

	protected function getApiRequestUrl() :string {
		return sprintf( '%s/%s/%s', parent::getApiRequestUrl(), $this->item_id, $this->install_id );
	}

	/**
	 * @return string[]
	 */
	protected function getRequestBodyParamKeys() :array {
		return [
			'url',
			'nonce',
			'meta',
		];
	}
}