<?php

/*
Plugin Name: Asesor de Cookies RGPD
Plugin URI: https://webartesanal.com
Description: Este plugin avisa a los nuevos visitantes de su web sobre la utilización de cookies en su página y le proporciona los textos iniciales para que pueda crear una política de cookies correcta y facilitarle la adaptación de su web a la RGPD
Tags: rgpd, cookie, cookies, spain, ley, law, politica, policy, españa, normativa
Version: 0.31
Requires at least: 3.5
Tested up to: 5.7
Author: Carlos Doral Pérez
Author URI: https://webartesanal.com
License: GPLv2 or later
*/ 
 
/*  Copyright 2013-2017 Carlos Doral Pérez

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

	// Configuración y definiciones
	require dirname( __FILE__ ) . '/config.php';
	require dirname( __FILE__ ) . '/lib/lib.php';
	require dirname( __FILE__ ) . '/lib/plugin.php';
	
	// Lógica del plugin
	try 
	{
		cdp_cookies::ejecutar();
	}
	catch( cdp_cookies_error $e )
	{
		cdp_cookies_log::pon( $e->getMessage() );
	}

?>