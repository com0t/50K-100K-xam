<?php

namespace FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Services;

/**
 * Class IPs
 * @package FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Services
 */
class IPs extends Base {

	const RESPONSE_DATA_KEY = 'service_ips';

	/**
	 * @return string[][][]|null
	 */
	public function getIPs() {
		return $this->query();
	}

	protected function getApiUrl() :string {
		return parent::getApiUrl().'/ips';
	}
}