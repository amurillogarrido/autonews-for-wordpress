<?php
/**
 * Archivo: feeds.php
 * Ubicación: includes/feeds.php
 * Descripción: Funciones para procesar los feeds RSS, validar y publicar artículos,
 * obtener el contenido completo, limpiar el HTML y gestionar duplicados.
 *
 * Nota: Este archivo depende de que existan funciones en otros módulos, por ejemplo:
 * - dsrw_write_log() en logs.php
 * - dsrw_send_error_email() en error-handling.php
 * - dsrw_get_prompt_template() en prompts.php
 * Además, se asume que el autoloader de Composer y la carga de traducciones ya se han realizado.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acceso directo.
}

/**
 * Procesa todos los feeds configurados.
 */
function dsrw_process_all_feeds(&$logs = null) {
    $rss_urls_raw    = get_option( 'dsrw_rss_urls', '' );
    $openai_api_key  = get_option( 'dsrw_openai_api_key' );
    $openai_api_base = get_option( 'dsrw_openai_api_base', 'https://api.openai.com' );
    $num_articulos   = (int) get_option( 'dsrw_num_articulos', 5 );

    if ( empty( $rss_urls_raw ) || empty( $openai_api_key ) ) {
        dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'Faltan datos de configuración (RSS URLs o API Key).', 'autonews-rss-rewriter' ) );
        dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Configuración Incompleta', 'autonews-rss-rewriter' ), __( 'Faltan datos de configuración (RSS URLs o API Key).', 'autonews-rss-rewriter' ) );
        return;
    }

    $rss_urls = array_filter( array_map( 'trim', explode( "\n", $rss_urls_raw ) ) );
    if (is_array($logs)) $logs[] = "🔗 Procesando " . count($rss_urls) . " feeds RSS...";
    $feed_categories = get_option( 'dsrw_feed_categories', array() );
    $default_author_option = get_option( 'dsrw_default_author', '1' );
    $available_authors = get_users( array(
        'who'     => 'authors',
        'orderby' => 'display_name',
        'order'   => 'ASC',
    ) );
    $base_publish_time = current_time( 'timestamp' );
    $publish_delay_minutes = (int) get_option( 'dsrw_publish_delay', 0 );

    foreach ( $rss_urls as $index => $url ) {
        // Se toma la configuración del feed sin modificarla para cada artículo del feed
        $feed_category_setting = isset( $feed_categories[ $index ] ) ? $feed_categories[ $index ] : '';
        dsrw_process_single_feed( $url, $openai_api_key, $openai_api_base, $num_articulos, $feed_category_setting, $base_publish_time, $publish_delay_minutes, $default_author_option, $available_authors, $logs );
    }
}

/**
 * Procesa un solo feed RSS.
 *
 * @param string $feed_url URL del feed.
 * @param string $api_key Clave de API para OpenAI.
 * @param string $api_base Base URL de la API.
 * @param int    $num_items Número de artículos deseados.
 * @param mixed  $feed_category_setting Configuración de categoría para el feed.
 * @param int    &$base_publish_time Tiempo base para calcular publicaciones.
 * @param int    $publish_delay_minutes Desfase en minutos entre publicaciones.
 * @param mixed  $default_author_option Opción de autor predeterminado.
 * @param array  $available_authors Lista de usuarios autores.
 * @param array  &$logs (Opcional) Array para registrar logs para AJAX.
 */

 function dsrw_ajax_run_feeds() {

    // Verifica capacidades del usuario (seguridad)
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permisos insuficientes' ), 403 );
    }

    // Llama a la función que procesa todos los feeds
    dsrw_process_all_feeds();

    // Envía una respuesta de éxito en JSON
    wp_send_json_success( array( 'message' => 'Procesamiento completado.' ) );
}
add_action( 'wp_ajax_dsrw_run_feeds', 'dsrw_ajax_run_feeds' );

function dsrw_process_single_feed( $feed_url, $api_key, $api_base, $num_items, $feed_category_setting, &$base_publish_time, $publish_delay_minutes, $default_author_option, $available_authors, &$logs = null ) {
    if ( empty( $feed_url ) ) {
        return;
    }
    include_once ABSPATH . WPINC . '/feed.php';
    $rss = fetch_feed( $feed_url );
    if ( is_wp_error( $rss ) ) {
        dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'Error al leer el RSS: ', 'autonews-rss-rewriter' ) . $rss->get_error_message() );
        dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Error al Leer RSS', 'autonews-rss-rewriter' ), __( 'Error al leer el RSS: ', 'autonews-rss-rewriter' ) . $rss->get_error_message() );
        return;
    }
    
    // ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓ MODIFICACIÓN AQUI (OBTENER TODOS LOS ÍTEMS) ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
    $maxitems  = $rss->get_item_quantity(); 
    // ↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
    $rss_items = $rss->get_items( 0, $maxitems );
    // ↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑

    if ( ! $rss_items ) {
        dsrw_write_log( "[AutoNews RSS Rewriter] " . __( 'No hay entradas en el feed: ', 'autonews-rss-rewriter' ) . $feed_url );
        dsrw_send_error_email( __( 'AutoNews RSS Rewriter - No hay Entradas', 'autonews-rss-rewriter' ), __( 'No hay entradas en el feed: ', 'autonews-rss-rewriter' ) . $feed_url );
        return;
    }

    $published_count = 0;

    // --- ¡NUEVA MEJORA! OBTENER LISTA DE CATEGORÍAS ---
    // Obtenemos todos los nombres de las categorías de WP una sola vez
    $all_categories = get_categories( array( 'hide_empty' => false, 'fields' => 'names' ) );
    $category_list_string = implode(', ', $all_categories); // Ej: "Casa Real, Corazón, Política"
    // --- FIN MEJORA ---

    foreach ( $rss_items as $item ) {
        if ( $published_count >= $num_items ) {
            break;
        }
    
        $titulo_original = $item->get_title();
        $enlace = $item->get_link();
        $enlace_normalizado = preg_replace('/(\?.*)|(#.*)/', '', strtolower(trim($enlace)));
        $hash = md5( $enlace_normalizado );        

        if ( is_array($logs) ) {
            $logs[] = "📝 Reescribiendo artículo " . ($published_count + 1) . ": \"$titulo_original\"";
        }
    
        if ( dsrw_is_duplicate( $hash ) ) {
            dsrw_write_log( "[AutoNews RSS Rewriter] " . __( 'Artículo duplicado detectado: ', 'autonews-rss-rewriter' ) . $enlace );
            if ( is_array($logs) ) {
                $logs[] = "🔁 Ignorado (duplicado): \"$titulo_original\"";
            }
            continue;
        }
        

        // Obtener contenido
        $contenido = dsrw_get_full_content( $enlace );

        // --- ¡NUEVA MEJORA! (Filtro Anti-Galerías) ---
        // Contamos las imágenes ANTES de limpiarlas.
        // Usamos un umbral de 4 imágenes para considerarlo galería.
        $image_count = 0;
        if ( !empty($contenido) ) {
            $image_count = substr_count( strtolower($contenido), '<img' );
        }

        if ( $image_count > 4 ) {
            dsrw_write_log( "[AutoNews] " . __( 'ARTÍCULO DESCARTADO: Detectado como galería (', 'autonews-rss-rewriter' ) . $image_count . __( ' imágenes) - ', 'autonews-rss-rewriter' ) . $enlace );
            if ( is_array($logs) ) {
                $logs[] = "🎨 Ignorado (Galería, " . $image_count . " imágenes): \"$titulo_original\"";
            }
            continue; // Saltar este artículo
        }
        // --- FIN MEJORA ---


        // Limpiar el contenido (ahora sí, después de contar)
        $contenido = dsrw_clean_article_content( $contenido ); // <-- ¡AQUÍ SE LIMPIA!
        if ( empty( $contenido ) ) {
            $contenido = wp_strip_all_tags( $item->get_description() );
        }
        // Si el contenido (sin etiquetas) tiene menos de 150 caracteres, saltar este artículo.
        if ( strlen( strip_tags( $contenido ) ) < 150 ) {
            dsrw_write_log( "[AutoNews] " . __( 'ARTÍCULO DESCARTADO: Contenido demasiado corto (<150 caracteres) - ', 'autonews-rss-rewriter' ) . $enlace );
            continue;
        }
        // Si tiene menos de 180 palabras, también se descarta.
        if ( str_word_count( strip_tags( $contenido ) ) < 180 ) {
            dsrw_write_log( "[AutoNews] " . __( 'ARTÍCULO DESCARTADO: Contenido insuficiente (<180 palabras) - ', 'autonews-rss-rewriter' ) . $enlace );
            continue;
        }
        
        // --- ¡NUEVA MEJORA! (Pasar la lista de categorías) ---
        $reescrito = dsrw_rewrite_article( $titulo_original, $contenido, $api_key, $api_base, $category_list_string );
        // --- FIN MEJORA ---

        if ( ! $reescrito ) {
            continue;
        }
        
        // --- MODIFICACIÓN DE CLAVES JSON ---
        $nuevo_titulo = isset( $reescrito['title'] ) ? $reescrito['title'] : '';
        
        // --- ¡NUEVA CORRECCIÓN! ---
        // Forzamos la primera letra a mayúscula, sin importar lo que diga la IA.
        $nuevo_titulo = ucfirst( $nuevo_titulo );
        // --- FIN CORRECCIÓN ---

        $nuevo_contenido = isset( $reescrito['content'] ) ? $reescrito['content'] : '';
        $nuevo_slug = isset( $reescrito['slug'] ) ? sanitize_title( $reescrito['slug'] ) : '';
        $categoria_nombre = isset( $reescrito['category'] ) ? sanitize_text_field( $reescrito['category'] ) : '';
        $excerpt = isset( $reescrito['excerpt'] ) ? sanitize_text_field( $reescrito['excerpt'] ) : '';
        
        // --- ¡NUEVA MEJORA 3! (Lectura de Tags) ---
        $nuevas_etiquetas = isset( $reescrito['tags'] ) && is_array( $reescrito['tags'] ) ? $reescrito['tags'] : array();
        // --- FIN MEJORA 3 ---


        // --- MODIFICACIÓN DE CATEGORÍAS ---
if ( $feed_category_setting === 'none' ) {
    $default_category = get_option( 'default_category' );
    $categoria_final = $default_category ? (int) $default_category : 1;

} elseif ( $feed_category_setting === '' ) {
    if ( ! empty( $categoria_nombre ) ) {
        // --- ¡LÓGICA MEJORADA! ---
        // Ahora que la IA nos da un nombre exacto de la lista, la coincidencia debería ser 100%
        // Usamos 'get_term_by' para una comprobación exacta en lugar de 'similar_text'
        $term = get_term_by('name', $categoria_nombre, 'category');
        
        if ( $term ) {
            // ¡Éxito! La IA nos dio un nombre que existe.
            $categoria_final = $term->term_id;
        } else {
            // La IA falló o sugirió una categoría que no estaba en la lista (pese a la instrucción)
            // Volvemos a la lógica de "buscar mejor coincidencia" como plan B.
            $matched_id = dsrw_find_best_category_match( $categoria_nombre );
            if ( $matched_id ) {
                $categoria_final = $matched_id;
            } else {
                // Si sigue sin coincidir, comprobamos si podemos crearla
                $allow_category_creation = get_option('dsrw_allow_category_creation', 0);
                if ( $allow_category_creation ) {
                    $term_id = wp_create_category( $categoria_nombre );
                    if ( ! is_wp_error( $term_id ) ) {
                        $categoria_final = $term_id;
                        dsrw_write_log('[AutoNews RSS Rewriter] Categoría creada automáticamente: ' . $categoria_nombre);
                    } else {
                        $default_category = get_option( 'default_category' );
                        $categoria_final = $default_category ? (int) $default_category : 1;
                        dsrw_write_log('[AutoNews RSS Rewriter] Error al crear categoría: ' . $categoria_nombre . ' - ' . $term_id->get_error_message());
                    }
                } else {
                    $default_category = get_option( 'default_category' );
                    $categoria_final = $default_category ? (int) $default_category : 1;
                    dsrw_write_log('[AutoNews RSS Rewriter] No se encontró categoría (' . $categoria_nombre . ') y no está permitido crearla. Usando la por defecto.');
                }
            }
        }
    } else {
        // Si la IA no devolvió ninguna categoría, usar la por defecto
        $default_category = get_option( 'default_category' );
        $categoria_final = $default_category ? (int) $default_category : 1;
    }
} else {
    // Usa la categoría especificada manually en dsrw_feed_categories
    $categoria_final = (int) $feed_category_setting;
}
// --- FIN MODIFICACIÓN DE CATEGORÍAS ---


        // Asignar el autor
        if ( $default_author_option === 'random' ) {
            if ( ! empty( $available_authors ) ) {
                $random_author = $available_authors[ array_rand( $available_authors ) ];
                $author_id = $random_author->ID;
            } else {
                $author_id = 1;
                dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'No hay autores disponibles. Asignando al usuario con ID 1.', 'autonews-rss-rewriter' ) );
            }
        } else {
            $author_id = intval( $default_author_option );
            if ( ! get_userdata( $author_id ) ) {
                $author_id = 1;
                dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'El autor seleccionado no existe. Asignando al usuario con ID 1.', 'autonews-rss-rewriter' ) );
            }
        }
        // Calcular fecha de publicación
        if ( $publish_delay_minutes > 0 ) {
            $publish_time = $base_publish_time + ( $publish_delay_minutes * MINUTE_IN_SECONDS );
            $publish_date = date( 'Y-m-d H:i:s', $publish_time );
            $post_status = 'future';
            $base_publish_time = $publish_time;
        } else {
            $publish_date = current_time( 'mysql' );
            $post_status = 'publish';
        }
        
        // --- LIMPIEZA POST-IA MEJORADA ---
        $nuevo_contenido = dsrw_cleanup_headings( $nuevo_contenido );
        $nuevo_contenido = dsrw_cleanup_bold( $nuevo_contenido ); // <-- ¡ESTA FUNCIÓN AHORA ES MÁS POTENTE!
        $nuevo_contenido = dsrw_remove_placeholder_images( $nuevo_contenido );  
        $nuevo_contenido = preg_replace('/<figcaption>.*?<\/figcaption>/is', '', $nuevo_contenido);
        $nuevo_contenido = preg_replace('/\s*(Pie de foto:|Leyenda:).*$/mi', '', $nuevo_contenido);
        // --- FIN LIMPIEZA POST-IA ---

        $post_data = array(
            'post_title'    => wp_strip_all_tags( $nuevo_titulo ),
            'post_content'  => wp_kses_post( $nuevo_contenido ), // wp_kses_post también limpia HTML malformado
            'post_status'   => $post_status,
            'post_date'     => $publish_date,
            'post_name'     => $nuevo_slug,
            'post_excerpt'  => $excerpt,
            'post_type'     => 'post',
            'post_author'   => $author_id,
            'post_category' => ( $categoria_final > 0 && get_term( $categoria_final, 'category' ) ) ? array( $categoria_final ) : array(),
        );

        // --- ¡NUEVA MEJORA 3! (Asignación de Tags) ---
        $enable_tags = get_option('dsrw_enable_tags', 0);
        if ( $enable_tags && ! empty($nuevas_etiquetas) ) {
            // Sanitizar las etiquetas por si acaso
            $tags_limpias = array_map( 'sanitize_text_field', $nuevas_etiquetas );
            $post_data['tags_input'] = $tags_limpias;
            dsrw_write_log('[AutoNews] Asignando ' . count($tags_limpias) . ' etiquetas al post.');
        }
        // --- FIN MEJORA 3 ---

        $post_id = wp_insert_post( $post_data );

if ( is_wp_error( $post_id ) ) {
    dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'Error al insertar post: ', 'autonews-rss-rewriter' ) . $post_id->get_error_message() );
    dsrw_send_error_email(
        __( 'AutoNews RSS Rewriter - Error al Insertar Post', 'autonews-rss-rewriter' ),
        __( 'Error al insertar post: ', 'autonews-rss-rewriter' ) . $post_id->get_error_message()
    );
    continue;
}

// Si llegamos aquí, $post_id es un entero válido
update_post_meta( $post_id, '_dsrw_original_hash', $hash );
clean_post_cache( $post_id ); // vacía caché para que get_posts() lo vea
dsrw_write_log( "[AutoNews] Hash guardado para post #{$post_id}: " . get_post_meta( $post_id, '_dsrw_original_hash', true ) );


        // ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓ MODIFICACIÓN AQUI (CONTAR PUBLICADOS TRAS INSERTAR) ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
        $published_count++;
        // ↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑

        if ( is_array($logs) ) {
            $logs[] = "✅ Artículo publicado correctamente: \"$titulo_original\"";
        }        

        // --- SECCIÓN DE SELECCIÓN DE IMAGEN CON VALIDACIÓN DE RESOLUCIÓN ---
        $img_url = '';
        $enclosures = $item->get_enclosures();
        if ( ! empty( $enclosures ) ) {
            foreach ( $enclosures as $enclosure ) {
                // Si se especifica el atributo "medium" y es "image"
                if ( isset( $enclosure->attributes['medium'] ) && $enclosure->attributes['medium'] === 'image' ) {
                    if ( isset( $enclosure->attributes['width'] ) && isset( $enclosure->attributes['height'] ) ) {
                        $width = (int) $enclosure->attributes['width'];
                        $height = (int) $enclosure->attributes['height'];
                        if ( $width < 300 || $height < 200 ) {
                            continue; // Imagen demasiado pequeña, saltar
                        }
                    }
                    $img_url = esc_url_raw( $enclosure->get_link() );
                    dsrw_write_log( "[AutoNews] Imagen seleccionada desde media:content: " . $img_url );
                    break;
                }
                // Si no hay atributo "medium" y el URL parece una imagen
                elseif ( empty( $enclosure->attributes['medium'] ) && preg_match( '/\.(jpg|jpeg|png|gif)$/i', $enclosure->get_link() ) ) {
                    $img_url = esc_url_raw( $enclosure->get_link() );
                    dsrw_write_log( "[AutoNews] Imagen seleccionada (sin medium) desde enclosure: " . $img_url );
                    break;
                }
            }
        }
        // Si no se encontró imagen en enclosures, se revisa media:thumbnail
        if ( empty( $img_url ) ) {
            $thumbnails = $item->get_item_tags( SIMPLEPIE_NAMESPACE_MEDIARSS, 'thumbnail' );
            if ( ! empty( $thumbnails ) && isset( $thumbnails[0]['attribs']['']['url'] ) ) {
                if ( isset( $thumbnails[0]['attribs']['']['width'] ) && isset( $thumbnails[0]['attribs']['']['height'] ) ) {
                    $width = (int) $thumbnails[0]['attribs']['']['width'];
                    $height = (int) $thumbnails[0]['attribs']['']['height'];
                    if ( $width >= 600 && $height >= 600 ) {
                        $img_url = esc_url_raw( $thumbnails[0]['attribs']['']['url'] );
                        dsrw_write_log( "[AutoNews] Usando imagen de media:thumbnail: " . $img_url );
                    }
                } else {
                    $img_url = esc_url_raw( $thumbnails[0]['attribs']['']['url'] );
                    dsrw_write_log( "[AutoNews] Usando imagen de media:thumbnail sin datos de resolución: " . $img_url );
                }
            }
        }
        // Extracción de imágenes (si está habilitado)
        if ( get_option('dsrw_enable_image_extraction') === '1' ) {
            if ( empty( $img_url ) ) {
                $img_url = dsrw_get_larger_image_url( $img_url );
            }
            if ( empty( $img_url ) ) {
                $img_url = dsrw_get_featured_image_from_meta( $contenido );
            }
            if ( empty( $img_url ) ) {
                $img_url = dsrw_get_featured_image_from_schema( $contenido );
            }
            if ( empty( $img_url ) ) {
                $img_url = dsrw_extract_first_image( $contenido );
            }

            if ( ! empty( $img_url ) ) {
                $attachment_id = dsrw_upload_featured_image( $img_url, $post_id );
                if ( $attachment_id ) {
                    set_post_thumbnail( $post_id, $attachment_id );
                } else {
                    // Imagen inválida; se fuerza la generación de miniatura
                    $img_url = '';
                }
            }
        }
        
        // Si no hay imagen válida y está activa la generación automática
        if ( empty( $img_url ) && get_option('dsrw_enable_thumbnail_generator') === '1' ) {
            $tmp_img = dsrw_generate_thumbnail_with_text( $nuevo_titulo );
            if ( file_exists($tmp_img) ) {
                $upload = media_handle_sideload( array(
                    'name'     => basename($tmp_img),
                    'tmp_name' => $tmp_img,
                ), $post_id );

                if ( ! is_wp_error( $upload ) ) {
                    set_post_thumbnail( $post_id, $upload );
                    dsrw_write_log('[AutoNews] Miniatura generada con el título y asignada correctamente.');
                } else {
                    dsrw_write_log('[AutoNews] Error al subir miniatura generada: ' . $upload->get_error_message());
                }

                @unlink($tmp_img);
            }
        }
    }
}

/**
 * Elimina todas las imágenes <img>, enlaces <a> y atributos innecesarios del contenido HTML.
 *
 * @param string $html Contenido HTML original.
 * @return string Contenido limpio.
 */
function dsrw_clean_article_content( $html ) {
    if ( empty( $html ) ) {
        return '';
    }
    // Eliminar todas las etiquetas <img> (Esta regla la movemos DESPUÉS de contar las imágenes)
    // $html = preg_replace('/<img[^>]+\>/i', '', $html); 
    // ^-- Esta línea está comentada aquí a propósito. La lógica se movió a dsrw_process_single_feed

    
    // --- ¡CORRECCIÓN! ---
    // Reemplaza la antigua regla por una que elimina TODOS los <a> 
    // sin importar comillas o tipo de href, pero conserva el texto.
    // 's' (DOTALL) = . incluye saltos de línea
    // 'i' (CASE-INSENSITIVE) = ignora mayús/minús
    $html = preg_replace( '/<a\s+[^>]*href\s*=\s*["\'].*?["\'][^>]*>(.*?)<\/a>/is', '$1', $html );
    // --- FIN CORRECCIÓN ---

    // --- ¡NUEVA MEJORA MULTI-IDIOMA! ---
    // Lista de palabras basura a eliminar (expresiones regulares separadas por | )
    $junk_words = [
        // Español
        'lee la crónica', 'leer más', 'ver más', 'click aquí', 'pincha aquí', 'seguir leyendo', 'más información',
        // Inglés
        'read more', 'click here', 'continue reading', 'more information',
        // Alemán
        'weiterlesen', 'mehr lesen', 'klicken Sie hier', 'mehr erfahren',
        // Francés
        'lire la suite', 'en savoir plus', 'cliquez ici',
        // Noruego
        'les mer', 'klikk her', 'fortsett å lese',
        // Islandés
        'lesa meira', 'smelltu hér', 'halda áfram að lesa',
        // Sueco
        'läs mer', 'klicka här', 'fortsätt läsa'
    ];
    
    // Construye la expresión regular
    $regex = '/[\s\(]+(' . implode('|', $junk_words) . ')[\s\)]+/i';
    
    // Elimina "junk" residual de los enlaces
    $html = preg_replace($regex, '', $html);

    // --- ¡NUEVA CORRECCIÓN! ---
    // Eliminar párrafos o líneas que contengan avisos de copyright
    // El modificador 'i' es para case-insensitive (ignora mayús/minús)
    // El modificador 's' es para que '.' incluya saltos de línea (por si el <p> tiene saltos)
    $html = preg_replace('/<p[^>]*>.*?(©|Copyright|Prohibida la reproducción|Todos los derechos reservados).*?<\/p>/is', '', $html);
    // --- FIN CORRECCIÓN ---
    
    // Eliminar atributos style y on* por seguridad
    $html = preg_replace('/\s*(style|on[a-z]+)\s*=\s*["\'][^"\']*["\']/i', '', $html);

    // AHORA SÍ: Eliminar todas las etiquetas <img> al final (después de contarlas)
    $html = preg_replace('/<img[^>]+\>/i', '', $html);

    return trim($html);
}

/**
 * Elimina encabezados duplicados, conservando solo el primer <h1> y el primer <h2>.
 *
 * @param string $content Contenido HTML.
 * @return string Contenido con encabezados depurados.
 */
function dsrw_cleanup_headings($content) {
    $first_h1 = true;
    $content = preg_replace_callback('/(<h1[^>]*>.*?<\/h1>)/is', function($matches) use (&$first_h1) {
        if ($first_h1) {
            $first_h1 = false;
            return $matches[0];
        }
        return '';
    }, $content);

    $first_h2 = true;
    $content = preg_replace_callback('/(<h2[^>]*>.*?<\/h2>)/is', function($matches) use (&$first_h2) {
        if ($first_h2) {
            $first_h2 = false;
            return $matches[0];
        }
        return '';
    }, $content);

    return $content;
}

/**
 * Reemplaza etiquetas <b> por <strong>, convierte Markdown (**) a <strong>,
 * y repara HTML roto (etiquetas sin cerrar/abrir).
 *
 * @param string $content Contenido HTML.
 * @return string Contenido con negritas optimizadas y reparadas.
 */
function dsrw_cleanup_bold($content) {
    
    // --- NUEVA MODIFICACIÓN ---
    // 1. Convertir Markdown (**) a <strong>
    // Busca **texto** y lo reemplaza por <strong>texto</strong>
    // El modificador 's' hace que '.' incluya saltos de línea, por si la negrita los tiene.
    $content = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $content);
    // --- FIN MODIFICACIÓN ---

    // 2. Convertir <b> a <strong> (lógica anterior)
    $content = str_ireplace(array('<b>', '</b>'), array('<strong>', '</strong>'), $content);
    
    // --- NUEVA MODIFICACIÓN ---
    // 3. Reparar HTML roto (etiquetas huérfanas)
    // Esta es la función principal de WordPress para arreglar HTML mal formado.
    // Arreglará un <strong> sin </strong>, o un </strong> sin <strong>.
    if ( function_exists('force_balance_tags') ) {
        $content = force_balance_tags($content);
    }
    // --- FIN MODIFICACIÓN ---

    // 4. Eliminar duplicados (lógica anterior)
    $content = preg_replace('/(<strong>\s*)+/', '<strong>', $content);
    $content = preg_replace('/(\s*<\/strong>)+/', '</strong>', $content);
    
    return $content;
}


/**
 * Elimina <figure> (o <img>) cuyo src sea "#" (o vacío).
 *
 * @param string $content Contenido HTML.
 * @return string Contenido limpio.
 */
function dsrw_remove_placeholder_images( $content ) {
    // 1) Eliminar figuras enteras con <img src="#">
    $pattern_figure = '/<figure[^>]*>\s*<img[^>]+src=["\']#["\'][^>]*>.*?<\/figure>/is';
    $content = preg_replace($pattern_figure, '', $content);

    // 2) Eliminar cualquier <img> cuyo src sea "#"
    $pattern_img = '/<img[^>]+src=["\']#["\'][^>]*>/is';
    $content = preg_replace($pattern_img, '', $content);

    return $content;
}

/**
 * Busca la mejor coincidencia entre la categoría sugerida y las existentes.
 *
 * @param string $categoria_sugerida Categoría sugerida.
 * @return mixed ID de la categoría coincidente o false.
 */
function dsrw_find_best_category_match( $categoria_sugerida ) {
    $categoria_sugerida = strtolower( trim( $categoria_sugerida ) );
    $existing_categories = get_categories( array( 'hide_empty' => false ) );
    $best_match = false;
    $highest_similarity = 0;

    foreach ( $existing_categories as $category ) {
        $existing_name = strtolower( $category->name );
        similar_text( $existing_name, $categoria_sugerida, $percent );
        if ( $percent > $highest_similarity ) {
            $highest_similarity = $percent;
            $best_match = $category;
        }
    }
    if ( $highest_similarity >= 80 ) {
        return $best_match->term_id;
    }
    return false;
}

/**
 * Comprueba si existe un post (incluido 'publish') con este hash.
 *
 * @param string $hash  MD5 de la URL normalizada.
 * @return bool         True si ya existe, false otherwise.
 */
function dsrw_is_duplicate( $hash ) {
    $existing = get_posts( array(
        'post_type'      => 'post',
        'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
        'meta_key'       => '_dsrw_original_hash',
        'meta_value'     => $hash,
        'fields'         => 'ids',
        'numberposts'    => 1,
        'suppress_filters' => false,
    ) );
    return ! empty( $existing );
}

/**
 * Reescribe un artículo usando la API de OpenAI.
 *
 * @param string $titulo Título original.
 * @param string $contenido Contenido original.
 * @param string $api_key Clave de API.
 * @param string $api_base Base URL de la API.
 * @param string $category_list_string Lista de categorías de WP (nueva)
 * @return mixed Array decodificado con el contenido reescrito, o false en caso de error.
 */
function dsrw_rewrite_article( $titulo, $contenido, $api_key, $api_base, $category_list_string ) {
    $language = get_option( 'dsrw_selected_language', 'es' );
    
    // --- ¡NUEVA MEJORA! (Pasar la lista de categorías) ---
    $prompt = dsrw_get_prompt_template( $language, $titulo, $contenido, $category_list_string ); 
    // --- FIN MEJORA ---

    // --- NUEVA MEJORA 2 ---
    // Leer los ajustes de modelo y temperatura de la base de datos
    $model = get_option( 'dsrw_openai_model', 'gpt-4.1-nano' ); // Default: 4.1-nano
    $temperature = (float) get_option( 'dsrw_openai_temperature', 0.2 ); // Default: 0.2
    
    // Asegurarse de que el modelo no esté vacío
    if ( empty($model) ) {
        $model = 'gpt-4.1-nano';
    }
    // --- FIN MEJORA 2 ---

    $post_data = array(
        'model'             => $model, // <-- ¡AHORA ES DINÁMICO!
        'messages'          => array(
            array( 'role' => 'user', 'content' => $prompt )
        ),
        // 'temperature'    => $temperature, // Movido a la lógica condicional
        // 'frequency_penalty' => 0.5, // Movido a la lógica condicional
        // 'presence_penalty'  => 0.3, // Movido a la lógica condicional
    );

    // --- ¡NUEVA CORRECCIÓN! (Parámetros condicionales) ---
    // Lista de modelos "básicos" que no soportan parámetros avanzados
    $basic_models = array('gpt-5-nano'); 
    
    if ( ! in_array( $model, $basic_models ) ) {
        // Si es un modelo "avanzado" (4.1-nano, 4o-mini), añadimos los parámetros
        $post_data['temperature'] = $temperature;
        $post_data['frequency_penalty'] = 0.5;
        $post_data['presence_penalty'] = 0.3;
        $post_data['max_tokens'] = 4096; // <-- LÍMITE AUMENTADO
    } else {
        // Si es un modelo "básico" (gpt-5-nano)
        $post_data['max_completion_tokens'] = 4096; // <-- LÍMITE AUMENTADO
        // No añadimos 'temperature', 'frequency_penalty', o 'presence_penalty' para que use los defaults
    }
    // --- FIN CORRECCIÓN ---


    $headers = array(
        'Content-Type'  => 'application/json',
        'Authorization' => 'Bearer ' . $api_key
    );
    dsrw_write_log( "[AutoNews RSS Rewriter] " . __( 'Enviando solicitud a la API: ', 'autonews-rss-rewriter' ) . $api_base . '/v1/chat/completions' );
    dsrw_write_log( "[AutoNews RSS Rewriter] " . __( 'Usando Modelo: ', 'autonews-rss-rewriter' ) . $model . ', Temp: ' . (isset($post_data['temperature']) ? $post_data['temperature'] : 'default') );
    dsrw_write_log( "[AutoNews RSS Rewriter] " . __( 'Datos enviados (prompt recortado): ', 'autonews-rss-rewriter' ) . substr(json_encode( $post_data ), 0, 500) . '...' );
    
    $response = wp_remote_post(
        $api_base . '/v1/chat/completions',
        array(
            'headers' => $headers,
            'body'    => json_encode( $post_data ),
            'timeout' => 60,
        )
    );
    if ( is_wp_error( $response ) ) {
        dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'Error HTTP: ', 'autonews-rss-rewriter' ) . $response->get_error_message() );
        dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Error HTTP', 'autonews-rss-rewriter' ), __( 'Error HTTP: ', 'autonews-rss-rewriter' ) . $response->get_error_message() );
        return false;
    }
    $code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    dsrw_write_log( "[AutoNews RSS Rewriter] " . __( 'Código HTTP de respuesta: ', 'autonews-rss-rewriter' ) . $code );
    dsrw_write_log( "[AutoNews RSS Rewriter] " . __( 'Cuerpo de la respuesta (recortado): ', 'autonews-rss-rewriter' ) . substr($response_body, 0, 500) . '...' );
    if ( $code !== 200 ) {
        if ( $code === 429 ) {
            dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Límite de Tasa Excedido', 'autonews-rss-rewriter' ), __( 'Has excedido el límite de tasa de la API. Intenta nuevamente más tarde.', 'autonews-rss-rewriter' ) );
        } elseif ( $code === 401 ) {
            dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'Error 401: Autenticación fallida. Verifica tu clave de API.', 'autonews-rss-rewriter' ) );
            dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Error de Autenticación', 'autonews-rss-rewriter' ), __( 'Error 401: Autenticación fallida. Verifica tu clave de API.', 'autonews-rss-rewriter' ) );
        } else {
            dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Error en la Respuesta de la API', 'autonews-rss-rewriter' ), __( 'Código de estado HTTP: ', 'autonews-rss-rewriter' ) . $code . __( '. Respuesta: ', 'autonews-rss-rewriter' ) . $response_body );
        }
        return false;
    }
    $body = json_decode( $response_body, true );
    if ( ! is_array( $body ) || empty( $body['choices'] ) ) {
        dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'Respuesta inesperada de la API.', 'autonews-rss-rewriter' ) );
        dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Respuesta Inesperada', 'autonews-rss-rewriter' ), __( 'Respuesta inesperada de la API.', 'autonews-rss-rewriter' ) );
        return false;
    }
    $raw_content = $body['choices'][0]['message']['content'];
    // Modificación de limpieza del JSON
    $raw_content = preg_replace('/^```json\s*/i', '', trim($raw_content));
    $raw_content = preg_replace('/\s*```$/i', '', $raw_content);
    $decoded = json_decode( $raw_content, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'Error al parsear JSON: ', 'autonews-rss-rewriter' ) . $raw_content );
        dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Error al Parsear JSON', 'autonews-rss-rewriter' ), __( 'JSON Recibido: ', 'autonews-rss-rewriter' ) . $raw_content );
        return false;
    }
    return $decoded;
}

/**
 * Obtiene el contenido completo del artículo usando la librería Readability.
 *
 * @param string $url URL del artículo.
 * @return mixed Contenido HTML limpio o false en caso de error.
 */
function dsrw_get_full_content( $url ) {
    if ( ! class_exists( 'andreskrey\Readability\Readability' ) ) {
        dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'La clase Readability no está disponible.', 'autonews-rss-rewriter' ) );
        dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Readability No Disponible', 'autonews-rss-rewriter' ), __( 'La clase Readability no está disponible.', 'autonews-rss-rewriter' ) );
        return false;
    }
    $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
    if ( is_wp_error( $response ) ) {
        dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'Error al obtener el contenido completo: ', 'autonews-rss-rewriter' ) . $response->get_error_message() );
        dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Error al Obtener Contenido', 'autonews-rss-rewriter' ), __( 'Error al obtener el contenido completo: ', 'autonews-rss-rewriter' ) . $response->get_error_message() );
        return false;
    }
    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'Cuerpo de la respuesta vacío al obtener el contenido completo.', 'autonews-rss-rewriter' ) );
        dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Contenido Vacío', 'autonews-rss-rewriter' ), __( 'Cuerpo de la respuesta vacío al obtener el contenido completo.', 'autonews-rss-rewriter' ) );
        return false;
    }
    $config = new \andreskrey\Readability\Configuration();
    $config->setFixRelativeURLs(true)
           ->setOriginalURL($url);
    $readability = new \andreskrey\Readability\Readability($config);
    try {
        $readability->parse($body);
        return $readability->getContent();
    } catch (\andreskrey\Readability\ParseException $e) {
        dsrw_write_log( '[AutoNews RSS Rewriter] ' . __( 'Error al parsear el contenido: ', 'autonews-rss-rewriter' ) . $e->getMessage() );
        dsrw_send_error_email( __( 'AutoNews RSS Rewriter - Error al Parsear Contenido', 'autonews-rss-rewriter' ), __( 'Error al parsear el contenido: ', 'autonews-rss-rewriter' ) . $e->getMessage() );
        return false;
    }
}