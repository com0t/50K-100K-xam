=== Asesor de Cookies RGPD para normativa europea ===
Contributors: Carlos Doral Pérez (<a href="https://webartesanal.com">webartesanal.com</a>)
Tags: rgpd, cookie, cookies, spain, ley, law, politica, policy, españa, normativa
Requires at least: 3.5
Tested up to: 5.7
Stable tag: 0.31
License: GPLv2 or later

Este plugin le facilita la adaptación a la RGPD de su web a la política de cookies mostrando el aviso a los visitantes de su página y proporcionándole los textos legales y técnicos iniciales.

== Description == 

> **[Para más información visite Web Artesanal](https://webartesanal.com/)**

El funcionamiento consiste en mostrar un mensaje cada vez que un nuevo usuario visita su web avisándole que si pulsa el botón ACEPTAR consiente la instalación de cookies en su navegador web.

Además este plugin le proporciona los textos legales y técnicos iniciales para confeccionar su política de cookies, se generan automáticamente y los puede editar si lo desea.

Características del plugin:

* Elección del estilo de la ventana del aviso, color, tamaño de fuente, etc.
* Elección de la posición de la solapa u ocultación de la misma.
* Creación automática de las dos páginas con los textos legales y técnicos iniciales que necesita su web: La política de cookies y la descripción coloquial de cookies para los usuarios web. Las páginas son editables.
 
== Screenshots ==

1. Este es el aviso de cookies mostrado al visitante web por primera vez.
2. Esta es la solapa flotante que aparecerá en la parte inferior de su página.
3. Panel de configuración que permite cambiar los colores, posición del aviso, etc.

== Installation ==

1. Descargue el plugin, descomprímalo y súbalo al directorio /wp-content/plugins/
2. Vaya al apartado plugins y active el Asesor de Cookies.
3. Vaya a Herramientas, Asesor de Cookies.
4. Pinche el botón 'Generar Páginas' y luego 'Guardar'.
5. El plugin ya está funcionando con los textos legales por defecto. Si quiere editarlos vaya a Páginas y ahí verá las dos nuevas páginas que ha creado el plugin, la de 'Política de cookies' y la de 'Más información sobre las cookies' que es totalmente técnica y no tendrá que modificar.
6. Es conveniente que añada en su menú o en el pié de página de su web un enlace 'Política de cookies' visible que debe apuntar a la página que ha creado sobre la política de cookies.

Si lo desea, como método alternativo de instalación puede ir a la sección Plugins y hacer lo siguiente:

1. Pulse 'Añadir nuevo'.
2. En el buscador escriba 'asesor cookies'.
3. Haga click en 'Instalar'.
4. Ahora siga desde el paso 2 de la sección anterior.

== Changelog ==

= 0.31 =
* Fallo svn

= 0.30 = 
* Fallo en estilos

= 0.29 =
* Probado en WP 5.3.2
* Fuera publi

= 0.28 =
* Probado en WP 5.0.1

= 0.27 =
* Testeo en versiones modernas WP

= 0.26 =
* Problemas al publicar con svn

= 0.25 =
* Ocultación de solapa por petición de usuarios
 
= 0.24 =
* Problemas al publicar con svn

= 0.23 =
* Problemas al publicar con svn

= 0.22 =
* Ahora el aviso de cookies siempre aparece flotante y en la parte inferior, se eliminan opciones como ponerlo en la parte superior, añadirlo al body como parte del contenido, elección del tipo de botón (cerrar, aceptar) y otras opciones que complicaban la configuración.
* Se añade solapa permanente para mostrar el aviso de cookies en cualquier momento.
* Se elimina la opción de dar el consentimiento de forma automática, ahora el visitante siempre debe pulsar el botón ACEPTAR.

= 0.21 =
* Corregido error que hacía desaparecer los enlaces del resto de plugins.

= 0.20 =
* No se veía la ventana con algunos temas WordPress como Divi

= 0.19 =
* Se añade botón Configuración en la página de plugins para acceder directamente a la configuración del Asesor de Cookies.
* Se elimina una petición ajax al servidor por generar problemas en algunas instalaciones WP.
* Se combinan los 3 archivos JS en uno sólo para mejorar el rendimiento.
* Se arregla la previsualización que no funcionaba correctamente.
* Se resuelve problema cuando hay dos instalaciones WP en el mismo dominio y anidadas. Gracias Mikel!
* Detalles CSS

= 0.18 =
* En algunas instalaciones se producian definiciones duplicadas en traer_aviso.php. Gracias a Mikel Gutierrez por su soporte.
* Se renuevan banners.

= 0.17 =
* Errores al subir al repositorio svn.

= 0.16 =
* Errores al subir al repositorio svn.

= 0.15 =
* Validación W3C, la inclusión de CSS no validaba, gracias por avisar Julio!
* El plugin ahora funciona correctamente si el directorio de administración WP tiene protección .htaccess. Gracias a Antonio Rodríguez por avisar.
* Banner superior en admin.

= 0.14 =
* Opción a incluir un botón CERRAR o ACEPTAR en el aviso.
* Pequeños detalles Javascript para prevención de conflictos con otros plugins.
* Algunos detalles en CSS
* Inclusión de enlace al plugin

= 0.13 =
* El texto del aviso ahora es editable.
* Se puede cambiar el tamaño de fuente.
* Corregido error que aparecía cuando un usuario no administrador entraba al back de WP.

= 0.12 =
* readme.txt actualizado y capturas de pantalla.

= 0.11 =
* Versión inicial.

== Troubleshooting ==

Si este plugin no te funciona correctamente prueba a hacer lo siguiente:
* Borra el caché de tu navegador, a veces se quedan versiones antiguas de archivos CSS y JS.
* Si utilizas algún sistema de caché en tu instalación WordPress prueba a borrar dicho caché.

Si te sigue fallando puede ser porque otro plugin genere errores Javascript y esto impide el funcionamiento del Asesor de Cookies. Puedes probar a desactivar otros plugins para saber cuál está dando problemas.

**[Si tienes otros problemas intentaremos ayudarte si envías un correo desde nuestra web](//webartesanal.com/)**



