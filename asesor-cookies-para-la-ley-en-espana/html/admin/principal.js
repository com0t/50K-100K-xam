
/* ======================================================================================
   @author     Carlos Doral Pérez (http://webartesanal.com)
   @version    0.24
   @copyright  Copyright &copy; 2013 Carlos Doral Pérez, All Rights Reserved
               License: GPLv2 or later
   ====================================================================================== */
   
//
function cdp_cookies_mensaje( texto, clase )
{
	jQuery( '.cdp-cookies-mensajes' ).removeClass( 'error' ).addClass( clase );
	jQuery( '.cdp-cookies-mensajes' ).html( texto ).fadeIn( 500 ).delay( 2000 ).fadeOut( 500 );
}

//
function cdp_cookies_mensaje_error( texto )
{
	cdp_cookies_mensaje( texto, 'error' );
}

//
function guardar()
{ 
	//
	var datos = {
		action: 'guardar_config',
		texto_aviso: jQuery( '#texto_aviso' ).val(),
		tam_fuente: jQuery( '#tam_fuente' ).val(),
		posicion_solapa: jQuery( '#posicion_solapa' ).val(),
		alineacion: jQuery( '#alineacion' ).val(),
		tema: jQuery( '#tema:checked' ).val(),
		enlace_politica: jQuery( '#enlace_politica' ).val(),
		enlace_mas_informacion: jQuery( '#enlace_mas_informacion' ).val(),
		nonce_guardar: cdp_cookies_info.nonce_guardar
	};

	//
	jQuery.post( ajaxurl, datos, function( resul ) {
		if( resul.ok )
			cdp_cookies_mensaje( resul.txt );
		else
			cdp_cookies_mensaje_error( resul.txt );
	}, 'json' );
}

//
function crear_paginas()
{
	//
	var datos = {
		action: 'crear_paginas',
		nonce_crear_paginas : cdp_cookies_info.nonce_crear_paginas
	};

	//
	jQuery.post( ajaxurl, datos, function( resul ) {
		if( resul.ok )
		{
			cdp_cookies_mensaje( resul.txt );
			jQuery( '#enlace_mas_informacion' ).val( resul.url_info );
			jQuery( '#enlace_politica' ).val( resul.url_politica );
		}
		else
		{
			cdp_cookies_mensaje_error( resul.txt );
		}
	}, 'json' );
}

//
jQuery( document ).ready( function( $ ) {

	// Ocultar/mostrar instrucciones
	$( '.cdp-cookies-bot-instrucciones' ).click( function() {
		$( '.cdp-cookies-instrucciones' ).toggle();
	} );

	// Radios más fáciles de pinchar
	$( 'form .cdp-cookies-radio' ).click( function() {
		$( this ).find( 'input' ).attr( 'checked', true );
	} );

	// Guardar config
	$( 'a.cdp-cookies-guardar' ).click( function() {
		guardar();
	} );

	// Crear pág. política
	$( 'a.cdp-cookies-crear-politica' ).click( function() {
		crear_paginas();
	} );

	// Ver pág. más info
	$( 'a.cdp-cookies-ver-mas-info' ).click( function() {
		window.open( $( '#enlace_mas_informacion' ).val() );
	} );

	// Ver pág. politica
	$( 'a.cdp-cookies-ver-politica' ).click( function() {
		window.open( $( '#enlace_politica' ).val() );
	} );

} );