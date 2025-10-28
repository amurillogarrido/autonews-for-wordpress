<?php
/**
 * Archivo: utilities.php
 * Ubicación: includes/utilities.php
 * Descripción: Funciones auxiliares (utilities) para el plugin, como validaciones de URL,
 *              obtención de etiquetas de intervalos, etc.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita el acceso directo.
}

/**
 * Valida y sanitiza la lista de URLs RSS introducidas por el usuario en las opciones.
 *
 * @param string $input Cadena con las URLs separadas por saltos de línea.
 * @return string Cadena con las URLs válidas, separadas por saltos de línea.
 */
function dsrw_validate_rss_urls( $input ) {
    // Convierte la cadena en un array de URLs, filtrando espacios en blanco
    $urls = array_filter( array_map( 'trim', explode( "\n", $input ) ) );
    $valid_urls = array();

    // Valida cada URL con filter_var() y descarta las inválidas
    foreach ( $urls as $url ) {
        if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
            $valid_urls[] = $url;
        } else {
            // Escribe en el log si la URL no es válida
            dsrw_write_log( "[AutoNews RSS Rewriter] " . __( 'URL RSS inválida ignorada: ', 'autonews-rss-rewriter' ) . $url );
        }
    }

    // Convierte el array resultante de nuevo en una cadena de texto separada por saltos de línea
    return implode( "\n", $valid_urls );
}

/**
 * Devuelve la etiqueta de intervalo en función del valor seleccionado en las opciones.
 *
 * @param string $interval Cadena que indica los minutos (e.g., '30', '60', '120', 'disabled').
 * @return string Etiqueta descriptiva del intervalo (en minutos u horas).
 */
function dsrw_get_cron_interval_label( $interval ) {
    $labels = array(
        '30'    => __( '30 minutos', 'autonews-rss-rewriter' ),
        '60'    => __( '1 hora', 'autonews-rss-rewriter' ),
        '120'   => __( '2 horas', 'autonews-rss-rewriter' ),
        '180'   => __( '3 horas', 'autonews-rss-rewriter' ),
        '600'   => __( '10 horas', 'autonews-rss-rewriter' ),
        '720'   => __( '12 horas', 'autonews-rss-rewriter' ),
        '1440'  => __( '24 horas', 'autonews-rss-rewriter' ),
    );

    return isset( $labels[ $interval ] )
        ? $labels[ $interval ]
        : __( 'Intervalo Personalizado', 'autonews-rss-rewriter' );
}