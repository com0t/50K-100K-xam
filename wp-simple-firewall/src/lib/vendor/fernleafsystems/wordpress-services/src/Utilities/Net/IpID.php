<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Services\Utilities\Net;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;
use FernleafSystems\Wordpress\Services\Utilities\ServiceProviders;

class IpID {

	const UNKNOWN = 'unknown';
	const VISITOR = 'visitor';
	const THIS_SERVER = 'server';

	/**
	 * @var string
	 */
	private $ip;

	/**
	 * @var string|null
	 */
	private $agent;

	private $ignoreUserAgentInChecks = false;

	public function __construct( string $ip, $agent = null ) {
		$this->ip = $ip;
		$this->agent = $agent;
	}

	public function setIgnoreUserAgent( bool $ignore = true ) {
		$this->ignoreUserAgentInChecks = $ignore;
		return $this;
	}

	/**
	 * @return string[]
	 * @throws \Exception
	 */
	public function run() :array {
		$srvIP = Services::IP();
		if ( !$srvIP->isValidIp( $this->ip ) ) {
			throw new \Exception( "A valid IP address was not provided." );
		}

		$theSlug = null;
		$theName = null;

		$bots = ServiceProviders::GetProviderIPs();
		if ( empty( $bots[ 'services' ] ) || empty( $bots[ 'crawlers' ] ) ) {
			throw new \Exception( 'Could not request Provider IPs' );
		}

		foreach ( $bots[ 'services' ] as $slug => $provider ) {
			// For "services" we don't need to verify the agent as the IPs are fixed.
			if ( $this->checkServiceProvider( $provider ) ) {
				$theSlug = $slug;
				$theName = $provider[ 'name' ];
				break;
			}
		}

		if ( empty( $theSlug ) ) {
			foreach ( $bots[ 'crawlers' ] as $slug => $crawler ) {
				if ( $this->checkCrawler( $slug, $crawler ) ) {
					$theSlug = $slug;
					$theName = $crawler[ 'name' ];
					break;
				}
			}
		}

		if ( empty( $theSlug ) ) {
			if ( $srvIP->checkIp( $this->ip, $srvIP->getServerPublicIPs() ) ) {
				$theSlug = self::THIS_SERVER;
				$theName = 'This Server';
			}
			elseif ( $srvIP->checkIp( $this->ip, $srvIP->getRequestIp() ) ) {
				$theSlug = self::VISITOR;
				$theName = 'You';
			}
			else {
				$theSlug = self::UNKNOWN;
				$theName = 'Unknown';
			}
		}

		return [ $theSlug, $theName ];
	}

	private function checkCrawler( string $slug, array $crawlerSpec ) :bool {

		$IPs = Transient::Get( 'apto_serviceips_'.$slug );
		if ( !is_array( $IPs ) ) {
			$IPs = [];
		}

		// Only verify IP if the UserAgent is provided.
		if ( $this->verifyAgent( $crawlerSpec ) && !in_array( $this->ip, $IPs ) ) {
			$host = @gethostbyaddr( $this->ip ); // returns the ip on failure
			$isBot = !empty( $host ) && ( $host !== $this->ip )
					 && preg_match( $crawlerSpec[ 'host_pattern' ], $host )
					 && gethostbyname( $host ) === $this->ip;

			if ( $isBot ) {
				$IPs[] = $this->ip;
				Transient::Set( 'apto_serviceips_'.$slug, $IPs, WEEK_IN_SECONDS*4 );
			}
		}

		return in_array( $this->ip, $IPs );
	}

	private function checkServiceProvider( array $providerData ) :bool {
		$SP = Services::ServiceProviders();
		return $SP->isIpInCollection( $this->ip, $providerData[ 'ips' ] );
	}

	private function verifyAgent( array $data ) :bool {
		$agentValid = $this->ignoreUserAgentInChecks;

		if ( empty( $data[ 'agents' ] ) ) {
			$agentValid = true; // since we can't verify agents if there's none to test.
		}
		elseif ( !empty( $this->agent ) ) {
			foreach ( $data[ 'agents' ] as $agent ) {
				if ( stripos( $this->agent, $agent ) !== false ) {
					$agentValid = true;
					break;
				}
			}
		}

		return $agentValid;
	}
}