<?php
/**
 * Archivo: image-handling.php
 * Ubicación: includes/image-handling.php
 * Descripción: Funciones para la gestión de imágenes, incluyendo validación, extracción y generación de miniaturas automáticas.
 *
 * Nota: Este archivo depende de otras funciones del plugin definidas en otros módulos, 
 *       como dsrw_write_log() (logs.php) o dsrw_send_error_email() (error-handling.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acceso directo.
}

/**
 * Sube la imagen destacada a WordPress.
 *
 * @param string $image_url URL de la imagen a subir.
 * @param int    $post_id ID del post al que se asignará la imagen.
 * @return mixed ID del attachment en caso de éxito, false en caso de error.
 */
function dsrw_upload_featured_image( $image_url, $post_id ) {
    if ( empty( $image_url ) ) {
        dsrw_write_log( "[AutoNews] " . __( 'ERROR: No se proporcionó una URL de imagen válida.', 'autonews-rss-rewriter' ) );
        return false;
    }
    // Validar si la URL corresponde a una imagen
    $headers = @get_headers( $image_url, 1 );
    if ( ! isset( $headers['Content-Type'] ) || strpos( $headers['Content-Type'], 'image/') === false ) {
        dsrw_write_log( "[AutoNews] " . __( 'ERROR: URL no válida como imagen: ', 'autonews-rss-rewriter' ) . $image_url );
        return false;
    }
    // Descargar la imagen temporalmente y verificar su tamaño real
    $tmp_file = download_url( $image_url );
    if ( is_wp_error( $tmp_file ) ) {
        dsrw_write_log('[AutoNews] Error al descargar imagen para comprobar dimensiones: ' . $tmp_file->get_error_message());
        return false;
    }
    $size = getimagesize( $tmp_file );
    @unlink( $tmp_file );
    if ( ! $size ) {
        dsrw_write_log('[AutoNews] No se pudieron obtener las dimensiones de la imagen: ' . $image_url );
        return false;
    }
    if ( $size[0] < 600 || $size[1] < 600 ) {
        dsrw_write_log('[AutoNews] Imagen ignorada por ser demasiado pequeña (' . $size[0] . 'x' . $size[1] . '): ' . $image_url );
        $image_url = ''; // Vacía la variable
        return false;
    }
    // Si pasa las validaciones, subir la imagen a WordPress
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    $attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );
    if ( is_wp_error( $attachment_id ) ) {
        dsrw_write_log( "[AutoNews] " . __( 'ERROR al subir imagen: ', 'autonews-rss-rewriter' ) . $attachment_id->get_error_message() );
        return false;
    }
    dsrw_write_log( "[AutoNews] " . __( 'Imagen destacada subida correctamente: ', 'autonews-rss-rewriter' ) . $image_url );
    return $attachment_id;
}

/**
 * Intenta obtener una versión más grande de la imagen eliminando sufijos de dimensión (por ejemplo, "-150x150").
 *
 * @param string $img_url URL original de la imagen.
 * @return string URL modificada.
 */
function dsrw_get_larger_image_url( $img_url ) {
    $new_url = preg_replace('/-\d+x\d+(?=\.(jpg|jpeg|png|gif))/i', '', $img_url);
    return $new_url;
}

/**
 * Extrae la imagen desde los metadatos Open Graph o Twitter del contenido.
 *
 * @param string $html Contenido HTML.
 * @return string URL de la imagen, o una cadena vacía si no se encuentra.
 */
function dsrw_get_featured_image_from_meta( $html ) {
    if ( empty( $html ) ) {
        return '';
    }
    if ( preg_match('/<meta property="og:image" content="([^"]+)"\s*\/?>/i', $html, $matches) ) {
        return esc_url_raw($matches[1]);
    }
    if ( preg_match('/<meta name="twitter:image" content="([^"]+)"\s*\/?>/i', $html, $matches) ) {
        return esc_url_raw($matches[1]);
    }
    return '';
}

/**
 * Extrae la imagen utilizando datos estructurados (Schema, JSON-LD) del contenido.
 *
 * @param string $html Contenido HTML.
 * @return string URL de la imagen, o vacío si no se encuentra.
 */
function dsrw_get_featured_image_from_schema( $html ) {
    if ( empty( $html ) ) {
        return '';
    }
    if ( preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/is', $html, $matches) ) {
        $json = json_decode($matches[1], true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json['image'])) {
            if (is_array($json['image'])) {
                return esc_url_raw($json['image'][0]);
            } else {
                return esc_url_raw($json['image']);
            }
        }
    }
    return '';
}

/**
 * Extrae la primera imagen encontrada en el contenido HTML.
 *
 * @param string $html Contenido HTML.
 * @return string URL de la imagen, o una cadena vacía si no se encuentra.
 */
function dsrw_extract_first_image( $html ) {
    if ( empty( $html ) ) {
        return '';
    }
    if ( preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches) ) {
        return esc_url_raw($matches[1]);
    }
    return '';
}

/**
 * Carga la imagen de fondo para la generación de miniaturas.
 *
 * @return mixed Recurso de imagen o false si ocurre un error.
 */
function dsrw_load_background_image() {
    $bg_path = plugin_dir_path(__FILE__) . '../assets/wpblur.webp';
    if ( ! file_exists($bg_path) ) {
        dsrw_write_log('[AutoNews] Imagen de fondo no encontrada: ' . $bg_path);
        return false;
    }
    $bg_image = imagecreatefromwebp($bg_path);
    if ( ! $bg_image ) {
        dsrw_write_log('[AutoNews] Error al cargar la imagen de fondo.');
        return false;
    }
    return $bg_image;
}

/**
 * Genera una miniatura personalizada con el título del artículo.
 *
 * @param string $title Título a mostrar en la miniatura.
 * @return string Ruta al archivo temporal de la miniatura generada o false en caso de error.
 */
function dsrw_generate_thumbnail_with_text( $title ) {
    $width = 1200;
    $height = 630;

    // Obtener opciones
    $bg_color_hex    = get_option( 'dsrw_thumbnail_bg_color', '#0073aa' );
    $text_color_hex  = get_option( 'dsrw_thumbnail_text_color', '#ffffff' );
    $font_size       = intval( get_option( 'dsrw_thumbnail_font_size', 48 ) );
    // Limitar tamaño de fuente entre 10 y 60 px
    $font_size = max( 10, min( $font_size, 60 ) );

    // Convertir colores HEX a RGB
    list($r1, $g1, $b1) = sscanf($bg_color_hex, "#%02x%02x%02x");
    list($r2, $g2, $b2) = sscanf($text_color_hex, "#%02x%02x%02x");

    // Cargar imagen de fondo
    $bg_image = dsrw_load_background_image();
    if ( ! $bg_image ) {
        return false;
    }

    // Crear overlay translúcido sobre el fondo
    $overlay = imagecreatetruecolor($width, $height);
    imagesavealpha($overlay, true);
    $overlay_color = imagecolorallocatealpha($overlay, $r1, $g1, $b1, 40);
    imagefill($overlay, 0, 0, $overlay_color);
    imagecopy($bg_image, $overlay, 0, 0, 0, 0, $width, $height);
    imagedestroy($overlay);

    // Fuente
    $font_path = plugin_dir_path(__FILE__) . '../assets/OpenSans-Bold.ttf';
    if ( ! file_exists( $font_path ) ) {
        dsrw_write_log('[AutoNews] Fuente no encontrada para miniatura: ' . $font_path);
        return false;
    }

    // Dividir el título en líneas para ajustarlo al ancho máximo
    $max_text_width = $width - 100;
    $words = explode(' ', $title);
    $lines = array();
    $line = '';

    foreach ( $words as $word ) {
        $test_line = $line === '' ? $word : $line . ' ' . $word;
        $box = imagettfbbox($font_size, 0, $font_path, $test_line);
        $text_width = $box[2] - $box[0];
        if ( $text_width > $max_text_width ) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $test_line;
        }
    }
    $lines[] = $line;

    // Escribir texto centrado en la imagen
    $text_color = imagecolorallocate($bg_image, $r2, $g2, $b2);
    $line_height = $font_size + 10;
    $total_height = count($lines) * $line_height;
    $y = ($height - $total_height) / 2 + $font_size;

    foreach ( $lines as $line ) {
        $box = imagettfbbox($font_size, 0, $font_path, $line);
        $text_width = $box[2] - $box[0];
        $x = ($width - $text_width) / 2;
        imagettftext($bg_image, $font_size, 0, $x, $y, $text_color, $font_path, $line);
        $y += $line_height;
    }

    // Guardar la miniatura en un archivo temporal
    $tmp_file = tempnam(sys_get_temp_dir(), 'dsrw_thumb') . '.jpg';
    imagejpeg($bg_image, $tmp_file, 90);
    imagedestroy($bg_image);

    return $tmp_file;
}