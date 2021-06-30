<?php

namespace FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme\VOs\ThemeInfoVO;

/**
 * Class Api
 * @package FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme
 * @property array $fields
 */
class Api {

	use Base;
	use DynProperties;

	/**
	 * @return ThemeInfoVO
	 * @throws \Exception
	 */
	public function getInfo() {
		return $this->run( 'theme_information' );
	}

	/**
	 * @return ThemeInfoVO
	 * @throws \Exception
	 * @deprecated 0.1.19
	 */
	public function getThemeInfo() {
		return $this->getInfo();
	}

	/**
	 * @param string $sCmd
	 * @return ThemeInfoVO
	 * @throws \Exception
	 */
	public function run( $sCmd ) {
		include_once( ABSPATH.'wp-admin/includes/theme.php' );

		$params = $this->getRawData();
		$params[ 'slug' ] = $this->getWorkingSlug();

		$response = \themes_api( $sCmd,
			Services::DataManipulation()->mergeArraysRecursive( $this->defaultParams(), $params ) );

		if ( \is_wp_error( $response ) ) {
			throw new \Exception( sprintf( '[ThemesApi Error] %s', $response->get_error_message() ) );
		}
		elseif ( !\is_object( $response ) ) {
			throw new \Exception( sprintf( '[ThemesApi Error] %s', 'Did not return an expected Object' ) );
		}

		return ( new ThemeInfoVO() )->applyFromArray( (array)$response );
	}

	/**
	 * @return array[]
	 */
	protected function defaultParams() {
		return [
			'fields' => [
				'rating'   => true,
				'ratings'  => true,
				'versions' => true,
			]
		];
	}
}