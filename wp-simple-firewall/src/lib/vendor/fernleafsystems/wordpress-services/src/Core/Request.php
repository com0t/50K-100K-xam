<?php

namespace FernleafSystems\Wordpress\Services\Core;

use Carbon\Carbon;
use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Request
 * @package FernleafSystems\Wordpress\Services\Core
 * @property array $post
 * @property array $query
 * @property array $cookie
 * @property array $cookie_copy
 * @property array $server
 * @property array $env
 */
class Request extends DynPropertiesClass {

	/**
	 * @var int
	 */
	private $nTs;

	/**
	 * @var float
	 */
	private $nMts;

	/**
	 * @var string
	 */
	private $content;

	/**
	 * Request constructor.
	 */
	public function __construct() {
		$this->post = is_array( $_POST ) ? $_POST : [];
		$this->query = is_array( $_GET ) ? $_GET : [];
		$this->cookie_copy = is_array( $_COOKIE ) ? $_COOKIE : [];
		$this->server = is_array( $_SERVER ) ? $_SERVER : [];
		$this->env = is_array( $_ENV ) ? $_ENV : [];
		$this->ts();
	}

	public function __get( string $key ) {
		switch ( $key ) {
			case 'cookie':
				$value = is_array( $_COOKIE ) ? $_COOKIE : [];
				break;
			default:
				$value = parent::__get( $key );
				break;
		}
		return $value;
	}

	public function getContent() :string {
		if ( !isset( $this->content ) ) {
			$this->content = file_get_contents( 'php://input' );
		}
		return (string)$this->content;
	}

	public function getMethod() :string {
		$method = (string)$this->server( 'REQUEST_METHOD' );
		return empty( $method ) ? '' : strtolower( $method );
	}

	/**
	 * @param bool $bMsOnly
	 * @return int
	 */
	public function mts( $bMsOnly = false ) {
		$now = $this->ts();
		if ( empty( $this->nMts ) ) {
			$now = $bMsOnly ? 0 : $now;
		}
		else {
			$now = $bMsOnly ? preg_replace( '#^[0-9]+\.#', '', $this->nMts ) : $this->nMts;
		}
		return $now;
	}

	public function ts() :int {
		if ( empty( $this->nTs ) ) {
			$this->nTs = time();
			$this->nMts = function_exists( 'microtime' ) ? @microtime( true ) : false;
		}
		return $this->nTs;
	}

	/**
	 * @param bool $bSetTimezone - useful only when you're reporting times or displaying
	 * @return Carbon
	 */
	public function carbon( $bSetTimezone = false ) {
		$WP = Services::WpGeneral();
		$carbon = new Carbon();
		$carbon->setTimestamp( $this->ts() );
		$carbon->setLocale( $WP->getLocaleCountry() );
		if ( $bSetTimezone ) {
			$TZ = $WP->getOption( 'timezone_string' );
			if ( !empty( $TZ ) ) {
				$carbon->setTimezone( $TZ );
			}
		}
		return $carbon;
	}

	public function getRawRequestParams( bool $includeCookies = true ) :array {
		$params = array_merge( $this->query, $this->post );
		return $includeCookies ? array_merge( $params, $this->cookie ) : $params;
	}

	public function getHost() :string {
		return (string)$this->server( 'HTTP_HOST' );
	}

	public function getPath() :string {
		return $this->getUriParts()[ 'path' ];
	}

	public function getServerAddress() :string {
		return (string)$this->server( 'SERVER_ADDR' );
	}

	public function getUri() :string {
		return (string)$this->server( 'REQUEST_URI' );
	}

	public function getUriParts() :array {
		$path = $this->getUri();
		if ( strpos( $path, '?' ) !== false ) {
			list( $path, $query ) = explode( '?', $path, 2 );
		}
		else {
			$query = '';
		}
		return [
			'path'  => $path,
			'query' => $query,
		];
	}

	public function getUserAgent() :string {
		return (string)$this->server( 'HTTP_USER_AGENT' );
	}

	public function isGet() :bool {
		return $this->getMethod() == 'get';
	}

	public function isPost() :bool {
		return $this->getMethod() == 'post';
	}

	public function countQuery() :int {
		return count( $this->query );
	}

	public function countPost() :int {
		return count( $this->post );
	}

	/**
	 * @param string $key
	 * @param null   $default
	 * @return mixed|null
	 */
	public function cookie( $key, $default = null ) {
		return $this->fetch( $this->cookie, $key, $default );
	}

	/**
	 * @param string $key
	 * @param null   $default
	 * @return mixed|null
	 */
	public function env( $key, $default = null ) {
		return $this->fetch( $this->env, $key, $default );
	}

	/**
	 * @param string $key
	 * @param null   $default
	 * @return mixed|null
	 */
	public function post( $key, $default = null ) {
		return $this->fetch( $this->post, $key, $default );
	}

	/**
	 * @param string $key
	 * @param null   $default
	 * @return mixed|null
	 */
	public function query( $key, $default = null ) {
		return $this->fetch( $this->query, $key, $default );
	}

	/**
	 * POST > GET > COOKIE
	 * @param string $key
	 * @param bool   $includeCookies
	 * @param null   $default
	 * @return mixed|null
	 */
	public function request( $key, $includeCookies = false, $default = null ) {
		$value = $this->post( $key );
		if ( is_null( $value ) ) {
			$value = $this->query( $key );
			if ( $includeCookies && is_null( $value ) ) {
				$value = $this->cookie( $key );
			}
		}
		return is_null( $value ) ? $default : $value;
	}

	/**
	 * @param string $key
	 * @param null   $default
	 * @return mixed|null
	 */
	public function server( $key, $default = null ) {
		return $this->fetch( $this->server, $key, $default );
	}

	/**
	 * @param array  $container
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed|null
	 */
	private function fetch( array $container, $key, $default = null ) {
		$value = $container[ $key ] ?? $default;
		return is_null( $value ) ? $default : $value;
	}

	/**
	 * @param string $sContainer
	 * @param string $sKey
	 * @param mixed  $mDefault
	 * @return mixed|null
	 * @deprecated
	 */
	private function arrayFetch( $sContainer, $sKey, $mDefault = null ) {
		$sArray = 'a'.ucfirst( $sContainer );
		$aArray = $this->{$sArray};
		if ( is_null( $sKey ) || !isset( $aArray[ $sKey ] ) || !is_array( $aArray ) ) {
			return $mDefault;
		}
		return $aArray[ $sKey ];
	}

	/**
	 * @return int
	 * @deprecated
	 */
	public function time() {
		return $this->ts();
	}

	/**
	 * @param bool $bMicro
	 * @return int
	 * @deprecated
	 */
	public function getRequestTime( $bMicro = false ) {
		return $this->mts( $bMicro );
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function getRequestPath() {
		return $this->getPath();
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function getRequestUri() {
		return $this->server( 'REQUEST_URI', '' );
	}

	/**
	 * @return array|false
	 * @deprecated
	 */
	public function getRequestUriParts() {
		return $this->getUriParts();
	}

	/**
	 * @param string $sContainer
	 * @return int
	 * @deprecated
	 */
	private function count( $sContainer ) {
		$sArray = 'a'.$sContainer;
		$aArray = $this->{$sArray};
		return is_array( $aArray ) ? count( $aArray ) : 0;
	}
}