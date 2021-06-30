<?php


/* ======================================================================================
   @author     Carlos Doral Pérez (//webartesanal.com)
   @version    0.24
   @copyright  Copyright &copy; 2013-2014 Carlos Doral Pérez, All Rights Reserved
               License: GPLv2 or later
   ====================================================================================== */

/**
 *
 */
class cdp_cookies_error extends Exception
{
}

/**
 *
 */
class cdp_cookies_error_nonce extends cdp_cookies_error
{
	/**
	 * 
	 */
	function __construct()
	{
		parent::__construct( "Se ha producido un error de seguridad en este plugin" );
	}
}

/**
 *
 * Utilidad log
 *
 * Actualiz:
 *
 * 10-oct-2013 No arroja excepciones si no puede escribir en el archivo
 * 03-may-2013 Permite objetos en el metodo de array
 * 15-feb-2013 Adaptación a WP
 * 18-sep-2012 Cambio en los defines
 * 04-sep-2012 Renova y convertido a estático
 * 23-may-2012 Alerta de programación
 * 15-may-2012 Hora en formato 24h
 * 09-may-2012 Añado pon_sql
 * 20-mar-2012
 *
 * @copyright Carlos Doral Pérez
 * @version 0.42
 * @author Carlos Doral Pérez
 *
 */
class cdp_cookies_log
{
	/**
	 *
	 */
	static function pon( $texto )
	{
		global $config;
		if( !CDP_COOKIES_LOG_ACTIVO )
			return;
		if( ( $fi = @fopen( CDP_COOKIES_ARCHIVO_LOG, 'a' ) ) === false )
		{
			if( !@chmod( CDP_COOKIES_ARCHIVO_LOG, 0777 ) )
				return;
			if( ( $fi = @fopen( CDP_COOKIES_ARCHIVO_LOG, 'a' ) ) === false )
				return;
		}
		fwrite( $fi, '[' . date( 'Y-m-d H:i:s' ) . '] ' . $texto . "\n" );
		fclose( $fi );
	}

	/**
	 * Logea valores de un array asociativo
	 */
	static function pon_array( $array, $titulo )
	{
		if( is_object( $array ) )
			$array = ( array )$array;
		$str = "//\n// ARRAY $titulo\n";
		if( is_array( $array ) && count( $array ) > 0 )
		{
			foreach( $array as $k => $v )
				$str .= "[$k] = $v\n";
		}
		else
		{
			$str .= "-ninguno-\n";
		}
		self::pon( $str );
	}

	/**
	 * Logea el array $_POST
	 */
	static function post()
	{
		self::pon_array( isset( $_POST ) ? $_POST : null, 'POST' );
	}

	/**
	 * Logea el array $_GET
	 */
	static function get()
	{
		self::pon_array( isset( $_GET ) ? $_GET : null, 'GET' );
	}
}

/**
 *
 * Utilidad para dibujar fragmentos HTML
 *
 * Creada:		18-jun-2013
 *
 * Modif:
 *
 * @copyright Carlos Doral Pérez
 * @version 0.1
 * @author Carlos Doral Pérez
 *
 */
class cdp_cookies_html
{
	/**
	 *
	 */
	static function tags( $atributos = null )
	{
		if( $atributos == null )
			$atributos = array();
		$tags = '';
		foreach( $atributos as $k => $v )
		{
			if( ( trim( $k ) == 'style' ) && is_array( $v ) )
			{
				$t = array();
				foreach( $v as $a => $b )
					$t[] = "$a:$b";
				$v = implode( ';', $t );
			}
			$tags .= " $k=\"$v\"";
		}
		return $tags;
	}

	/**
	 *
	 */
	static function select
	(
		$nombre_post,
		array $items,
		$es_asoc,
		$valor_seleccionado,
		$atributos = null
	)
	{
		$options = '';
		foreach( $items as $k => $v )
		{
			if( !$es_asoc )
				$v = $k;
			$selected = $valor_seleccionado == $k ? ' selected' : '';
			$options .= "<option value=\"$k\"$selected>$v</option>";
		}
		$tags = self::tags( $atributos );
		return
			"<select id=\"$nombre_post\" name=\"$nombre_post\" $tags>" .
			$options .
			"</select>";
	}
}

/**
 *
 * Creada		: 13-jun-13
 * 
 * Modificada
 * 				: 10-oct-13 Nuevos métodos
 * 				: 14-jun-13
 * 
 * Versión		: 0.13
 *
 */
class cdp_cookies_input
{
	/**
	 *
	 */
	static function get( $nombre_var, $valor_sino_existe = null )
	{
		if( isset( $_GET ) && isset( $_GET[$nombre_var] ) )
			return $_GET[$nombre_var];
		return $valor_sino_existe;
	}

	/**
	 *
	 */
	static function post( $nombre_var, $valor_sino_existe = null )
	{
		if( isset( $_POST ) && isset( $_POST[$nombre_var] ) )
			return $_POST[$nombre_var];
		return $valor_sino_existe;
	}

	/**
	 *
	 */
	static function objeto_post( $lista_vars )
	{
		$obj = new stdClass();
		foreach( $lista_vars as $var )
			$obj->$var = self::post( $var );
		return $obj;
	}
	
	/**
	 * 
	 */
	static function validar_array( $nombre_var, $array, $filtrar_previamente = true )
	{
		if( !isset( $_POST[$nombre_var] ) )
			throw new cdp_cookies_error( sprintf( __( "Se requiere un valor para %s" ), $nombre_var ) );
		if( $filtrar_previamente )
			$_POST[$nombre_var] = sanitize_text_field( $_POST[$nombre_var] );
		if( !in_array( $_POST[$nombre_var], $array ) )
			throw new cdp_cookies_error( sprintf( __( "Valor incorrecto para la lista %s" ), $nombre_var ) );
	}

	/**
	 * 
	 */
	static function validar_url( $nombre_var, $filtrar_previamente = true )
	{
		if( !isset( $_POST[$nombre_var] ) )
			throw new cdp_cookies_error( sprintf( __( "Se requiere un valor para %s" ), $nombre_var ) );
		if( $filtrar_previamente )
			$_POST[$nombre_var] = sanitize_text_field( $_POST[$nombre_var] );
		if( !preg_match( '|^http(s?)://|i', $_POST[$nombre_var] ) )
			throw new cdp_cookies_error( sprintf( __( "Valor incorrecto para la url %s" ), $nombre_var ) );
	}
}

?>