<?php
/**
 * Archivo: logs.php
 * Ubicación: includes/logs.php
 * Descripción: Funciones para el manejo de logs del plugin. Incluye:
 *    - dsrw_write_log(): Escribe mensajes en un archivo de log personalizado.
 *    - dsrw_logs_page(): Muestra el contenido del log en una página del área de administración.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evitar acceso directo.
}

/**
 * Escribe un mensaje en el archivo de log.
 *
 * @param string $message Mensaje a registrar.
 */
function dsrw_write_log( $message ) {
    $upload_dir = wp_upload_dir();
    $log_file = trailingslashit( $upload_dir['basedir'] ) . 'dsrw_logs.txt';
    $formatted_message = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message . PHP_EOL;
    
    if ( ! file_exists( $log_file ) ) {
        file_put_contents( $log_file, $formatted_message, LOCK_EX );
    } else {
        file_put_contents( $log_file, $formatted_message, FILE_APPEND | LOCK_EX );
    }
}

/**
 * Muestra el registro de actividad en el área de administración.
 *
 * Esta función se usa en la página de "Registro de Actividad" del plugin.
 */
function dsrw_logs_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap dsrw-logs-page">
        <h1><?php esc_html_e( 'AutoNews RSS Rewriter - Registro de Actividad', 'autonews-rss-rewriter' ); ?></h1>
        <pre class="dsrw-log-content" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; max-height: 500px; overflow: auto;">
<?php
    $upload_dir = wp_upload_dir();
    $log_file = trailingslashit( $upload_dir['basedir'] ) . 'dsrw_logs.txt';
    if ( file_exists( $log_file ) ) {
        $lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        // Mostrar las últimas 100 líneas del log
        $last_lines = array_slice( $lines, -100 );
        echo esc_html( implode( "\n", $last_lines ) );
    } else {
        esc_html_e( 'No se encontró el archivo de logs.', 'autonews-rss-rewriter' );
    }
?>
        </pre>
    </div>
    <?php
}
