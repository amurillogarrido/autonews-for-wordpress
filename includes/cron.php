<?php
/**
 * Archivo: cron.php
 * Ubicación: includes/cron.php
 * Descripción: Funciones para definir intervalos personalizados de cron por feed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Agrega intervalos personalizados de cron según la configuración de cada feed.
 */
function dsrw_add_custom_cron_intervals( $schedules ) {

    // Todos los intervalos posibles que un feed puede usar
    $all_intervals = array(
        '30'   => array( 'interval' => 30 * MINUTE_IN_SECONDS,   'display' => __( 'Cada 30 Minutos', 'autonews-rss-rewriter' ) ),
        '60'   => array( 'interval' => 60 * MINUTE_IN_SECONDS,   'display' => __( 'Cada Hora', 'autonews-rss-rewriter' ) ),
        '120'  => array( 'interval' => 120 * MINUTE_IN_SECONDS,  'display' => __( 'Cada 2 Horas', 'autonews-rss-rewriter' ) ),
        '180'  => array( 'interval' => 180 * MINUTE_IN_SECONDS,  'display' => __( 'Cada 3 Horas', 'autonews-rss-rewriter' ) ),
        '360'  => array( 'interval' => 360 * MINUTE_IN_SECONDS,  'display' => __( 'Cada 6 Horas', 'autonews-rss-rewriter' ) ),
        '720'  => array( 'interval' => 720 * MINUTE_IN_SECONDS,  'display' => __( 'Cada 12 Horas', 'autonews-rss-rewriter' ) ),
        '1440' => array( 'interval' => 1440 * MINUTE_IN_SECONDS, 'display' => __( 'Cada 24 Horas', 'autonews-rss-rewriter' ) ),
    );

    // Registrar solo los que estén en uso por algún feed
    $feed_cron_intervals = get_option( 'dsrw_feed_cron_intervals', array() );
    if ( is_array( $feed_cron_intervals ) ) {
        foreach ( $feed_cron_intervals as $index => $interval ) {
            if ( isset( $all_intervals[ $interval ] ) ) {
                $key = 'dsrw_interval_' . $interval;
                if ( ! isset( $schedules[ $key ] ) ) {
                    $schedules[ $key ] = $all_intervals[ $interval ];
                }
            }
        }
    }

    return $schedules;
}
add_filter( 'cron_schedules', 'dsrw_add_custom_cron_intervals' );

/**
 * Activa el plugin. Programa los crons individuales de cada feed.
 */
function dsrw_activate_plugin() {
    dsrw_schedule_feed_crons();
    dsrw_write_log( "[AutoNews] Plugin activado. Crons de feeds programados." );
}

/**
 * Desactiva el plugin. Limpia todos los crons.
 */
function dsrw_deactivate_plugin() {
    // Limpiar cron global antiguo por si existiera
    wp_clear_scheduled_hook( 'dsrw_cron_hook' );

    // Limpiar todos los crons individuales
    $rss_urls_raw = get_option( 'dsrw_rss_urls', '' );
    $rss_urls = array_filter( array_map( 'trim', explode( "\n", $rss_urls_raw ) ) );
    foreach ( $rss_urls as $index => $url ) {
        wp_clear_scheduled_hook( 'dsrw_feed_cron_hook_' . $index );
    }
    dsrw_write_log( "[AutoNews] Plugin desactivado. Todos los crons eliminados." );
}

/**
 * Wrapper para ejecutar un feed individual desde su propio CRON.
 */
function dsrw_cron_execute_single_feed( $feed_index ) {
    $feed_index = intval( $feed_index );
    
    dsrw_write_log( '[AutoNews CRON] ========================================' );
    dsrw_write_log( '[AutoNews CRON] Ejecutando feed #' . $feed_index . ' - ' . current_time( 'mysql' ) );
    
    if ( ! did_action( 'init' ) ) {
        dsrw_write_log( '[AutoNews CRON] WordPress no está completamente cargado.' );
        return;
    }
    
    if ( ! function_exists( 'wp_insert_post' ) || ! function_exists( 'update_post_meta' ) ) {
        dsrw_write_log( '[AutoNews CRON] Funciones de WordPress no disponibles.' );
        return;
    }
    
    if ( ! function_exists( 'media_handle_sideload' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
    }
    
    wp_cache_flush();
    
    try {
        dsrw_process_feed_by_index( $feed_index );
        dsrw_write_log( '[AutoNews CRON] ✅ Feed #' . $feed_index . ' completado' );
    } catch ( Exception $e ) {
        dsrw_write_log( '[AutoNews CRON] ❌ Error feed #' . $feed_index . ': ' . $e->getMessage() );
    }
    
    wp_cache_flush();
    dsrw_write_log( '[AutoNews CRON] ========================================' );
}

/**
 * Registrar hooks para cada cron individual de feed.
 */
function dsrw_register_feed_cron_hooks() {
    $rss_urls_raw = get_option( 'dsrw_rss_urls', '' );
    $rss_urls = array_filter( array_map( 'trim', explode( "\n", $rss_urls_raw ) ) );
    
    foreach ( $rss_urls as $index => $url ) {
        add_action( 'dsrw_feed_cron_hook_' . $index, 'dsrw_cron_execute_single_feed' );
    }
}
add_action( 'init', 'dsrw_register_feed_cron_hooks' );

/**
 * Programa o reprograma los crons individuales de cada feed.
 */
function dsrw_schedule_feed_crons() {
    $rss_urls_raw = get_option( 'dsrw_rss_urls', '' );
    $rss_urls = array_filter( array_map( 'trim', explode( "\n", $rss_urls_raw ) ) );
    $feed_cron_intervals = get_option( 'dsrw_feed_cron_intervals', array() );
    
    if ( ! is_array( $feed_cron_intervals ) ) {
        $feed_cron_intervals = array();
    }
    
    foreach ( $rss_urls as $index => $url ) {
        $hook_name = 'dsrw_feed_cron_hook_' . $index;
        $interval = isset( $feed_cron_intervals[ $index ] ) ? $feed_cron_intervals[ $index ] : 'disabled';
        
        // Limpiar siempre primero
        wp_clear_scheduled_hook( $hook_name );
        
        // Solo programar si no es disabled
        if ( $interval !== 'disabled' && ! empty( $interval ) ) {
            $schedule_key = 'dsrw_interval_' . $interval;
            $result = wp_schedule_event( time(), $schedule_key, $hook_name, array( $index ) );
            if ( $result ) {
                dsrw_write_log( '[AutoNews] ✅ Cron feed #' . $index . ' programado cada ' . $interval . ' min' );
            } else {
                dsrw_write_log( '[AutoNews] ❌ Error programando cron feed #' . $index );
            }
        }
    }
}