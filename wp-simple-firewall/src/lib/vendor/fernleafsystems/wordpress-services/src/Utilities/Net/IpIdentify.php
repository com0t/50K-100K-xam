<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Services\Utilities\Net;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class IpIdentify
 * @package FernleafSystems\Wordpress\Services\Utilities\Net
 * @deprecated 2.11
 */
class IpIdentify {

	const UNKNOWN = 'unknown';
	const VISITOR = 'visitor';
	const THIS_SERVER = 'server';
	const APPLE = 'apple';
	const BAIDU = 'baidu';
	const BING = 'bing';
	const CLOUDFLARE = 'cloudflare';
	const DUCKDUCKGO = 'duckduckgo';
	const GOOGLE = 'google';
	const GTMETRIX = 'gtmetrix';
	const HUAWEI = 'huawei';
	const ICONTROLWP = 'icontrolwp';
	const MANAGEWP = 'managewp';
	const NODEPING = 'nodeping';
	const PAYPAL = 'paypal';
	const PINGDOM = 'pingdom';
	const STATUSCAKE = 'statuscake';
	const SEMRUSH = 'semrush';
	const STRIPE = 'stripe';
	const UPTIMEROBOT = 'uptimerobot';
	const YAHOO = 'yahoo';
	const YANDEX = 'yandex';

	/**
	 * @var string
	 */
	private $ip;

	/**
	 * @var string|null
	 */
	private $agent;

	public function __construct( string $ip, $agent = null ) {
		$this->ip = $ip;
		$this->agent = $agent;
	}

	/**
	 * @return string[]
	 * @throws \Exception
	 */
	public function run() :array {
		$srvIP = Services::IP();
		$srvProviders = Services::ServiceProviders();

		if ( !Services::IP()->isValidIp( $this->ip ) ) {
			throw new \Exception( "A valid IP address was not provided." );
		}

		if ( $srvProviders->isIp_AppleBot( $this->ip, $this->agent ) ) {
			$is = self::APPLE;
		}
		elseif ( $srvProviders->isIp_BaiduBot( $this->ip, $this->agent ) ) {
			$is = self::BAIDU;
		}
		elseif ( $srvProviders->isIp_BingBot( $this->ip, $this->agent ) ) {
			$is = self::BING;
		}
		elseif ( $srvProviders->isIp_Cloudflare( $this->ip ) ) {
			$is = self::CLOUDFLARE;
		}
		elseif ( $srvProviders->isIp_DuckDuckGoBot( $this->ip, $this->agent ) ) {
			$is = self::DUCKDUCKGO;
		}
		elseif ( $srvProviders->isIp_GoogleBot( $this->ip, $this->agent ) ) {
			$is = self::GOOGLE;
		}
		elseif ( $srvProviders->isIpInCollection( $this->ip, $srvProviders->getIpsForSlug( self::GTMETRIX ) ) ) {
			$is = self::GTMETRIX;
		}
		elseif ( $srvProviders->isIp_HuaweiBot( $this->ip, $this->agent ) ) {
			$is = self::HUAWEI;
		}
		elseif ( $srvProviders->isIp_iControlWP( $this->ip ) ) {
			$is = self::ICONTROLWP;
		}
		elseif ( $srvProviders->isIp_ManageWP( $this->ip ) ) {
			$is = self::MANAGEWP;
		}
		elseif ( $srvProviders->isIpInCollection( $this->ip, $srvProviders->getIps_NodePing() ) ) {
			$is = self::NODEPING;
		}
		elseif ( $srvProviders->isIp_PayPal( $this->ip ) ) {
			$is = self::PAYPAL;
		}
		elseif ( $srvProviders->isIp_Pingdom( $this->ip, $this->agent ) ) {
			$is = self::PINGDOM;
		}
		elseif ( $srvProviders->isIp_SemRush( $this->ip, $this->agent ) ) {
			$is = self::SEMRUSH;
		}
		elseif ( $srvProviders->isIp_Statuscake( $this->ip, $this->agent ) ) {
			$is = self::STATUSCAKE;
		}
		elseif ( $srvProviders->isIp_Stripe( $this->ip, $this->agent ) ) {
			$is = self::STRIPE;
		}
		elseif ( $srvProviders->isIp_UptimeRobot( $this->ip, $this->agent ) ) {
			$is = self::UPTIMEROBOT;
		}
		elseif ( $srvProviders->isIp_YahooBot( $this->ip, $this->agent ) ) {
			$is = self::YAHOO;
		}
		elseif ( $srvProviders->isIp_YandexBot( $this->ip, $this->agent ) ) {
			$is = self::YANDEX;
		}
		elseif ( $srvIP->checkIp( $this->ip, $srvIP->getServerPublicIPs() ) ) {
			$is = self::THIS_SERVER;
		}
		elseif ( $srvIP->checkIp( $this->ip, $srvIP->getRequestIp() ) ) {
			$is = self::VISITOR;
		}
		else {
			$is = self::UNKNOWN;
		}

		return [ $is => $this->getNames()[ $is ] ];
	}

	public function getNames() :array {
		return [
			self::UNKNOWN     => 'Unknown',
			self::THIS_SERVER => 'This Server',
			self::VISITOR     => 'You',
			self::APPLE       => 'AppleBot',
			self::BAIDU       => 'BaiduBot',
			self::BING        => 'BingBot',
			self::CLOUDFLARE  => 'CloudFlare',
			self::DUCKDUCKGO  => 'DuckDuckGoBot',
			self::GOOGLE      => 'GoogleBot',
			self::HUAWEI      => 'Huawei/PetalBot',
			self::GTMETRIX    => 'GTMetrix',
			self::ICONTROLWP  => 'iControlWP',
			self::MANAGEWP    => 'ManageWP',
			self::NODEPING    => 'NodePing',
			self::PAYPAL      => 'PayPal',
			self::PINGDOM     => 'Pingdom',
			self::SEMRUSH     => 'SEMRush',
			self::STATUSCAKE  => 'StatusCake',
			self::STRIPE      => 'Stripe',
			self::UPTIMEROBOT => 'UptimeRobot',
			self::YAHOO       => 'YahooBot',
			self::YANDEX      => 'YandexBot',
		];
	}

	public function getName( string $id ) :string {
		return $this->getNames()[ $id ];
	}
}