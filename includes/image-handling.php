<?php
/**
 * Archivo: image-handling.php
 * Ubicación: includes/image-handling.php
 * Descripción: Funciones para la gestión de imágenes, incluyendo validación, extracción,
 * generación de miniaturas con efecto BLUR y texto superpuesto.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acceso directo.
}

/**
 * Sube la imagen destacada a WordPress.
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

    // Descargar temporalmente
    $tmp_file = download_url( $image_url );
    if ( is_wp_error( $tmp_file ) ) {
        dsrw_write_log('[AutoNews] Error al descargar imagen: ' . $tmp_file->get_error_message());
        return false;
    }

    $size = @getimagesize( $tmp_file );
    @unlink( $tmp_file ); // Borrar temporal

    if ( ! $size ) {
        dsrw_write_log('[AutoNews] No se pudieron obtener las dimensiones: ' . $image_url );
        return false;
    }

    // Filtro de tamaño mínimo (600x600)
    if ( $size[0] < 600 || $size[1] < 600 ) {
        dsrw_write_log('[AutoNews] Imagen ignorada por pequeña (' . $size[0] . 'x' . $size[1] . '): ' . $image_url );
        return false;
    }

    // Subir a la biblioteca de medios
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );

    if ( is_wp_error( $attachment_id ) ) {
        dsrw_write_log( "[AutoNews] " . __( 'ERROR al subir imagen: ', 'autonews-rss-rewriter' ) . $attachment_id->get_error_message() );
        return false;
    }

    dsrw_write_log( "[AutoNews] Imagen subida correctamente: " . $image_url );
    return $attachment_id;
}

/**
 * Utilidades de limpieza de URL y extracción (Metadatos, Schema, HTML)
 */
function dsrw_get_larger_image_url( $img_url ) {
    return preg_replace('/-\d+x\d+(?=\.(jpg|jpeg|png|gif))/i', '', $img_url);
}

function dsrw_get_featured_image_from_meta( $html ) {
    if ( empty( $html ) ) return '';
    if ( preg_match('/<meta property="og:image" content="([^"]+)"\s*\/?>/i', $html, $matches) ) return esc_url_raw($matches[1]);
    if ( preg_match('/<meta name="twitter:image" content="([^"]+)"\s*\/?>/i', $html, $matches) ) return esc_url_raw($matches[1]);
    return '';
}

function dsrw_get_featured_image_from_schema( $html ) {
    if ( empty( $html ) ) return '';
    if ( preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/is', $html, $matches) ) {
        $json = json_decode($matches[1], true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json['image'])) {
            return is_array($json['image']) ? esc_url_raw($json['image'][0]) : esc_url_raw($json['image']);
        }
    }
    return '';
}

function dsrw_extract_first_image( $html ) {
    if ( empty( $html ) ) return '';
    if ( preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches) ) return esc_url_raw($matches[1]);
    return '';
}

/**
 * Carga la imagen de fondo (Personalizada o por Defecto)
 * La recorta y centra a 1200x630.
 */
function dsrw_load_background_image() {
    $target_width = 1200;
    $target_height = 630;
    $final_canvas = imagecreatetruecolor($target_width, $target_height);
    
    $custom_bg_id = get_option('dsrw_thumbnail_custom_bg_id');
    $source_image = false;
    
    // 1. Intentar cargar imagen personalizada
    if ( $custom_bg_id > 0 ) {
        $file_path = get_attached_file( $custom_bg_id );
        if ( $file_path && file_exists( $file_path ) ) {
            $file_type = wp_check_filetype( $file_path );
            switch ( $file_type['ext'] ) {
                case 'jpeg': case 'jpg': $source_image = @imagecreatefromjpeg( $file_path ); break;
                case 'png': $source_image = @imagecreatefrompng( $file_path ); break;
                case 'gif': $source_image = @imagecreatefromgif( $file_path ); break;
                case 'webp': $source_image = @imagecreatefromwebp( $file_path ); break;
            }
        }
    }

    // 2. Fallback: Cargar imagen por defecto (wpblur.webp o jpg)
    if ( ! $source_image ) {
        $bg_path = plugin_dir_path(__FILE__) . '../assets/wpblur.webp';
        
        // Si no existe webp, buscamos jpg por si acaso
        if ( ! file_exists($bg_path) ) {
             $bg_path = plugin_dir_path(__FILE__) . '../assets/wpblur.jpg';
        }

        if ( file_exists($bg_path) ) {
            // Detectar tipo por extensión para cargar correctamente
            $ext = pathinfo($bg_path, PATHINFO_EXTENSION);
            if($ext == 'webp') {
                $source_image = @imagecreatefromwebp($bg_path);
            } elseif($ext == 'jpg' || $ext == 'jpeg') {
                $source_image = @imagecreatefromjpeg($bg_path);
            }
        }
        
        if ( ! $source_image ) {
            dsrw_write_log('[AutoNews] CRÍTICO: No se encontró imagen de fondo en assets/wpblur.webp');
            imagedestroy($final_canvas);
            return false;
        }
    }
    
    // 3. Recorte "Cover" (Centrado)
    $src_width = imagesx($source_image);
    $src_height = imagesy($source_image);
    
    $src_ratio = $src_width / $src_height;
    $target_ratio = $target_width / $target_height;

    $src_x = 0; $src_y = 0;
    $src_w = $src_width; $src_h = $src_height;

    if ($src_ratio > $target_ratio) {
        $src_w = (int)($src_height * $target_ratio);
        $src_x = (int) (($src_width - $src_w) / 2);
    } else {
        $src_h = (int)($src_width / $target_ratio);
        $src_y = (int) (($src_height - $src_h) / 2);
    }

    imagecopyresampled(
        $final_canvas, $source_image,
        0, 0, $src_x, $src_y,
        $target_width, $target_height, $src_w, $src_h
    );
    
    imagedestroy($source_image); 
    return $final_canvas;
}

/**
 * Genera la miniatura final: 
 * IMAGEN FONDO + BLUR + CAPA COLOR + TEXTO CENTRADO
 */
function dsrw_generate_thumbnail_with_text( $title ) {
    if ( ! extension_loaded('gd') ) {
        dsrw_write_log('[AutoNews] Error: La librería GD no está activa en el servidor.');
        return false;
    }

    $width = 1200;
    $height = 630;

    // --- 1. Obtener Configuración ---
    $bg_color_hex    = get_option( 'dsrw_thumbnail_bg_color', '#000000' ); // Por defecto negro para contraste
    $text_color_hex  = get_option( 'dsrw_thumbnail_text_color', '#ffffff' );
    $font_size       = intval( get_option( 'dsrw_thumbnail_font_size', 50 ) );
    $font_size       = max( 20, min( $font_size, 80 ) ); // Rango seguro

    // Hex a RGB
    list($r1, $g1, $b1) = sscanf($bg_color_hex, "#%02x%02x%02x");
    list($r2, $g2, $b2) = sscanf($text_color_hex, "#%02x%02x%02x");

    // --- 2. Cargar Fondo ---
    $bg_image = dsrw_load_background_image();
    if ( ! $bg_image ) return false;

    // --- 3. APLICAR EFECTO BLUR (NUEVO) ---
    // Aplicamos el filtro varias veces para intensificar el desenfoque
    for ($i = 0; $i < 5; $i++) {
        imagefilter($bg_image, IMG_FILTER_GAUSSIAN_BLUR);
    }

    // --- 4. Aplicar Capa de Color (Overlay) ---
    $overlay = imagecreatetruecolor($width, $height);
    
    // GD Alpha: 0 (Opaco) a 127 (Transparente).
    // Usamos 50 para que el color sea bastante sólido pero deje ver el fondo borroso.
    $alpha_val = 50; 
    
    $overlay_color = imagecolorallocatealpha($overlay, $r1, $g1, $b1, $alpha_val); 
    imagefill($overlay, 0, 0, $overlay_color);
    imagecopy($bg_image, $overlay, 0, 0, 0, 0, $width, $height);
    imagedestroy($overlay);

    // --- 5. Escribir el Texto ---
    $font_path = plugin_dir_path(__FILE__) . '../assets/OpenSans-Bold.ttf';
    
    // Fallback de fuente si no existe la OpenSans
    if ( ! file_exists( $font_path ) ) {
        // Intenta usar una fuente del sistema si la personalizada falla, o retorna error
        dsrw_write_log('[AutoNews] Fuente no encontrada: ' . $font_path);
        // Opcional: usar fuente GD básica (fea pero funcional) si falla la TTF
        // return false; 
    }

    if ( file_exists( $font_path ) ) {
        // Configuración de texto
        $max_text_width = $width - 200; // Margen de 100px a cada lado
        $words = explode(' ', $title);
        $lines = array();
        $line = '';

        // Algoritmo de ajuste de línea
        foreach ( $words as $word ) {
            $test_line = $line === '' ? $word : $line . ' ' . $word;
            $box = imagettfbbox($font_size, 0, $font_path, $test_line);
            if( !$box ) continue; // Evitar error si falla cálculo
            
            $text_width = abs($box[4] - $box[0]);
            
            if ( $text_width > $max_text_width ) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $test_line;
            }
        }
        $lines[] = $line;

        // Renderizado centrado
        $text_color = imagecolorallocate($bg_image, $r2, $g2, $b2);
        $line_height = $font_size * 1.4;
        $total_height = count($lines) * $line_height;
        
        // Calcular Y inicial para centrar verticalmente el bloque de texto
        $y_start = ($height - $total_height) / 2 + ($font_size / 2); // Ajuste visual

        $current_y = $y_start;

        foreach ( $lines as $line_text ) {
            $box = imagettfbbox($font_size, 0, $font_path, $line_text);
            $text_w = abs($box[4] - $box[0]);
            $x = ($width - $text_w) / 2;
            
            // Sombra del texto (opcional, mejora legibilidad)
            $shadow_color = imagecolorallocate($bg_image, 0, 0, 0);
            imagettftext($bg_image, $font_size, 0, $x + 3, $current_y + 3, $shadow_color, $font_path, $line_text);
            
            // Texto principal
            imagettftext($bg_image, $font_size, 0, $x, $current_y, $text_color, $font_path, $line_text);
            
            $current_y += $line_height;
        }
    }

    // --- 6. Guardar y Limpiar ---
    $tmp_file = tempnam(sys_get_temp_dir(), 'dsrw_thumb') . '.jpg';
    imagejpeg($bg_image, $tmp_file, 90); // Calidad 90
    imagedestroy($bg_image);

    return $tmp_file;
}