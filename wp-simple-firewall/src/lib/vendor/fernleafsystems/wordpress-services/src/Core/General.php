<?php

namespace FernleafSystems\Wordpress\Services\Core;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Hashes;
use FernleafSystems\Wordpress\Services\Utilities\Options\TestCanUseTransients;

class General {

	/**
	 * @var string
	 */
	protected $sWpVersion;

	public function canUseTransients() :bool {
		return ( new TestCanUseTransients() )->run();
	}

	/**
	 * @return null|string
	 */
	public function findWpLoad() {
		return $this->findWpCoreFile( 'wp-load.php' );
	}

	/**
	 * @param $sFilename
	 * @return null|string
	 */
	public function findWpCoreFile( $sFilename ) {
		$sLoaderPath = __DIR__;
		$nLimiter = 0;
		$nMaxLimit = count( explode( DIRECTORY_SEPARATOR, trim( $sLoaderPath, DIRECTORY_SEPARATOR ) ) );
		$bFound = false;

		do {
			if ( @is_file( $sLoaderPath.DIRECTORY_SEPARATOR.$sFilename ) ) {
				$bFound = true;
				break;
			}
			$sLoaderPath = realpath( $sLoaderPath.DIRECTORY_SEPARATOR.'..' );
			$nLimiter++;
		} while ( $nLimiter < $nMaxLimit );

		return $bFound ? $sLoaderPath.DIRECTORY_SEPARATOR.$sFilename : null;
	}

	/**
	 * @param string $sRedirect
	 * @return bool
	 */
	public function doForceRunAutomaticUpdates( $sRedirect = '' ) {

		$lock_name = 'auto_updater.lock'; //ref: /wp-admin/includes/class-wp-upgrader.php
		delete_option( $lock_name );
		if ( !defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true ); // this prevents WP from disabling plugins pre-upgrade
		}

		// does the actual updating
		wp_maybe_auto_update();

		if ( !empty( $sRedirect ) ) {
			Services::Response()->redirect( network_admin_url( $sRedirect ) );
		}
		return true;
	}

	/**
	 * @param \stdClass|string $mItem
	 * @param string           $sContext from plugin|theme
	 * @return string
	 */
	public function getFileFromAutomaticUpdateItem( $mItem, $sContext = 'plugin' ) {
		if ( is_object( $mItem ) && isset( $mItem->{$sContext} ) ) { // WP 3.8.2+
			$mItem = $mItem->{$sContext};
		}
		elseif ( !is_string( $mItem ) ) { // WP pre-3.8.2
			$mItem = '';
		}
		return $mItem;
	}

	public function isRunningAutomaticUpdates() :bool {
		return get_option( 'auto_updater.lock' ) ? true : false;
	}

	public function isDebug() :bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Clears any WordPress caches
	 */
	public function doBustCache() {
		global $_wp_using_ext_object_cache, $wp_object_cache;
		$_wp_using_ext_object_cache = false;
		if ( !empty( $wp_object_cache ) ) {
			@$wp_object_cache->flush();
		}
	}

	/**
	 * @return array
	 * @see wp_redirect_admin_locations()
	 */
	public function getAutoRedirectLocations() :array {
		return [ 'wp-admin', 'dashboard', 'admin', 'login', 'wp-login.php' ];
	}

	/**
	 * @return string[]
	 */
	public function getCoreChecksums() :array {
		return $this->isClassicPress() ? $this->getCoreChecksums_CP() : $this->getCoreChecksums_WP();
	}

	/**
	 * @return string[]
	 */
	private function getCoreChecksums_CP() :array {
		$data = ( new Hashes\ClassicPress() )->getCurrent();
		return is_array( $data ) ? $data : [];
	}

	/**
	 * @return string[]
	 */
	private function getCoreChecksums_WP() :array {
		include_once( ABSPATH.'/wp-admin/includes/update.php' );
		if ( function_exists( 'get_core_checksums' ) ) { // if it's loaded, we use it.
			$data = get_core_checksums( $this->getVersion(), $this->getLocaleForChecksums() );
		}
		else {
			$data = ( new Hashes\WordPress() )->getCurrent();
		}
		return is_array( $data ) ? $data : [];
	}

	public function getAdminUrl( string $path = '', bool $wpmsOnly = false ) :string {
		return $wpmsOnly ? network_admin_url( $path ) : admin_url( $path );
	}

	public function getAdminUrl_Plugins( bool $wpmsOnly = false ) :string {
		return $this->getAdminUrl( 'plugins.php', $wpmsOnly );
	}

	public function getAdminUrl_Settings( bool $wpmsOnly = false ) :string {
		return $this->getAdminUrl( 'options-general.php', $wpmsOnly );
	}

	public function getAdminUrl_Themes( bool $bWpmsOnly = false ) :string {
		return $this->getAdminUrl( 'themes.php', $bWpmsOnly );
	}

	public function getAdminUrl_Updates( bool $bWpmsOnly = false ) :string {
		return $this->getAdminUrl( 'update-core.php', $bWpmsOnly );
	}

	public function getHomeUrl( string $path = '', bool $wpms = false ) :string {
		$url = $wpms ? network_home_url( $path ) : home_url( $path );
		if ( empty( $url ) ) {
			remove_all_filters( $wpms ? 'network_home_url' : 'home_url' );
			$url = $wpms ? network_home_url( $path ) : home_url( $path );
		}
		return $url;
	}

	public function getWpUrl( string $path = '' ) :string {
		$url = network_site_url( $path );
		if ( empty( $url ) ) {
			remove_all_filters( 'site_url' );
			remove_all_filters( 'network_site_url' );
			$url = network_site_url( $path );
		}
		return $url;
	}

	public function getUrl_AdminPage( string $slug, bool $wpmsOnly = false ) :string {
		$sUrl = sprintf( 'admin.php?page=%s', $slug );
		return $wpmsOnly ? network_admin_url( $sUrl ) : admin_url( $sUrl );
	}

	/**
	 * @param string $separator
	 * @return string
	 */
	public function getLocale( $separator = '_' ) {
		$locale = get_locale();
		return is_string( $separator ) ? str_replace( '_', $separator, $locale ) : $locale;
	}

	public function getLocaleCountry() :string {
		$locale = $this->getLocale();
		$nSep = strpos( $locale, '_' );
		return $nSep ? substr( $locale, 0, $nSep ) : $locale;
	}

	public function getLocaleForChecksums() :string {
		global $wp_local_package;
		return empty( $wp_local_package ) ? 'en_US' : $wp_local_package;
	}

	/**
	 * @param int $nTime
	 * @return string
	 */
	public function getTimeStampForDisplay( $nTime = null ) :string {
		$nTime = empty( $nTime ) ? Services::Request()->ts() : $nTime;
		return date_i18n( DATE_RFC2822, $this->getTimeAsGmtOffset( $nTime ) );
	}

	/**
	 * @param string $type - plugins, themes
	 * @return array
	 */
	public function getWordpressUpdates( string $type = 'plugins' ) {
		$current = $this->getTransient( 'update_'.$type );
		return ( isset( $current->response ) && is_array( $current->response ) ) ? $current->response : [];
	}

	/**
	 * @param string $sKey
	 * @return mixed
	 */
	public function getTransient( $sKey ) {
		// TODO: Handle multisite

		if ( function_exists( 'get_site_transient' ) ) {
			$mResult = get_site_transient( $sKey );
			if ( empty( $mResult ) ) {
				remove_all_filters( 'pre_site_transient_'.$sKey );
				$mResult = get_site_transient( $sKey );
			}
		}
		elseif ( version_compare( $this->getVersion(), '2.7.9', '<=' ) ) {
			$mResult = get_option( $sKey );
		}
		elseif ( version_compare( $this->getVersion(), '2.9.9', '<=' ) ) {
			$mResult = apply_filters( 'transient_'.$sKey, get_option( '_transient_'.$sKey ) );
		}
		else {
			$mResult = apply_filters( 'site_transient_'.$sKey, get_option( '_site_transient_'.$sKey ) );
		}
		return $mResult;
	}

	/**
	 * @return string|null
	 */
	public function getPath_WpConfig() {
		$FS = Services::WpFs();
		$sMain = path_join( ABSPATH, 'wp-config.php' );
		$sSec = path_join( ABSPATH.'..', 'wp-config.php' );
		return $FS->exists( $sMain ) ? $sMain : ( $FS->exists( $sSec ) ? $sSec : null );
	}

	public function isClassicPress() :bool {
		return function_exists( 'classicpress_version' );
	}

	public function isMaintenanceMode() :bool {
		$bMaintenance = false;
		$sFile = ABSPATH.'.maintenance';
		if ( Services::WpFs()->exists( $sFile ) ) {
			include( $sFile );
			if ( isset( $upgrading ) && ( Services::Request()->ts() - $upgrading ) < 600 ) {
				$bMaintenance = true;
			}
		}
		return $bMaintenance;
	}

	public function isPermalinksEnabled() :bool {
		return $this->getOption( 'permalink_structure' ) ? true : false;
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mValue
	 * @param int    $nExpire
	 * @return bool
	 */
	public function setTransient( $sKey, $mValue, $nExpire = 0 ) {
		return set_site_transient( $sKey, $mValue, $nExpire );
	}

	/**
	 * @param $sKey
	 * @return bool
	 */
	public function deleteTransient( $sKey ) {

		if ( version_compare( $this->getVersion(), '2.7.9', '<=' ) ) {
			$bResult = delete_option( $sKey );
		}
		elseif ( function_exists( 'delete_site_transient' ) ) {
			$bResult = delete_site_transient( $sKey );
		}
		elseif ( version_compare( $this->getVersion(), '2.9.9', '<=' ) ) {
			$bResult = delete_option( '_transient_'.$sKey );
		}
		else {
			$bResult = delete_option( '_site_transient_'.$sKey );
		}
		return $bResult;
	}

	public function getDirUploads() :string {
		$aDirParts = wp_get_upload_dir();
		$bHasUploads = is_array( $aDirParts ) && !empty( $aDirParts[ 'basedir' ] )
					   && Services::WpFs()->exists( $aDirParts[ 'basedir' ] );
		return $bHasUploads ? $aDirParts[ 'basedir' ] : '';
	}

	/**
	 * TODO: Create ClassicPress override class for this stuff
	 * @param bool $bIgnoreClassicpress if true returns the $wp_version regardless of ClassicPress or not
	 * @return string
	 */
	public function getVersion( $bIgnoreClassicpress = false ) {

		if ( empty( $this->sWpVersion ) ) {
			$sVersionContents = file_get_contents( ABSPATH.WPINC.'/version.php' );

			if ( preg_match( '/wp_version\s=\s\'([^(\'|")]+)\'/i', $sVersionContents, $aMatches ) ) {
				$this->sWpVersion = $aMatches[ 1 ];
			}
			else {
				global $wp_version;
				$this->sWpVersion = $wp_version;
			}
		}
		return ( $bIgnoreClassicpress || !$this->isClassicPress() ) ? $this->sWpVersion : \classicpress_version();
	}

	public function getWordpressIsAtLeastVersion( string $version, bool $ignoreCP = true ) :bool {
		return version_compare( $this->getVersion( $ignoreCP ), $version, '>=' );
	}

	/**
	 * @param string $sPluginBaseFilename
	 * @return bool
	 * @deprecated
	 */
	public function getIsPluginAutomaticallyUpdated( $sPluginBaseFilename ) {
		return Services::WpPlugins()->isPluginAutomaticallyUpdated( $sPluginBaseFilename );
	}

	public function getUrl_CurrentAdminPage() :string {

		$page = Services::WpPost()->getCurrentPage();
		$url = self_admin_url( $page );

		//special case for plugin admin pages.
		if ( $page == 'admin.php' ) {
			$sSubPage = Services::Request()->query( 'page' );
			if ( !empty( $sSubPage ) ) {
				$aQueryArgs = [
					'page' => $sSubPage,
				];
				$url = add_query_arg( $aQueryArgs, $url );
			}
		}
		return $url;
	}

	/**
	 * @param string
	 * @return string
	 */
	public function getIsPage_Updates() {
		return Services::WpPost()->isCurrentPage( 'update.php' );
	}

	public function getLoginUrl() :string {
		return wp_login_url();
	}

	public function getLostPasswordUrl() :string {
		return add_query_arg( [ 'action' => 'lostpassword' ], $this->getLoginUrl() );
	}

	/**
	 * @param $sTermSlug
	 * @return bool
	 */
	public function getDoesWpSlugExist( $sTermSlug ) {
		return ( Services::WpPost()->getDoesWpPostSlugExist( $sTermSlug ) || term_exists( $sTermSlug ) );
	}

	/**
	 * @param $termSlug
	 * @return bool
	 * @deprecated
	 */
	public function getDoesWpPostSlugExist( $termSlug ) {
		return Services::WpPost()->getDoesWpPostSlugExist( $termSlug );
	}

	public function getSiteName() :string {
		return function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'WordPress Site';
	}

	public function getSiteAdminEmail() :string {
		return function_exists( 'get_bloginfo' ) ? get_bloginfo( 'admin_email' ) : '';
	}

	/**
	 * @return string
	 */
	public function getCookieDomain() {
		return defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : false;
	}

	public function getCookiePath() :string {
		return defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
	}

	public function isApplicationPasswordApiRequest() :bool {
		return (bool)apply_filters( 'application_password_is_api_request',
			$this->isXmlrpc() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) );
	}

	public function isAjax() :bool {
		return function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	public function isCron() :bool {
		return function_exists( 'wp_doing_cron' ) ? wp_doing_cron() : defined( 'DOING_CRON' ) && DOING_CRON;
	}

	public function isMobile() :bool {
		return function_exists( 'wp_is_mobile' ) && wp_is_mobile();
	}

	public function isWpCli() :bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	public function isXmlrpc() :bool {
		return defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
	}

	public function isLoginUrl() :bool {
		$sLoginPath = @parse_url( $this->getLoginUrl(), PHP_URL_PATH );
		return trim( Services::Request()->getPath(), '/' ) == trim( $sLoginPath, '/' );
	}

	public function isLoginRequest() :bool {
		$req = Services::Request();
		return $req->isPost()
			   && $this->isLoginUrl()
			   && !is_null( $req->post( 'log' ) )
			   && !is_null( $req->post( 'pwd' ) );
	}

	public function isRegisterRequest() :bool {
		$req = Services::Request();
		return $req->isPost()
			   && $this->isLoginUrl()
			   && !is_null( $req->post( 'user_login' ) )
			   && !is_null( $req->post( 'user_email' ) );
	}

	public function isMultisite() :bool {
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	public function isMultisite_SubdomainInstall() :bool {
		return $this->isMultisite() && defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL;
	}

	/**
	 * @param string $sKey
	 * @param string $sValue
	 * @return bool
	 */
	public function addOption( $sKey, $sValue ) {
		return $this->isMultisite() ? add_site_option( $sKey, $sValue ) : add_option( $sKey, $sValue );
	}

	/**
	 * @param string $sKey
	 * @param        $sValue
	 * @param bool   $bIgnoreWPMS
	 * @return bool
	 */
	public function updateOption( $sKey, $sValue, $bIgnoreWPMS = false ) {
		return ( $this->isMultisite() && !$bIgnoreWPMS ) ? update_site_option( $sKey, $sValue ) : update_option( $sKey, $sValue );
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mDefault
	 * @param bool   $bIgnoreWPMS
	 * @return mixed
	 */
	public function getOption( $sKey, $mDefault = false, $bIgnoreWPMS = false ) {
		return ( $this->isMultisite() && !$bIgnoreWPMS ) ? get_site_option( $sKey, $mDefault ) : get_option( $sKey, $mDefault );
	}

	/**
	 * @param string $sKey
	 * @param bool   $bIgnoreWPMS
	 * @return bool
	 */
	public function deleteOption( $sKey, $bIgnoreWPMS = false ) {
		return ( $this->isMultisite() && !$bIgnoreWPMS ) ? delete_site_option( $sKey ) : delete_option( $sKey );
	}

	public function getCurrentWpAdminPage() :string {
		$req = Services::Request();
		$sScript = $req->server( 'SCRIPT_NAME' );
		if ( empty( $sScript ) ) {
			$sScript = $req->server( 'PHP_SELF' );
		}
		if ( is_admin() && !empty( $sScript ) && basename( $sScript ) == 'admin.php' ) {
			$sCurrentPage = $req->query( 'page' );
		}
		return empty( $sCurrentPage ) ? '' : $sCurrentPage;
	}

	/**
	 * @param int|null $nTime
	 * @param bool     $bShowTime
	 * @param bool     $bShowDate
	 * @return string
	 */
	public function getTimeStringForDisplay( $nTime = null, $bShowTime = true, $bShowDate = true ) {
		$nTime = empty( $nTime ) ? Services::Request()->ts() : $nTime;

		$sFullTimeString = $bShowTime ? $this->getTimeFormat() : '';
		if ( empty( $sFullTimeString ) ) {
			$sFullTimeString = $bShowDate ? $this->getDateFormat() : '';
		}
		else {
			$sFullTimeString = $bShowDate ? ( $sFullTimeString.' '.$this->getDateFormat() ) : $sFullTimeString;
		}
		return date_i18n( $sFullTimeString, $this->getTimeAsGmtOffset( $nTime ) );
	}

	/**
	 * @param null $nTime
	 * @return int|null
	 */
	public function getTimeAsGmtOffset( $nTime = null ) {

		$nTimezoneOffset = wp_timezone_override_offset();
		if ( $nTimezoneOffset === false ) {
			$nTimezoneOffset = $this->getOption( 'gmt_offset' );
			if ( empty( $nTimezoneOffset ) ) {
				$nTimezoneOffset = 0;
			}
		}

		$nTime = empty( $nTime ) ? Services::Request()->ts() : $nTime;
		return $nTime + ( $nTimezoneOffset*HOUR_IN_SECONDS );
	}

	public function getTimeFormat() :string {
		$format = $this->getOption( 'time_format' );
		return empty( $format ) ? 'H:i' : $format;
	}

	public function getDateFormat() :string {
		$format = $this->getOption( 'date_format' );
		return empty( $format ) ? 'F j, Y' : $format;
	}

	/**
	 * @return false|\WP_Automatic_Updater
	 */
	public function getWpAutomaticUpdater() {
		if ( !isset( $this->oWpAutomaticUpdater ) ) {
			require_once( ABSPATH.'wp-admin/includes/class-wp-upgrader.php' );
			$this->oWpAutomaticUpdater = new \WP_Automatic_Updater();
		}
		return $this->oWpAutomaticUpdater;
	}

	public function getIfAutoUpdatesInstalled() :bool {
		return (int)did_action( 'automatic_updates_complete' ) > 0;
	}

	public function canCoreUpdateAutomatically() :bool {
		$can = false;

		$sThisV = $this->getVersion();
		if ( preg_match( '#^[\d]+(\.[\d]){1,2}+$#', $sThisV ) ) {
			if ( substr_count( $sThisV, '.' ) == 1 ) {
				$sThisV .= '.0';
			}
			$aParts = explode( '.', $sThisV );
			$aParts[ 2 ]++;
			global $required_php_version, $required_mysql_version;
			$future_minor_update = (object)[
				'current'       => implode( '.', $aParts ),
				'version'       => $sThisV,
				'php_version'   => $required_php_version,
				'mysql_version' => $required_mysql_version,
			];
			$can = $this->getWpAutomaticUpdater()
						->should_update( 'core', $future_minor_update, ABSPATH );
		}
		return $can;
	}

	/**
	 * @return array|false
	 */
	public function getCoreUpdates() {
		include_once( ABSPATH.'wp-admin/includes/update.php' );
		return get_core_updates();
	}

	/**
	 * See: /wp-admin/update-core.php core_upgrade_preamble()
	 * @return bool
	 */
	public function hasCoreUpdate() :bool {
		$aUpdates = $this->getCoreUpdates();
		return ( isset( $aUpdates[ 0 ]->response ) && 'latest' != $aUpdates[ 0 ]->response );
	}

	/**
	 * Flushes the Rewrite rules and forces a re-commit to the .htaccess where applicable
	 */
	public function resavePermalinks() {
		/** @var \WP_Rewrite $wp_rewrite */
		global $wp_rewrite;
		if ( is_object( $wp_rewrite ) ) {
			$wp_rewrite->flush_rules( true );
		}
	}

	/**
	 * @return bool
	 */
	public function turnOffCache() {
		if ( !defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( function_exists( 'wpfc_exclude_current_page' ) ) {
			/** WP Fastest */
			wpfc_exclude_current_page();
		}
		return DONOTCACHEPAGE;
	}

	/**
	 * @param string $sMessage
	 * @param string $sTitle
	 * @param bool   $bTurnOffCachePage
	 */
	public function wpDie( $sMessage, $sTitle = '', $bTurnOffCachePage = true ) {
		if ( $bTurnOffCachePage ) {
			$this->turnOffCache();
		}
		wp_die( $sMessage, $sTitle );
	}

	/**
	 * @param string $sPluginFile
	 * @return array
	 * @deprecated
	 */
	public function doPluginUpgrade( $sPluginFile ) {
		return Services::WpPlugins()->update( $sPluginFile );
	}

	/**
	 * @return array
	 * @deprecated
	 */
	public function getWordpressUpdates_Plugins() {
		return Services::WpPlugins()->getUpdates();
	}

	/**
	 * @param string $sCompareString
	 * @param string $sKey
	 * @return bool|string
	 * @deprecated
	 */
	public function getIsPluginInstalled( $sCompareString, $sKey = 'Name' ) {
		return Services::WpPlugins()->isInstalled( Services::WpPlugins()->findPluginBy( $sCompareString, $sKey ) );
	}

	/**
	 * @param string $sPluginBaseFile
	 * @return bool
	 * @deprecated
	 */
	public function getIsPluginInstalledByFile( $sPluginBaseFile ) {
		return Services::WpPlugins()->isInstalled( $sPluginBaseFile );
	}

	/**
	 * @return array
	 * @deprecated
	 */
	public function getThemes() {
		return Services::WpThemes()->getThemes();
	}

	/**
	 * @param string $sPluginFile
	 * @return string
	 * @deprecated
	 */
	public function getPluginActivateLink( $sPluginFile ) {
		return Services::WpPlugins()->getLinkPluginActivate( $sPluginFile );
	}

	/**
	 * @param string $sPluginFile
	 * @return string
	 * @deprecated
	 */
	public function getPluginDeactivateLink( $sPluginFile ) {
		return Services::WpPlugins()->getLinkPluginDeactivate( $sPluginFile );
	}

	/**
	 * @param string $sPluginFile
	 * @return string
	 * @deprecated
	 */
	public function getPluginUpgradeLink( $sPluginFile ) {
		return Services::WpPlugins()->getLinkPluginUpgrade( $sPluginFile );
	}

	/**
	 * @param string $sPluginFile
	 * @return int
	 * @deprecated
	 */
	public function getActivePluginLoadPosition( $sPluginFile ) {
		return Services::WpPlugins()->getActivePluginLoadPosition( $sPluginFile );
	}

	/**
	 * @return array
	 * @deprecated
	 */
	public function getActivePlugins() {
		return Services::WpPlugins()->getActivePlugins();
	}

	/**
	 * @return array
	 * @deprecated
	 */
	public function getPlugins() {
		return Services::WpPlugins()->getPlugins();
	}

	/**
	 * @param string $sRootPluginFile - the full path to the root plugin file
	 * @return array
	 * @deprecated
	 */
	public function getPluginData( $sRootPluginFile ) {
		return Services::WpPlugins()->getExtendedData( $sRootPluginFile );
	}

	/**
	 * @param string $sPluginFile
	 * @return \stdClass|null
	 * @deprecated
	 */
	public function getPluginUpdateInfo( $sPluginFile ) {
		return Services::WpPlugins()->getUpdateInfo( $sPluginFile );
	}

	/**
	 * @param string $sPluginFile
	 * @return string
	 * @deprecated
	 */
	public function getPluginUpdateNewVersion( $sPluginFile ) {
		return Services::WpPlugins()->getUpdateNewVersion( $sPluginFile );
	}

	/**
	 * @param string $sPluginFile
	 * @return bool|\stdClass
	 * @deprecated
	 */
	public function getIsPluginUpdateAvailable( $sPluginFile ) {
		return Services::WpPlugins()->isUpdateAvailable( $sPluginFile );
	}

	/**
	 * @param string $sCompareString
	 * @param string $sKey
	 * @return bool
	 * @deprecated
	 */
	public function getIsPluginActive( $sCompareString, $sKey = 'Name' ) {
		return Services::WpPlugins()->isActive( Services::WpPlugins()->findPluginBy( $sCompareString, $sKey ) );
	}

	/**
	 * @param string $sPluginFile
	 * @param int    $nDesiredPosition
	 * @deprecated
	 */
	public function setActivePluginLoadPosition( $sPluginFile, $nDesiredPosition = 0 ) {
		Services::WpPlugins()->setActivePluginLoadPosition( $sPluginFile, $nDesiredPosition );
	}

	/**
	 * @param string $sPluginBaseFilename
	 * @return null|\stdClass
	 * @deprecated
	 */
	public function getPluginDataAsObject( $sPluginBaseFilename ) {
		return Services::WpPlugins()->getPluginDataAsObject( $sPluginBaseFilename );
	}

	/**
	 * @param string $sPluginFile
	 * @deprecated
	 */
	public function setActivePluginLoadFirst( $sPluginFile ) {
		Services::WpPlugins()->setActivePluginLoadFirst( $sPluginFile );
	}

	/**
	 * @param string $sPluginFile
	 * @deprecated
	 */
	public function setActivePluginLoadLast( $sPluginFile ) {
		Services::WpPlugins()->setActivePluginLoadPosition( $sPluginFile, 1000 );
	}

	/**
	 * @return array
	 * @deprecated
	 */
	public function getWordpressUpdates_Themes() {
		return Services::WpThemes()->getUpdates();
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function getWordpressVersion() {
		return $this->getVersion();
	}

	/**
	 * @return string
	 * @deprecated getAdminUrl()
	 */
	public function getUrl_WpAdmin() {
		return get_admin_url();
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function getIsLoginRequest() {
		return $this->isLoginRequest();
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function getIsRegisterRequest() {
		return $this->isRegisterRequest();
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function getIsLoginUrl() {
		return $this->isLoginUrl();
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function getIsPermalinksEnabled() {
		return $this->isPermalinksEnabled();
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function getCurrentPage() {
		return Services::WpPost()->getCurrentPage();
	}

	/**
	 * @return \WP_Post
	 * @deprecated
	 */
	public function getCurrentPost() {
		return Services::WpPost()->getCurrentPost();
	}

	/**
	 * @return int
	 * @deprecated
	 */
	public function getCurrentPostId() {
		return Services::WpPost()->getCurrentPostId();
	}

	/**
	 * @param $nPostId
	 * @return false|\WP_Post
	 * @deprecated
	 */
	public function getPostById( $nPostId ) {
		return Services::WpPost()->getById( $nPostId );
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function getIsAjax() {
		return $this->isAjax();
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function getIsCron() {
		return $this->isCron();
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function getIsXmlrpc() {
		return $this->isXmlrpc();
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function getIsMobile() {
		return $this->isMobile();
	}

	/**
	 * @return array
	 * @deprecated
	 */
	public function getAllUserLoginUsernames() {
		return Services::WpUsers()->getAllUserLoginUsernames();
	}

	/**
	 * @param string
	 * @return string
	 * @deprecated
	 */
	public function getIsCurrentPage( $sPage ) {
		return Services::WpPost()->isCurrentPage( $sPage );
	}

	/**
	 * @param string $url
	 * @param array  $aQueryParams
	 * @param bool   $bSafe
	 * @param bool   $bProtectAgainstInfiniteLoops - if false, ignores the redirect loop protection
	 * @deprecated
	 */
	public function doRedirect( $url, $aQueryParams = [], $bSafe = true, $bProtectAgainstInfiniteLoops = true ) {
		Services::Response()->redirect( $url, $aQueryParams, $bSafe, $bProtectAgainstInfiniteLoops );
	}

	/**
	 * @deprecated
	 */
	public function redirectHere() {
		Services::Response()->redirectHere();
	}

	/**
	 * @param array $aQueryParams
	 * @deprecated
	 */
	public function redirectToLogin( $aQueryParams = [] ) {
		Services::Response()->redirectToLogin( $aQueryParams );
	}

	/**
	 * @param array $aQueryParams
	 * @deprecated
	 */
	public function redirectToAdmin( $aQueryParams = [] ) {
		Services::Response()->redirectToAdmin( $aQueryParams );
	}

	/**
	 * @param array $aQueryParams
	 * @deprecated
	 */
	public function redirectToHome( $aQueryParams = [] ) {
		Services::Response()->redirectToHome( $aQueryParams );
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function getIsRunningAutomaticUpdates() {
		return $this->isRunningAutomaticUpdates();
	}

	/**
	 * @param string $sPath
	 * @return string
	 * @deprecated
	 */
	public function getUrlWithPath( $sPath ) {
		return rtrim( $this->getHomeUrl(), '/' ).'/'.ltrim( $sPath, '/' );
	}
}