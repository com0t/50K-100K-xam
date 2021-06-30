<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;
use FernleafSystems\Wordpress\Services\Services;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	protected function buildModCards() :array {
		/** @var Autoupdates\ModCon $mod */
		$mod = $this->getMod();
		/** @var Autoupdates\Options $opts */
		$opts = $this->getOptions();
		$WP = Services::WpGeneral();

		$cards = [];

		if ( $mod->isModOptEnabled() ) {
			$hasUpdate = $WP->hasCoreUpdate();
			$cards[ 'core_update' ] = [
				'name'    => __( 'Core Update', 'wp-simple-firewall' ),
				'state'   => $hasUpdate ? -1 : 1,
				'summary' => $hasUpdate ?
					__( 'WordPress Core is up-to-date', 'wp-simple-firewall' )
					: __( "No WordPress Core upgrades waiting to be applied", 'wp-simple-firewall' ),
				'href'    => $WP->getAdminUrl_Updates( true ),
				'help'    => __( 'Core upgrades should be applied as early as possible.', 'wp-simple-firewall' )
			];

			$canCore = Services::WpGeneral()->canCoreUpdateAutomatically();
			$cards[ 'core_minor' ] = [
				'name'    => __( 'Auto Core Updates', 'wp-simple-firewall' ),
				'state'   => $canCore ? 1 : -1,
				'summary' => $canCore ?
					__( 'Minor WP Core updates will be installed automatically', 'wp-simple-firewall' )
					: __( 'Minor WP Core updates will not be installed automatically', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'autoupdate_core' ),
			];

			$hasDelay = $mod->isModOptEnabled() && $opts->getDelayUpdatesPeriod();
			$cards[ 'delay' ] = [
				'name'    => __( 'Update Delay', 'wp-simple-firewall' ),
				'state'   => $hasDelay ? 1 : -1,
				'summary' => $hasDelay ?
					__( 'Automatic updates are applied after a short delay', 'wp-simple-firewall' )
					: __( 'Automatic updates are applied immediately', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'update_delay' ),
			];

			$sName = $this->getCon()->getHumanName();
			$bSelfAuto = $mod->isModOptEnabled()
						 && in_array( $opts->getSelfAutoUpdateOpt(), [ 'auto', 'immediate' ] );
			$cards[ 'self' ] = [
				'name'    => __( 'Self Auto-Update', 'wp-simple-firewall' ),
				'state'   => $bSelfAuto ? 1 : -1,
				'summary' => $bSelfAuto ?
					sprintf( __( '%s upgrades are installed automatically', 'wp-simple-firewall' ), $sName )
					: sprintf( __( "%s upgrades aren't installed automatically", 'wp-simple-firewall' ), $sName ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'autoupdate_plugin_self' ),
			];
		}

		//really disabled?
		if ( $mod->isModOptEnabled()
			 && $opts->isDisableAllAutoUpdates() && !$WP->getWpAutomaticUpdater()->is_disabled() ) {
			$cards[ 'messages' ][ 'disabled_auto' ] = [
				'name'    => 'Auto Updates Not Really Disabled',
				'summary' => __( 'Automatic Updates Are Not Disabled As Expected.', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'enable_autoupdate_disable_all' ),
				'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
				'help'    => sprintf( __( 'A plugin/theme other than %s is affecting your automatic update settings.', 'wp-simple-firewall' ), $this->getCon()
																																					->getHumanName() ),
				'state'   => -2
			];
		}

		return array_merge(
			$cards,
			$this->getCardsForPlugins(),
			$this->getCardsForThemes()
		);
	}

	protected function getSectionTitle() :string {
		return __( 'Automatic Updates', 'wp-simple-firewall' );
	}

	protected function getSectionSubTitle() :string {
		return __( 'Controlling WordPress Automatic Updates', 'wp-simple-firewall' );
	}

	private function getCardsForPlugins() :array {
		$cards = [];

		$WPP = Services::WpPlugins();
		$nCount = count( $WPP->getPlugins() ) - count( $WPP->getActivePlugins() );
		$cards[ 'plugins_inactive' ] = [
			'name'    => __( 'Inactive Plugins', 'wp-simple-firewall' ),
			'state'   => $nCount > 0 ? -1 : 1,
			'summary' => $nCount > 0 ?
				sprintf( __( 'There are %s inactive and unused plugins', 'wp-simple-firewall' ), $nCount )
				: __( "There appears to be no unused plugins", 'wp-simple-firewall' ),
			'href'    => Services::WpGeneral()->getAdminUrl_Plugins( true ),
			'help'    => __( 'Unused plugins should be removed.', 'wp-simple-firewall' )
		];

		$nCount = count( $WPP->getUpdates() );
		$cards[ 'plugin_updates' ] = [
			'name'    => __( 'Plugin Updates', 'wp-simple-firewall' ),
			'state'   => $nCount > 0 ? -1 : 1,
			'summary' => $nCount > 0 ?
				sprintf( __( 'There are %s plugin updates available to be applied', 'wp-simple-firewall' ), $nCount )
				: __( "All available plugin updates have been applied", 'wp-simple-firewall' ),
			'href'    => Services::WpGeneral()->getAdminUrl_Updates( true ),
			'help'    => __( 'Updates should be applied as early as possible.', 'wp-simple-firewall' ),
		];

		return $cards;
	}

	private function getCardsForThemes() :array {
		$cards = [];

		$oWpT = Services::WpThemes();
		$nCount = count( $oWpT->getThemes() ) - ( $oWpT->isActiveThemeAChild() ? 2 : 1 );
		$cards[ 'themes_inactive' ] = [
			'name'    => __( 'Inactive Themes', 'wp-simple-firewall' ),
			'state'   => $nCount > 0 ? -1 : 1,
			'summary' => $nCount > 0 ?
				sprintf( __( 'There are %s inactive and unused themes', 'wp-simple-firewall' ), $nCount )
				: __( "There appears to be no unused themes", 'wp-simple-firewall' ),
			'href'    => Services::WpGeneral()->getAdminUrl_Themes( true ),
			'help'    => __( 'Unused themes should be removed.', 'wp-simple-firewall' )
		];

		$nCount = count( $oWpT->getUpdates() );
		$cards[ 'theme_updates' ] = [
			'name'    => __( 'Theme Updates', 'wp-simple-firewall' ),
			'state'   => $nCount > 0 ? -1 : 1,
			'summary' => $nCount > 0 ?
				sprintf( __( 'There are %s theme updates available to be applied', 'wp-simple-firewall' ), $nCount )
				: __( "All available theme updates have been applied", 'wp-simple-firewall' ),
			'href'    => Services::WpGeneral()->getAdminUrl_Updates( true ),
			'help'    => __( 'Updates should be applied as early as possible.', 'wp-simple-firewall' ),
		];

		return $cards;
	}
}