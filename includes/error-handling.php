<?php
/**
 * Archivo: error-handling.php
 * Ubicación: includes/error-handling.php
 * Descripción: Funciones para el manejo de errores y notificaciones. En este módulo se define
 *              la función para enviar correos de error al administrador.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita el acceso directo.
}

/**
 * Envía un correo electrónico de notificación de error al administrador.
 *
 * @param string $subject Asunto del correo.
 * @param string $message Cuerpo del correo.
 */
function dsrw_send_error_email( $subject, $message ) {
    $admin_email = get_option( 'admin_email' );
    wp_mail( $admin_email, $subject, $message );
}
