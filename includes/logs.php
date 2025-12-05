<?php
/**
 * Archivo: logs.php
 * Ubicación: includes/logs.php
 * Descripción: Funciones para el manejo de logs del plugin. Incluye:
 * - dsrw_write_log(): Escribe mensajes en un archivo de log personalizado con rotación automática.
 * - dsrw_logs_page(): Muestra el contenido del log en una página del área de administración.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evitar acceso directo.
}

/**
 * Escribe un mensaje en el archivo de log.
 * Implementa rotación de logs: si el archivo supera los 5MB, se archiva como _old.
 *
 * @param string $message Mensaje a registrar.
 */
function dsrw_write_log( $message ) {
    $upload_dir = wp_upload_dir();
    $base_dir   = trailingslashit( $upload_dir['basedir'] );
    $log_file   = $base_dir . 'dsrw_logs.txt';
    $old_log    = $base_dir . 'dsrw_logs_old.txt';
    
    // Límite de tamaño: 5 MB (en bytes)
    $max_size = 5 * 1024 * 1024;

    // Verificar si el archivo existe y supera el tamaño máximo
    if ( file_exists( $log_file ) && filesize( $log_file ) > $max_size ) {
        // Si existe un log antiguo, lo eliminamos primero (opcional, rename suele sobrescribir)
        if ( file_exists( $old_log ) ) {
            @unlink( $old_log );
        }
        // Renombrar el log actual a log antiguo
        @rename( $log_file, $old_log );
        
        // Escribir un aviso en el nuevo log indicando la rotación
        $rotation_msg = '[' . date( 'Y-m-d H:i:s' ) . '] --- LOG ROTADO (El anterior superó los 5MB) ---' . PHP_EOL;
        file_put_contents( $log_file, $rotation_msg, LOCK_EX );
    }

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
        
        <p class="description">
            <?php esc_html_e( 'Mostrando las últimas 100 líneas del log actual.', 'autonews-rss-rewriter' ); ?>
        </p>

        <pre class="dsrw-log-content" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; max-height: 500px; overflow: auto;">
<?php
    $upload_dir = wp_upload_dir();
    $log_file = trailingslashit( $upload_dir['basedir'] ) . 'dsrw_logs.txt';
    
    if ( file_exists( $log_file ) ) {
        // Leer el archivo en un array, saltando líneas vacías
        $lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        
        if ( $lines !== false && ! empty( $lines ) ) {
            // Mostrar las últimas 100 líneas del log
            $last_lines = array_slice( $lines, -100 );
            echo esc_html( implode( "\n", $last_lines ) );
        } else {
            esc_html_e( 'El archivo de logs está vacío.', 'autonews-rss-rewriter' );
        }
    } else {
        esc_html_e( 'No se encontró el archivo de logs activo.', 'autonews-rss-rewriter' );
    }
?>
        </pre>
        
        <?php 
        // Comprobar si existe un log antiguo y mostrar un aviso
        $old_log_file = trailingslashit( $upload_dir['basedir'] ) . 'dsrw_logs_old.txt';
        if ( file_exists( $old_log_file ) ) {
            echo '<p><em>' . esc_html__( 'Nota: Existe un archivo de log antiguo rotado (dsrw_logs_old.txt) en tu carpeta de subidas.', 'autonews-rss-rewriter' ) . '</em></p>';
        }
        ?>
    </div>
    <?php
}