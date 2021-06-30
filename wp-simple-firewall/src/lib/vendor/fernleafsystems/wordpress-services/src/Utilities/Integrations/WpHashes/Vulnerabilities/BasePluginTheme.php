<?php

namespace FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Vulnerabilities;

/**
 * Class BasePluginTheme
 * @package FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Vulnerabilities
 */
abstract class BasePluginTheme extends Base {

	/**
	 * @param string $slug
	 * @param string $version
	 * @return array[]|null
	 */
	public function getVulnerabilities( $slug, $version ) {
		$req = $this->getRequestVO();
		$req->slug = trim( trim( strtolower( $slug ), '-_.' ) );
		$req->version = $version;
		return $this->query();
	}

	protected function getApiUrl() :string {
		$req = $this->getRequestVO();
		return sprintf( '%s/%s/%s', parent::getApiUrl(), $req->slug, $req->version );
	}
}