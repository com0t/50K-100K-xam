<?php

namespace FernleafSystems\Wordpress\Services\Utilities;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Services\{
	IPs,
	ProviderIPs
};
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

/**
 * Class ServiceProviders
 * @package FernleafSystems\Wordpress\Services\Utilities
 */
class ServiceProviders {

	/**
	 * @return array[][]
	 */
	public static function GetProviderIPs() :array {
		$IPs = Transient::Get( 'apto_provider_ips' );
		if ( empty( $IPs ) || !is_array( $IPs ) ) {
			$IPs = ( new ProviderIPs() )->getIPs();
			if ( empty( $IPs ) ) { // fallback
				$raw = Services::Data()->readFileWithInclude( Services::DataDir( 'service_providers.json' ) );
				if ( !empty( $raw ) ) {
					$IPs = json_decode( $raw, true );
				}
			}
			Transient::Set( 'apto_provider_ips', $IPs, DAY_IN_SECONDS );
		}
		return is_array( $IPs ) ? $IPs : [];
	}

	public function getProviderInfo( string $providerSlug ) :array {
		$info = [];
		foreach ( ServiceProviders::GetProviderIPs() as $category ) {
			foreach ( $category as $slug => $provider ) {
				if ( $providerSlug === $slug ) {
					$info = $provider;
					break;
				}
			}
		}
		return $info;
	}

	public function getProviderName( string $providerSlug ) :string {
		$info = $this->getProviderInfo( $providerSlug );
		return empty( $info ) ? 'Unknown' : $info[ 'name' ];
	}

	public function getProvidersOfType( string $type ) :array {
		$providers = [];
		foreach ( ServiceProviders::GetProviderIPs() as $category ) {
			foreach ( $category as $slug => $provider ) {
				if ( isset( $provider[ 'type' ] ) && in_array( $type, $provider[ 'type' ] ) ) {
					$providers[] = $slug;
				}
			}
		}
		return $providers;
	}

	public function getSearchProviders() :array {
		return $this->getProvidersOfType( 'search' );
	}

	public function getUptimeProviders() :array {
		return $this->getProvidersOfType( 'uptime' );
	}

	public function getWpSiteManagementProviders() :array {
		return $this->getProvidersOfType( 'wp_site_management' );
	}

	/**
	 * @return string[]
	 */
	public function getAllCrawlerUseragents() {
		return [
			'Applebot/',
			'baidu',
			'bingbot',
			'Googlebot',
			'APIs-Google',
			'AdsBot-Google',
			'Mediapartners-Google',
			'PetalBot',
			'SemrushBot',
			'yandex.com/bots',
			'yahoo!'
		];
	}

	/**
	 * @return string[][][]|null
	 */
	protected function getAllServiceIPs() {
		$aIps = Transient::Get( 'serviceips_all' );
		if ( empty( $aIps ) ) {
			$aIps = ( new IPs() )->getIPs();
			$aIps = Transient::Set( 'serviceips_all', $aIps, WEEK_IN_SECONDS );
		}
		return $aIps;
	}

	/**
	 * @return string[][]
	 */
	public function getIps_CloudFlare() :array {
		return $this->getIpsForSlug( 'cloudflare' );
	}

	/**
	 * @return string[]
	 */
	public function getIps_CloudFlareV4() :array {
		return $this->getIps_CloudFlare()[ 4 ];
	}

	/**
	 * @return string[]
	 */
	public function getIps_CloudFlareV6() :array {
		return $this->getIps_CloudFlare()[ 6 ];
	}

	/**
	 * @param bool $flatList
	 * @return string[]|string[][]
	 */
	public function getIps_DuckDuckGo( $flatList = false ) :array {
		return $this->getIpsForSlug( 'duckduckgo', $flatList );
	}

	/**
	 * @param bool $flatList
	 * @return string[][]|string[]
	 */
	public function getIps_iControlWP( $flatList = false ) :array {
		return $this->getIpsForSlug( 'icontrolwp', $flatList );
	}

	/**
	 * @param bool $flatList
	 * @return string[][]|string[]
	 */
	public function getIps_ManageWp( $flatList = false ) :array {
		return $this->getIpsForSlug( 'managewp', $flatList );
	}

	/**
	 * @param bool $flatList
	 * @return string[][]|string[]
	 */
	public function getIps_NodePing( $flatList = false ) :array {
		return $this->getIpsForSlug( 'nodeping', $flatList );
	}

	/**
	 * @param bool $flatList
	 * @return string[][]|string[]
	 */
	public function getIps_Pingdom( $flatList = false ) :array {
		return $this->getIpsForSlug( 'pingdom', $flatList );
	}

	/**
	 * @param bool $flatList
	 * @return string[]|\string[][]
	 */
	public function getIps_Statuscake( $flatList = false ) :array {
		return $this->getIpsForSlug( 'statuscake', $flatList );
	}

	/**
	 * @param bool $flatList
	 * @return string[][]
	 */
	public function getIps_Sucuri( $flatList = false ) :array {
		return $this->getIpsForSlug( 'sucuri', $flatList );
	}

	/**
	 * @param bool $flatList - false for segregated IPv4 and IPv6
	 * @return string[][]|string[]
	 */
	public function getIps_UptimeRobot( $flatList = false ) :array {
		return $this->getIpsForSlug( 'uptimerobot', $flatList );
	}

	/**
	 * @param string $slug
	 * @param bool   $flatList
	 * @return string[][]|string[]
	 */
	public function getIpsForSlug( $slug, $flatList = false ) :array {
		$all = $this->getAllServiceIPs();
		$IPs = empty( $all[ $slug ] ) ? [ 4 => [], 6 => [] ] : $all[ $slug ];
		return $flatList ? array_merge( $IPs[ 4 ], $IPs[ 6 ] ) : $IPs;
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_AppleBot( $ip, $agent ) :bool {
		$oWp = Services::WpGeneral();

		$sStoreKey = $this->getPrefixedStoreKey( 'serviceips_applebot' );
		$aIps = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = [];
		}

		if ( !in_array( $ip, $aIps ) && $this->verifyIp_AppleBot( $ip, $agent ) ) {
			$aIps[] = $ip;
			$oWp->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $ip, $aIps );
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_HuaweiBot( $ip, $agent ) :bool {
		$WP = Services::WpGeneral();

		$sStoreKey = $this->getPrefixedStoreKey( 'serviceips_huawei' );
		$aIps = $WP->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = [];
		}

		if ( !in_array( $ip, $aIps ) && $this->verifyIp_HuaweiBot( $ip, $agent ) ) {
			$aIps[] = $ip;
			$WP->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $ip, $aIps );
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_BaiduBot( $ip, $agent ) :bool {
		$oWp = Services::WpGeneral();

		$sStoreKey = $this->getPrefixedStoreKey( 'serviceips_baidubot' );
		$aIps = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = [];
		}

		if ( !in_array( $ip, $aIps ) && $this->verifyIp_BaiduBot( $ip, $agent ) ) {
			$aIps[] = $ip;
			$oWp->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $ip, $aIps );
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_BingBot( $ip, $agent ) :bool {
		$oWp = Services::WpGeneral();

		$sStoreKey = $this->getPrefixedStoreKey( 'serviceips_bingbot' );
		$aIps = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = [];
		}

		if ( !in_array( $ip, $aIps ) && $this->verifyIp_BingBot( $ip, $agent ) ) {
			$aIps[] = $ip;
			$oWp->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $ip, $aIps );
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 * @deprecated 2.10.1
	 */
	public function isIp_BlogVault( $ip, $agent ) :bool {
		return false;
	}

	/**
	 * @param string $ip
	 * @return bool
	 */
	public function isIp_Cloudflare( $ip ) :bool {
		return $this->isIpInCollection( $ip, $this->getIpsForSlug( 'cloudflare' ) );
	}

	/**
	 * https://duckduckgo.com/duckduckbot
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_DuckDuckGoBot( $ip, $agent ) :bool {
		return ( empty( $agent ) || stripos( $agent, 'DuckDuckBot' ) !== false )
			   && $this->isIpInCollection( $ip, $this->getIpsForSlug( 'duckduckgo' ) );
	}

	/**
	 * @param string     $ip
	 * @param string[][] $collection
	 * @return bool
	 */
	public function isIpInCollection( $ip, array $collection ) :bool {
		try {
			$version = Services::IP()->getIpVersion( $ip );
			$exists = $version !== false && Services::IP()->checkIp( $ip, $collection[ $version ] );
		}
		catch ( \Exception $e ) {
			$exists = false;
		}
		return $exists;
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_iControlWP( string $ip, $agent = null ) :bool { //TODO: Agent
		$bIsBot = false;
		if ( is_null( $agent ) || stripos( $agent, 'iControlWPApp' ) !== false ) {
			$bIsBot = $this->isIpInCollection( $ip, $this->getIpsForSlug( 'icontrolwp' ) );
		}
		return $bIsBot;
	}

	/**
	 * https://support.google.com/webmasters/answer/80553?hl=en
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	public function isIp_GoogleBot( $sIp, $sUserAgent ) {
		$oWp = Services::WpGeneral();

		$sStoreKey = $this->getPrefixedStoreKey( 'serviceips_googlebot' );
		$aIps = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = [];
		}

		if ( !in_array( $sIp, $aIps ) && $this->verifyIp_GoogleBot( $sIp, $sUserAgent ) ) {
			$aIps[] = $sIp;
			$oWp->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $sIp, $aIps );
	}

	/**
	 * @param string $ip
	 * @return bool
	 */
	public function isIp_ManageWP( $ip ) :bool {
		return $this->isIpInCollection( $ip, $this->getIpsForSlug( 'managewp' ) );
	}

	/**
	 * @param string $ip
	 * @return bool
	 */
	public function isIp_PayPal( $ip ) :bool {
		return $this->isIpInCollection( $ip, $this->getIpsForSlug( 'paypal_ipn' ) );
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_Pingdom( $ip, $agent ) :bool {
		return ( stripos( $agent, 'pingdom.com' ) !== false )
			   && $this->isIpInCollection( $ip, $this->getIpsForSlug( 'pingdom' ) );
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_Stripe( $ip, $agent ) :bool {
		return ( stripos( $agent, 'Stripe/' ) !== false )
			   && $this->isIpInCollection( $ip, $this->getIpsForSlug( 'stripe' ) );
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_SemRush( $ip, $agent ) {
		$oWp = Services::WpGeneral();

		$sStoreKey = $this->getPrefixedStoreKey( 'serviceips_semrush' );
		$IPs = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $IPs ) ) {
			$IPs = [];
		}

		if ( !in_array( $ip, $IPs ) && $this->verifyIp_SemRush( $ip, $agent ) ) {
			$IPs[] = $ip;
			$oWp->setTransient( $sStoreKey, $IPs, WEEK_IN_SECONDS*4 );
		}

		return in_array( $ip, $IPs );
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_Statuscake( $ip, $agent ) :bool {
		return ( empty( $agent ) || ( stripos( $agent, 'StatusCake' ) !== false ) )
			   && $this->isIpInCollection( $ip, $this->getIpsForSlug( 'statuscake' ) );
	}

	public function isIp_UptimeRobot( $ip, $agent ) :bool {
		return ( empty( $agent ) || ( stripos( $agent, 'UptimeRobot' ) !== false ) )
			   && $this->isIpInCollection( $ip, $this->getIpsForSlug( 'uptimerobot' ) );
	}

	/**
	 * https://yandex.com/support/webmaster/robot-workings/check-yandex-robots.html
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_YandexBot( $ip, $agent ) {
		$oWp = Services::WpGeneral();

		$sStoreKey = $this->getPrefixedStoreKey( 'serviceips_yandexbot' );
		$IPs = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $IPs ) ) {
			$IPs = [];
		}

		if ( !in_array( $ip, $IPs ) && $this->verifyIp_YandexBot( $ip, $agent ) ) {
			$IPs[] = $ip;
			$oWp->setTransient( $sStoreKey, $IPs, WEEK_IN_SECONDS*4 );
		}

		return in_array( $ip, $IPs );
	}

	/**
	 * https://yandex.com/support/webmaster/robot-workings/check-yandex-robots.html
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	public function isIp_YahooBot( $ip, $agent ) {
		$oWp = Services::WpGeneral();

		$sStoreKey = $this->getPrefixedStoreKey( 'serviceips_yahoobot' );
		$aIps = $oWp->getTransient( $sStoreKey );
		if ( !is_array( $aIps ) ) {
			$aIps = [];
		}

		if ( !in_array( $ip, $aIps ) && $this->verifyIp_YahooBot( $ip, $agent ) ) {
			$aIps[] = $ip;
			$oWp->setTransient( $sStoreKey, $aIps, WEEK_IN_SECONDS*4 );
		}

		return in_array( $ip, $aIps );
	}

	/**
	 * https://support.apple.com/en-gb/HT204683
	 * https://discussions.apple.com/thread/7090135
	 * Apple IPs start with '17.'
	 * @param string $ip
	 * @param string $sUserAgent
	 * @return bool
	 */
	private function verifyIp_AppleBot( $ip, $sUserAgent = '' ) {
		return ( Services::IP()->getIpVersion( $ip ) != 4 || strpos( $ip, '17.' ) === 0 )
			   && $this->isIpOfBot( [ 'Applebot/' ], '#.*\.applebot.apple.com\.?$#i', $ip, $sUserAgent );
	}

	/**
	 * https://developer.huawei.com/consumer/en/doc/petalbot
	 * @param string $ip
	 * @param string $userAgent
	 * @return bool
	 */
	private function verifyIp_HuaweiBot( $ip, $userAgent = '' ) {
		return $this->isIpOfBot( [ 'PetalBot' ], '#.*\.aspiegel.com\.?$#i', $ip, $userAgent );
	}

	/**
	 * @param string $ip
	 * @param string $sUserAgent
	 * @return bool
	 */
	private function verifyIp_BaiduBot( $ip, $sUserAgent = '' ) {
		return $this->isIpOfBot( [ 'baidu' ], '#.*\.crawl\.baidu\.(com|jp)\.?$#i', $ip, $sUserAgent );
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	private function verifyIp_BingBot( $ip, $agent = '' ) {
		return $this->isIpOfBot( [ 'bingbot' ], '#.*\.search\.msn\.com\.?$#i', $ip, $agent );
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	private function verifyIp_GoogleBot( $ip, $agent = '' ) {
		return $this->isIpOfBot(
			[ 'Googlebot', 'APIs-Google', 'AdsBot-Google', 'Mediapartners-Google' ],
			'#.*\.google(bot)?\.com\.?$#i', $ip, $agent
		);
	}

	/**
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	private function verifyIp_SemRush( $ip, $agent = '' ) :bool {
		return $this->isIpOfBot( [ 'SemrushBot' ], '#.*\.bot\.semrush\.com\.?$#i', $ip, $agent );
	}

	/**
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	private function verifyIp_YandexBot( $sIp, $sUserAgent = '' ) {
		return $this->isIpOfBot( [ 'yandex.com/bots' ], '#.*\.yandex?\.(com|ru|net)\.?$#i', $sIp, $sUserAgent );
	}

	/**
	 * @param string $sIp
	 * @param string $sUserAgent
	 * @return bool
	 */
	private function verifyIp_YahooBot( $sIp, $sUserAgent = '' ) {
		return $this->isIpOfBot( [ 'yahoo!' ], '#.*\.crawl\.yahoo\.net\.?$#i', $sIp, $sUserAgent );
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	private function verifyIp_Sucuri( $sIp ) {
		$host = @gethostbyaddr( $sIp ); // returns the ip on failure
		return !empty( $host ) && ( $host != $sIp )
			   && preg_match( '#.*\.sucuri\.net\.?$#i', $host )
			   && gethostbyname( $host ) === $sIp;
	}

	/**
	 * Will test useragent, then attempt to resolve to hostname and back again
	 * https://www.elephate.com/detect-verify-crawlers/
	 * @param array  $aBotUserAgents
	 * @param string $sBotHostPattern
	 * @param string $ip
	 * @param string $agent
	 * @return bool
	 */
	private function isIpOfBot( $aBotUserAgents, $sBotHostPattern, $ip, $agent = '' ) :bool {
		$isBot = false;

		$bCheckIpHost = is_null( $agent );
		if ( !$bCheckIpHost ) {
			$aBotUserAgents = array_map(
				function ( $sAgent ) {
					return preg_quote( $sAgent, '#' );
				},
				$aBotUserAgents
			);
			$bCheckIpHost = (bool)preg_match( sprintf( '#%s#i', implode( '|', $aBotUserAgents ) ), $agent );
		}

		if ( $bCheckIpHost ) {
			$host = @gethostbyaddr( $ip ); // returns the ip on failure
			$isBot = !empty( $host ) && ( $host != $ip )
					 && preg_match( $sBotHostPattern, $host )
					 && gethostbyname( $host ) === $ip;
		}
		return $isBot;
	}

	/**
	 * @param string $key
	 * @return string
	 */
	private function getPrefixedStoreKey( $key ) {
		return 'odp_'.$key;
	}

	/**
	 * @param string $ip
	 * @return bool
	 * @deprecated
	 */
	public function isIp_Sucuri( $ip ) {
		return false;
	}
}