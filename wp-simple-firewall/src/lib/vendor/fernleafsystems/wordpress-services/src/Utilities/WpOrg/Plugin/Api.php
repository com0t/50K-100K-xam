<?php

namespace FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\VOs\PluginInfoVO;

/**
 * Class Api
 * @package FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin
 * @property array $fields
 */
class Api {

	use Base;
	use DynProperties;

	/**
	 * @return PluginInfoVO
	 * @throws \Exception
	 */
	public function getInfo() {
		return $this->run( 'plugin_information' );
	}

	/**
	 * @return PluginInfoVO
	 * @throws \Exception
	 * @deprecated 0.1.19
	 */
	public function getPluginInfo() {
		return $this->getInfo();
	}

	/**
	 * @param string $cmd
	 * @return PluginInfoVO
	 * @throws \Exception
	 */
	public function run( $cmd ) {
		include_once( ABSPATH.'wp-admin/includes/plugin-install.php' );

		$params = $this->getRawData();
		$params[ 'slug' ] = $this->getWorkingSlug();

		$response = \plugins_api( $cmd, $params );

		if ( \is_wp_error( $response ) ) {
			throw new \Exception( sprintf( '[PluginsApi Error] %s', $response->get_error_message() ) );
		}
		elseif ( !\is_object( $response ) ) {
			throw new \Exception( sprintf( '[PluginsApi Error] %s', 'Did not return an expected Object' ) );
		}

		return ( new PluginInfoVO() )->applyFromArray( (array)$response );
	}
}