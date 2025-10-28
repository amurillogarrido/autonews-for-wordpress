=== AutoNews - AI News Rewriter ===
Contributors: Alberto Murillo
Tags: rss, ai, gpt, news, wordpress, automation
Requires at least: 5.6
Tested up to: 6.8
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Un plugin que reescribe automáticamente artículos de feeds RSS usando inteligencia artificial y los publica en tu sitio WordPress con contenido original.

== Description ==

AutoNews utiliza OpenAI para transformar contenidos de feeds RSS en artículos únicos, enriquecidos con HTML, categorías automáticas y miniaturas. Ideal para blogs automáticos, curación de contenidos o sitios de noticias.

== Features ==
* Reescritura de artículos con IA (OpenAI)
* Publicación automática programada
* Soporte multilingüe en los prompts
* Publicación de excerpt (resumen) generado por IA
* Extracción de imágenes o miniaturas generadas
* Configuración avanzada por categoría, autor y número de artículos

== Installation ==
1. Sube la carpeta `autonews` al directorio `/wp-content/plugins/`
2. Activa el plugin desde el menú "Plugins" en WordPress
3. Configura tus feeds y tu clave de OpenAI en los ajustes

== Changelog ==

= 1.1.0 =
* Añadido soporte para excerpts generados por IA.
* Actualizados los prompts en todos los idiomas.

= 1.0.0 =
* Versión inicial del plugin.

== Frequently Asked Questions ==

= ¿Puedo usar GPT-4? =
Sí, siempre que tengas acceso y tu clave sea válida.

= ¿Qué pasa si ya hay artículos duplicados? =
El plugin detecta enlaces repetidos mediante hash MD5 y evita re-publicarlos.

== Screenshots ==

1. Configuración del plugin en el panel de administración.
2. Lista de feeds RSS configurados.
