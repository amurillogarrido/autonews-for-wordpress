<?php
/**
 * Archivo: cron.php
 * Ubicación: includes/cron.php
 * Descripción: Funciones para definir intervalos personalizados de cron, activar y desactivar la tarea programada.
 *
 * Nota: Este archivo se incluye en el archivo principal (autonews.php) y depende de que
 * funciones como dsrw_write_log() estén ya definidas (por ejemplo, en includes/logs.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita el acceso directo.
}

/**
 * Agrega intervalos personalizados de cron según la configuración del plugin.
 *
 * @param array $schedules Arreglo de intervalos de cron existentes.
 * @return array Arreglo modificado con nuevos intervalos.
 */
function dsrw_add_custom_cron_intervals( $schedules ) {
    $cron_interval = sanitize_text_field( get_option( 'dsrw_cron_interval', 'disabled' ) );

    switch ( $cron_interval ) {
        case '30':
            $schedules['dsrw_interval_30'] = array(
                'interval' => 30 * MINUTE_IN_SECONDS,
                'display'  => __( 'Cada 30 Minutos', 'autonews-rss-rewriter' ),
            );
            break;
        case '60':
            $schedules['dsrw_interval_60'] = array(
                'interval' => 60 * MINUTE_IN_SECONDS,
                'display'  => __( 'Cada Hora', 'autonews-rss-rewriter' ),
            );
            break;
        case '120':
            $schedules['dsrw_interval_120'] = array(
                'interval' => 120 * MINUTE_IN_SECONDS,
                'display'  => __( 'Cada 2 Horas', 'autonews-rss-rewriter' ),
            );
            break;
        case '180':
            $schedules['dsrw_interval_180'] = array(
                'interval' => 180 * MINUTE_IN_SECONDS,
                'display'  => __( 'Cada 3 Horas', 'autonews-rss-rewriter' ),
            );
            break;
        case '360':
            $schedules['dsrw_interval_360'] = array(
                'interval' => 360 * MINUTE_IN_SECONDS,
                'display'  => __( 'Cada 6 Horas', 'autonews-rss-rewriter' ),
            );
            break;
        case '720':
            $schedules['dsrw_interval_720'] = array(
                'interval' => 720 * MINUTE_IN_SECONDS,
                'display'  => __( 'Cada 12 Horas', 'autonews-rss-rewriter' ),
            );
            break;
        case '1440':
            $schedules['dsrw_interval_1440'] = array(
                'interval' => 1440 * MINUTE_IN_SECONDS,
                'display'  => __( 'Cada 24 Horas', 'autonews-rss-rewriter' ),
            );
            break;
    }

    // --- CRONS INDIVIDUALES POR FEED ---
    // Registrar también los intervalos de los feeds individuales
    $feed_cron_intervals = get_option( 'dsrw_feed_cron_intervals', array() );
    if ( is_array( $feed_cron_intervals ) ) {
        foreach ( $feed_cron_intervals as $index => $interval ) {
            if ( ! empty( $interval ) && $interval !== 'global' && $interval !== 'disabled' ) {
                $key = 'dsrw_interval_' . $interval;
                if ( ! isset( $schedules[ $key ] ) ) {
                    $schedules[ $key ] = array(
                        'interval' => intval( $interval ) * MINUTE_IN_SECONDS,
                        'display'  => sprintf( __( 'Cada %d Minutos (Feed)', 'autonews-rss-rewriter' ), intval( $interval ) ),
                    );
                }
            }
        }
    }

    return $schedules;
}

/**
 * Activa el cron del plugin.
 * Programa la tarea cron en función del intervalo configurado si aún no está programada.
 */
function dsrw_activate_plugin() {
    if ( ! wp_next_scheduled( 'dsrw_cron_hook' ) ) {
        $cron_interval = get_option( 'dsrw_cron_interval', 'disabled' );
        if ( $cron_interval !== 'disabled' ) {
            wp_schedule_event( time(), 'dsrw_interval_' . $cron_interval, 'dsrw_cron_hook' );
            dsrw_write_log( "[AutoNews RSS Rewriter] " . __( 'Tarea cron programada al activar el plugin.', 'autonews-rss-rewriter' ) );
        }
    }
}

/**
 * Desactiva el cron del plugin.
 * Limpia cualquier tarea cron asociada al hook 'dsrw_cron_hook' y todos los crons individuales.
 */
function dsrw_deactivate_plugin() {
    wp_clear_scheduled_hook( 'dsrw_cron_hook' );
    dsrw_write_log( "[AutoNews RSS Rewriter] " . __( 'Tarea cron desprogramada al desactivar el plugin.', 'autonews-rss-rewriter' ) );

    // Limpiar también todos los crons individuales de feeds
    $rss_urls_raw = get_option( 'dsrw_rss_urls', '' );
    $rss_urls = array_filter( array_map( 'trim', explode( "\n", $rss_urls_raw ) ) );
    foreach ( $rss_urls as $index => $url ) {
        $hook_name = 'dsrw_feed_cron_hook_' . $index;
        wp_clear_scheduled_hook( $hook_name );
    }
    dsrw_write_log( "[AutoNews RSS Rewriter] " . __( 'Tareas cron individuales de feeds desprogramadas.', 'autonews-rss-rewriter' ) );
}

// Asegura que WordPress reconozca nuestros intervalos personalizados
add_filter( 'cron_schedules', 'dsrw_add_custom_cron_intervals' );

/**
 * Wrapper para ejecutar el procesamiento desde CRON con contexto completo.
 * Esta función se asegura de que todo esté cargado antes de procesar.
 */
function dsrw_cron_execute_wrapper() {
    // Log de inicio
    dsrw_write_log( '[AutoNews CRON] ========================================' );
    dsrw_write_log( '[AutoNews CRON] Iniciando ejecución automática por CRON' );
    dsrw_write_log( '[AutoNews CRON] Fecha: ' . current_time( 'mysql' ) );
    
    // Verificar que WordPress está completamente cargado
    if ( ! did_action( 'init' ) ) {
        dsrw_write_log( '[AutoNews CRON] ⚠️ WordPress no está completamente cargado. Esperando...' );
        // No ejecutar aún, esperar al siguiente ciclo
        return;
    }
    
    // Verificar que tenemos todas las funciones necesarias
    if ( ! function_exists( 'wp_insert_post' ) ) {
        dsrw_write_log( '[AutoNews CRON] ❌ ERROR: wp_insert_post no está disponible' );
        return;
    }
    
    if ( ! function_exists( 'update_post_meta' ) ) {
        dsrw_write_log( '[AutoNews CRON] ❌ ERROR: update_post_meta no está disponible' );
        return;
    }
    
    // Cargar dependencias de medios si no están cargadas
    if ( ! function_exists( 'media_handle_sideload' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        dsrw_write_log( '[AutoNews CRON] ✅ Funciones de medios cargadas manualmente' );
    }
    
    // Limpiar caché antes de empezar
    wp_cache_flush();
    dsrw_write_log( '[AutoNews CRON] ✅ Caché limpiada' );
    
    // Ejecutar el procesamiento (solo feeds que usan el cron global)
    dsrw_write_log( '[AutoNews CRON] 🚀 Iniciando procesamiento de feeds (cron global)...' );
    
    try {
        dsrw_process_all_feeds();
        dsrw_write_log( '[AutoNews CRON] ✅ Procesamiento completado correctamente' );
    } catch ( Exception $e ) {
        dsrw_write_log( '[AutoNews CRON] ❌ ERROR durante el procesamiento: ' . $e->getMessage() );
        dsrw_write_log( '[AutoNews CRON] Stack trace: ' . $e->getTraceAsString() );
    }
    
    // Limpiar caché al finalizar
    wp_cache_flush();
    
    dsrw_write_log( '[AutoNews CRON] Ejecución finalizada' );
    dsrw_write_log( '[AutoNews CRON] ========================================' );
}

// Único handler permitido para 'dsrw_cron_hook': centraliza el contexto y llama internamente a dsrw_process_all_feeds().
add_action( 'dsrw_cron_hook', 'dsrw_cron_execute_wrapper' );

/**
 * Wrapper para ejecutar un feed individual desde su propio CRON.
 *
 * @param int $feed_index Índice del feed en la lista de URLs RSS.
 */
function dsrw_cron_execute_single_feed( $feed_index ) {
    $feed_index = intval( $feed_index );
    
    dsrw_write_log( '[AutoNews CRON-FEED] ========================================' );
    dsrw_write_log( '[AutoNews CRON-FEED] Iniciando ejecución individual del feed #' . $feed_index );
    dsrw_write_log( '[AutoNews CRON-FEED] Fecha: ' . current_time( 'mysql' ) );
    
    // Verificar que WordPress está completamente cargado
    if ( ! did_action( 'init' ) ) {
        dsrw_write_log( '[AutoNews CRON-FEED] ⚠️ WordPress no está completamente cargado. Esperando...' );
        return;
    }
    
    if ( ! function_exists( 'wp_insert_post' ) ) {
        dsrw_write_log( '[AutoNews CRON-FEED] ❌ ERROR: wp_insert_post no está disponible' );
        return;
    }
    
    if ( ! function_exists( 'update_post_meta' ) ) {
        dsrw_write_log( '[AutoNews CRON-FEED] ❌ ERROR: update_post_meta no está disponible' );
        return;
    }
    
    // Cargar dependencias de medios si no están cargadas
    if ( ! function_exists( 'media_handle_sideload' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        dsrw_write_log( '[AutoNews CRON-FEED] ✅ Funciones de medios cargadas manualmente' );
    }
    
    wp_cache_flush();
    
    try {
        dsrw_process_feed_by_index( $feed_index );
        dsrw_write_log( '[AutoNews CRON-FEED] ✅ Feed #' . $feed_index . ' procesado correctamente' );
    } catch ( Exception $e ) {
        dsrw_write_log( '[AutoNews CRON-FEED] ❌ ERROR procesando feed #' . $feed_index . ': ' . $e->getMessage() );
    }
    
    wp_cache_flush();
    
    dsrw_write_log( '[AutoNews CRON-FEED] Ejecución finalizada para feed #' . $feed_index );
    dsrw_write_log( '[AutoNews CRON-FEED] ========================================' );
}

/**
 * Registrar dinámicamente los hooks para cada cron individual de feed.
 * Se ejecuta en 'init' para que estén disponibles cuando WordPress los necesite.
 */
function dsrw_register_feed_cron_hooks() {
    $rss_urls_raw = get_option( 'dsrw_rss_urls', '' );
    $rss_urls = array_filter( array_map( 'trim', explode( "\n", $rss_urls_raw ) ) );
    
    foreach ( $rss_urls as $index => $url ) {
        $hook_name = 'dsrw_feed_cron_hook_' . $index;
        // Usamos una closure para pasar el índice del feed al wrapper
        add_action( $hook_name, function() use ( $index ) {
            dsrw_cron_execute_single_feed( $index );
        });
    }
}
add_action( 'init', 'dsrw_register_feed_cron_hooks' );

/**
 * Programa o reprograma los crons individuales de cada feed.
 * Se llama desde admin-settings cuando se guardan los ajustes.
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
        $interval = isset( $feed_cron_intervals[ $index ] ) ? $feed_cron_intervals[ $index ] : 'global';
        
        // Siempre limpiar el cron individual primero
        wp_clear_scheduled_hook( $hook_name );
        
        // Solo programar si tiene un intervalo propio (no 'global' ni 'disabled')
        if ( $interval !== 'global' && $interval !== 'disabled' && ! empty( $interval ) ) {
            $schedule_key = 'dsrw_interval_' . $interval;
            $result = wp_schedule_event( time(), $schedule_key, $hook_name );
            if ( $result ) {
                dsrw_write_log( '[AutoNews] ✅ Cron individual programado para feed #' . $index . ' cada ' . $interval . ' minutos' );
            } else {
                dsrw_write_log( '[AutoNews] ❌ Error al programar cron individual para feed #' . $index );
            }
        }
    }
}