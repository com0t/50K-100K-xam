<?php

namespace FernleafSystems\Wordpress\Services\Core;

use FernleafSystems\Wordpress\Services\Core\Upgrades;
use FernleafSystems\Wordpress\Services\Core\VOs\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme\Api;

/**
 * Class Themes
 * @package FernleafSystems\Wordpress\Services\Core
 */
class Themes {

	/**
	 * @var WpThemeVo[]
	 */
	private $aLoadedVOs;

	/**
	 * @param string $stylesheet
	 * @return bool
	 */
	public function activate( $stylesheet ) {
		if ( empty( $stylesheet ) ) {
			return false;
		}

		$oTheme = $this->getTheme( $stylesheet );
		if ( !$oTheme->exists() ) {
			return false;
		}

		switch_theme( $oTheme->get_stylesheet() );

		// Now test currently active theme
		$oCurrentTheme = $this->getCurrent();

		return ( $stylesheet == $oCurrentTheme->get_stylesheet() );
	}

	/**
	 * @param string $stylesheet
	 * @return bool|\WP_Error
	 */
	public function delete( $stylesheet ) {
		if ( empty( $stylesheet ) ) {
			return false;
		}
		if ( !function_exists( 'delete_theme' ) ) {
			require_once( ABSPATH.'wp-admin/includes/theme.php' );
		}
		return function_exists( 'delete_theme' ) ? delete_theme( $stylesheet ) : false;
	}

	/**
	 * @param string $slug
	 * @return bool
	 */
	public function installFromWpOrg( $slug ) {
		$success = false;
		try {
			$theme = ( new Api() )
				->setWorkingSlug( $slug )
				->getInfo();
			if ( !empty( $theme->download_link ) ) {
				$success = $this->install( $theme->download_link, true )[ 'successful' ];
			}
		}
		catch ( \Exception $e ) {
		}
		return $success;
	}

	/**
	 * @param string $sUrlToInstall
	 * @param bool   $bOverwrite
	 * @return array
	 */
	public function install( $sUrlToInstall, $bOverwrite = true ) {

		$oSkin = Services::WpGeneral()->getWordpressIsAtLeastVersion( '5.3' ) ?
			new Upgrades\UpgraderSkin()
			: new Upgrades\UpgraderSkinLegacy();
		$oUpgrader = new \Theme_Upgrader( $oSkin );
		add_filter( 'upgrader_package_options', function ( $aOptions ) use ( $bOverwrite ) {
			$aOptions[ 'clear_destination' ] = $bOverwrite;
			return $aOptions;
		} );

		$mResult = $oUpgrader->install( $sUrlToInstall );

		return [
			'successful' => $mResult === true,
			'feedback'   => $oSkin->getIcwpFeedback(),
			'theme_info' => $oUpgrader->theme_info(),
			'errors'     => is_wp_error( $mResult ) ? $mResult->get_error_messages() : [ 'no errors' ]
		];
	}

	/**
	 * @param string $slug
	 * @param bool   $bUseBackup
	 * @return bool
	 */
	public function reinstall( $slug, $bUseBackup = false ) {
		$bSuccess = false;

		if ( $this->isInstalled( $slug ) ) {
			$FS = Services::WpFs();

			$oTheme = $this->getTheme( $slug );

			$sDir = $oTheme->get_stylesheet_directory();
			$sBackupDir = dirname( $sDir ).'/../../'.$slug.'bak'.time();
			if ( $bUseBackup ) {
				rename( $sDir, $sBackupDir );
			}

			$bSuccess = $this->installFromWpOrg( $slug );
			if ( $bSuccess ) {
				wp_update_themes(); //refreshes our update information
				if ( $bUseBackup ) {
					$FS->deleteDir( $sBackupDir );
				}
			}
			elseif ( $bUseBackup ) {
				$FS->deleteDir( $sDir );
				rename( $sBackupDir, $sDir );
			}
		}
		return $bSuccess;
	}

	/**
	 * @param string $file
	 * @return array
	 */
	public function update( $file ) :array {
		require_once( ABSPATH.'wp-admin/includes/upgrade.php' );
		require_once( ABSPATH.'wp-admin/includes/class-wp-upgrader.php' );

		$oSkin = new \Automatic_Upgrader_Skin();
		$mResult = ( new \Theme_Upgrader( $oSkin ) )->upgrade( $file );

		return [
			'successful' => $mResult === true,
			'feedback'   => $oSkin->get_upgrade_messages(),
			'errors'     => is_wp_error( $mResult ) ? $mResult->get_error_messages() : [ 'no errors' ]
		];
	}

	/**
	 * @return false|string
	 */
	public function getCurrentThemeName() {
		return $this->getCurrent()->get( 'Name' );
	}

	/**
	 * @return null|\WP_Theme
	 */
	public function getCurrent() {
		return $this->getTheme();
	}

	public function getExists( string $stylesheet ) :bool {
		return $this->getTheme( $stylesheet )->exists();
	}

	/**
	 * @param string $slug - the folder name of the theme
	 * @return string
	 */
	public function getInstallationDir( $slug ) {
		return wp_normalize_path( $this->getTheme( $slug )->get_stylesheet_directory() );
	}

	/**
	 * Supports only WP > 3.4.0
	 * @param string $sStylesheet
	 * @return null|\WP_Theme
	 */
	public function getTheme( $sStylesheet = null ) {
		require_once( ABSPATH.'wp-admin/includes/theme.php' );
		return function_exists( 'wp_get_theme' ) ? wp_get_theme( $sStylesheet ) : null;
	}

	/**
	 * @param string $stylesheet
	 * @param bool   $bReload
	 * @return WpThemeVo|null
	 */
	public function getThemeAsVo( $stylesheet, $bReload = false ) {
		try {
			if ( !is_array( $this->aLoadedVOs ) ) {
				$this->aLoadedVOs = [];
			}
			if ( $bReload || !isset( $this->aLoadedVOs[ $stylesheet ] ) ) {
				$this->aLoadedVOs[ $stylesheet ] = new WpThemeVo( $stylesheet );
			}
			$asset = $this->aLoadedVOs[ $stylesheet ];
		}
		catch ( \Exception $e ) {
			$asset = null;
		}
		return $asset;
	}

	/**
	 * @return WpThemeVo[]
	 */
	public function getThemesAsVo() {
		return array_filter(
			array_map(
				function ( $stylesheet ) {
					return $this->getThemeAsVo( $stylesheet );
				},
				$this->getInstalledStylesheets()
			)
		);
	}

	/**
	 * @return string[]
	 */
	public function getInstalledStylesheets() :array {
		return array_map(
			function ( $theme ) {
				return $theme->get_stylesheet();
			},
			$this->getThemes()
		);
	}

	/**
	 * Supports only WP > 3.4.0
	 * Abstracts the WordPress wp_get_themes()
	 * @return \WP_Theme[]
	 */
	public function getThemes() :array {
		require_once( ABSPATH.'wp-admin/includes/theme.php' );
		return function_exists( 'wp_get_themes' ) ? wp_get_themes() : [];
	}

	/**
	 * @param string $slug
	 * @return array|null
	 */
	public function getUpdateInfo( $slug ) {
		$updates = $this->getUpdates();
		return $updates[ $slug ] ?? null;
	}

	/**
	 * @param bool $bForceUpdateCheck
	 * @return array
	 */
	public function getUpdates( $bForceUpdateCheck = false ) {
		if ( $bForceUpdateCheck ) {
			$this->clearUpdates();
			$this->checkForUpdates();
		}
		$aUpdates = Services::WpGeneral()->getWordpressUpdates( 'themes' );
		return is_array( $aUpdates ) ? $aUpdates : [];
	}

	/**
	 * @return null|\WP_Theme
	 */
	public function getCurrentParent() {
		return $this->isActiveThemeAChild() ? $this->getTheme( get_template() ) : null;
	}

	/**
	 * @return array[] - keys are theme stylesheets
	 */
	public function getAllExtendedData() {
		$data = Services::WpGeneral()->getTransient( 'update_themes' );
		return array_merge(
			$data->no_update ?? [],
			$data->response ?? []
		);
	}

	/**
	 * @param string $slug
	 * @return array
	 */
	public function getExtendedData( $slug ) {
		$data = $this->getAllExtendedData();
		return $data[ $slug ] ?? [];
	}

	/**
	 * @param string $sSlug
	 * @param bool   $bCheckIsActiveParent
	 * @return bool
	 */
	public function isActive( $sSlug, $bCheckIsActiveParent = false ) :bool {
		return ( $this->isInstalled( $sSlug ) && $this->getCurrent()->get_stylesheet() == $sSlug )
			   || ( $bCheckIsActiveParent && $this->isActiveParent( $sSlug ) );
	}

	public function isActiveThemeAChild() :bool {
		$current = $this->getCurrent();
		return $current->get_stylesheet() !== $current->get_template();
	}

	public function isActiveParent( string $slug ) :bool {
		return $this->isInstalled( $slug ) && $this->getCurrent()->get_template() == $slug;
	}

	public function isInstalled( string $slug ) :bool {
		return !empty( $slug ) && $this->getExists( $slug );
	}

	public function isUpdateAvailable( string $slug ) :bool {
		return !is_null( $this->getUpdateInfo( $slug ) );
	}

	public function isWpOrg( string $stylesheet ) :bool {
		return $this->getThemeAsVo( $stylesheet )->isWpOrg();
	}

	/**
	 * @return bool|null
	 */
	protected function checkForUpdates() {

		if ( class_exists( 'WPRC_Installer' ) && method_exists( 'WPRC_Installer', 'wprc_update_themes' ) ) {
			\WPRC_Installer::wprc_update_themes();
			return true;
		}
		elseif ( function_exists( 'wp_update_themes' ) ) {
			return ( wp_update_themes() !== false );
		}
		return null;
	}

	/**
	 */
	protected function clearUpdates() {
		$sKey = 'update_themes';
		$oResponse = Services::WpGeneral()->getTransient( $sKey );
		if ( !is_object( $oResponse ) ) {
			$oResponse = new \stdClass();
		}
		$oResponse->last_checked = 0;
		Services::WpGeneral()->setTransient( $sKey, $oResponse );
	}

	/**
	 * @return array
	 */
	public function wpmsGetSiteAllowedThemes() {
		return ( function_exists( 'get_site_allowed_themes' ) ? get_site_allowed_themes() : [] );
	}
}