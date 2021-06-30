<?php

namespace FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify;

/**
 * Class Email
 * @package FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify
 */
class Email extends Base {

	/**
	 * @param string $email
	 * @return array|null
	 */
	public function getEmailVerification( string $email ) {
		$req = $this->getRequestVO();
		$req->action = 'email';
		$req->address = $email;
		return $this->query();
	}

	protected function getApiUrl() :string {
		$data = array_map( 'rawurlencode', array_filter( array_merge(
			[
				'action'  => false,
				'address' => false,
			],
			$this->getRequestVO()->getRawDataAsArray()
		) ) );
		return sprintf( '%s/%s', parent::getApiUrl(), implode( '/', $data ) );
	}
}