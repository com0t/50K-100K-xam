<?php

namespace FernleafSystems\Wordpress\Services\Core;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Response
 * @package FernleafSystems\Wordpress\Services\Core
 */
class Response {

	/**
	 * Response constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function cookieDelete( $key ) {
		unset( $_COOKIE[ $key ] );
		return $this->cookieSet( $key, '', -1000000 );
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @param int    $duration
	 * @param null   $path
	 * @param null   $domain
	 * @param bool   $ssl
	 * @return bool
	 */
	public function cookieSet( $key, $value, $duration = 3600, $path = null, $domain = null, $ssl = null ) {
		if ( function_exists( 'headers_sent' ) && headers_sent() ) {
			return false;
		}
		$_COOKIE[ $key ] = $value;
		return setcookie(
			$key,
			$value,
			(int)( Services::Request()->ts() + $duration ),
			( is_null( $path ) && defined( 'COOKIEPATH' ) ) ? COOKIEPATH : $path,
			( is_null( $domain ) && defined( 'COOKIE_DOMAIN' ) ) ? COOKIE_DOMAIN : $domain,
			is_null( $ssl ) ? ( is_ssl() ? true : false ) : $ssl
		);
	}

	/**
	 * @param string $content
	 * @param string $filename
	 * @return bool
	 */
	public function downloadStringAsFile( $content, $filename ) {
		header( "Content-type: application/octet-stream" );
		header( "Content-disposition: attachment; filename=".$filename );
		header( "Content-Transfer-Encoding: binary" );
		header( "Content-Length: ".strlen( $content ) );
		echo $content;
		die();
	}

	/**
	 * @param string $url
	 * @param array  $queryParams
	 * @param bool   $safe
	 * @param bool   $bProtectAgainstInfiniteLoops - if false, ignores the redirect loop protection
	 */
	public function redirect( $url, $queryParams = [], $safe = true, $bProtectAgainstInfiniteLoops = true ) {
		$url = empty( $queryParams ) ? $url : add_query_arg( $queryParams, $url );

		// we prevent any repetitive redirect loops
		if ( $bProtectAgainstInfiniteLoops ) {
			if ( Services::Request()->cookie( 'icwp-isredirect' ) == 'yes' ) {
				return;
			}
			else {
				$this->cookieSet( 'icwp-isredirect', 'yes', 7 );
			}
		}

		// based on: https://make.wordpress.org/plugins/2015/04/20/fixing-add_query_arg-and-remove_query_arg-usage/
		// we now escape the URL to be absolutely sure since we can't guarantee the URL coming through there
		$url = esc_url_raw( $url );
		header( 'Cache-Control: no-store, no-cache' );
		$safe ? wp_safe_redirect( $url ) : wp_redirect( $url );
		exit();
	}

	/**
	 * @param array $aQueryParams
	 */
	public function redirectHere( $aQueryParams = [] ) {
		$this->redirect( Services::Request()->getUri(), $aQueryParams );
	}

	/**
	 * @param array $aQueryParams
	 */
	public function redirectToLogin( $aQueryParams = [] ) {
		$this->redirect( wp_login_url(), $aQueryParams );
	}

	/**
	 * @param array $aQueryParams
	 */
	public function redirectToAdmin( $aQueryParams = [] ) {
		$this->redirect( is_multisite() ? get_admin_url() : admin_url(), $aQueryParams );
	}

	/**
	 * @param array $aQueryParams
	 */
	public function redirectToHome( $aQueryParams = [] ) {
		$this->redirect( home_url(), $aQueryParams );
	}

	/**
	 * @param string $sRequestedUriPath
	 * @param string $sHostName - you can also send a full and valid URL
	 */
	public function sendApache404( $sRequestedUriPath = '', $sHostName = '' ) {
		$oReq = Services::Request();
		if ( empty( $sRequestedUriPath ) ) {
			$sRequestedUriPath = $oReq->getUri();
		}

		if ( empty( $sHostName ) ) {
			$sHostName = $oReq->server( 'SERVER_NAME' );
		}
		elseif ( filter_var( $sHostName, FILTER_VALIDATE_URL ) ) {
			$sHostName = parse_url( $sRequestedUriPath, PHP_URL_HOST );
		}

		$bSsl = is_ssl() || $oReq->server( 'HTTP_X_FORWARDED_PROTO' ) == 'https';
		header( 'HTTP/1.1 404 Not Found' );

		$nPort = $bSsl ? 443 : (int)$oReq->server( 'SERVER_PORT' );
		$sDie = sprintf(
			'<html><head><title>404 Not Found</title><style type="text/css"></style></head><body><h1>Not Found</h1><p>The requested URL %s was not found on this server.</p><p>Additionally, a 404 Not Found error was encountered while trying to use an ErrorDocument to handle the request.</p><hr><address>Apache Server at %s Port %s</address></body></html>',
			preg_replace( '#[^a-z0-9_&;=%/-]#i', '', esc_html( $sRequestedUriPath ) ),
			$sHostName,
			empty( $nPort ) ? 80 : $nPort
		);
		die( $sDie );
	}
}