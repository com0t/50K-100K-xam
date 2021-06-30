<?php

namespace FernleafSystems\Wordpress\Services\Utilities\Encrypt;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * Class EncryptVo
 * @package FernleafSystems\Wordpress\Services\Utilities\Encrypt
 * @property bool   $success
 * @property int    $result
 * @property string $message
 * @property bool   $json_encoded
 * @property string $sealed_data
 * @property string $sealed_password
 */
class OpenSslEncryptVo extends DynPropertiesClass {

	/**
	 * @inheritDoc
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'sealed_data':
			case 'sealed_password':
				$value = base64_decode( $value );
				break;

			default:
				break;
		}

		return $value;
	}

	/**
	 * @inheritDoc
	 */
	public function __set( string $key, $value ) {

		switch ( $key ) {

			case 'sealed_data':
			case 'sealed_password':
				$value = base64_encode( $value );
				break;

			default:
				break;
		}

		parent::__set( $key, $value );
	}
}