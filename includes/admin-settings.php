<?php
/**
 * Archivo: admin-settings.php
 * Función: Registro de ajustes, creación del menú y página de administración.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acceso directo.
}

// --- ¡NUEVA FUNCIÓN! ---
/**
 * Sanitiza el array de mapeo de categorías de feed.
 * Asegura que solo se guarden valores válidos ('', 'none', o IDs numéricos).
 *
 * @param array $input El array recibido del formulario.
 * @return array El array sanitizado.
 */
function sanitize_feed_categories_array( $input ) {
    $sanitized = array();
    if ( is_array( $input ) ) {
        foreach ( $input as $index => $value ) {
            // Limpia el valor primero (quita espacios, etc.)
            $clean_value = sanitize_text_field( trim( $value ) );
            $index_int = intval( $index ); // Asegura que el índice sea numérico

            // Permite cadena vacía '', 'none', o un entero positivo (ID de categoría)
            if ( $clean_value === '' || $clean_value === 'none' || absint( $clean_value ) > 0 ) {
                $sanitized[ $index_int ] = $clean_value;
            } else {
                 // Si no es válido, guarda cadena vacía (opción por defecto 'Automática')
                 $sanitized[ $index_int ] = '';
            }
        }
    }
    // Reindexar el array por si acaso (aunque no debería ser necesario con índices numéricos)
    // return array_values($sanitized); // Comentado: Mejor mantener los índices originales que vienen del formulario
    return $sanitized;
}
// --- FIN NUEVA FUNCIÓN ---


/**
 * Registrar configuraciones y ajustes del plugin.
 */
function dsrw_register_settings() {
    register_setting( 'dsrw_options_group', 'dsrw_rss_urls', 'dsrw_validate_rss_urls' );
    
    // --- MODIFICACIÓN AQUÍ ---
    // Usamos la nueva función de sanitización para el mapeo de categorías
    register_setting( 'dsrw_options_group', 'dsrw_feed_categories', 'sanitize_feed_categories_array' );
    // --- FIN MODIFICACIÓN ---

    register_setting( 'dsrw_options_group', 'dsrw_openai_api_key', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_openai_api_base', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_num_articulos', 'absint' );
    register_setting( 'dsrw_options_group', 'dsrw_publish_delay', 'absint' );
    register_setting( 'dsrw_options_group', 'dsrw_cron_interval', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_default_author', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_selected_language', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_enable_thumbnail_generator', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_thumbnail_custom_bg_id', 'absint' ); 
    register_setting( 'dsrw_options_group', 'dsrw_thumbnail_bg_color', 'sanitize_hex_color' );
    register_setting( 'dsrw_options_group', 'dsrw_thumbnail_text_color', 'sanitize_hex_color' );
    register_setting( 'dsrw_options_group', 'dsrw_thumbnail_font_size', 'absint' );
    register_setting( 'dsrw_options_group', 'dsrw_enable_image_extraction', 'intval' );
    register_setting('dsrw_options_group', 'dsrw_allow_category_creation', 'intval');
}
add_action( 'admin_init', 'dsrw_register_settings' );

/**
 * Crear el menú y submenús en el panel de administración.
 */
function dsrw_create_menu() {
    add_menu_page(
        __( 'AutoNews RSS Rewriter', 'autonews-rss-rewriter' ),
        __( 'AutoNews Rewriter', 'autonews-rss-rewriter' ),
        'manage_options',
        'dsrw-settings',
        'dsrw_settings_page',
        'dashicons-rss',
        81
    );

    add_submenu_page(
        'dsrw-settings',
        __( 'Registro de Actividad', 'autonews-rss-rewriter' ),
        __( 'Registro de Actividad', 'autonews-rss-rewriter' ),
        'manage_options',
        'dsrw-logs',
        'dsrw_logs_page'
    );
}
add_action( 'admin_menu', 'dsrw_create_menu' );

add_action('admin_enqueue_scripts', 'dsrw_admin_scripts');
function dsrw_admin_scripts($hook) {
    // Solo cargar en nuestra página de ajustes
    if ($hook !== 'toplevel_page_dsrw-settings') {
        return;
    }

    // Carga los scripts de la biblioteca de medios de WordPress
    wp_enqueue_media();

     // Encolar la hoja de estilo
    wp_enqueue_style(
        'dsrw-admin-css',
        plugin_dir_url(__FILE__) . '../assets/dsrw-admin.css',
        array(),
        '1.0.2', 
        'all'
    );

    // Encolar el script
    wp_enqueue_script(
        'dsrw-admin-js',
        plugin_dir_url(__FILE__) . '../assets/dsrw-admin.js',
        array('jquery', 'media-models'), 
        '1.0.2', 
        true
    );

    // Pasar datos al script: la URL de admin-ajax y un nonce
    wp_localize_script('dsrw-admin-js', 'dsrwAjax', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('dsrw_run_feeds_nonce') 
    ));
}

/**
 * Página de configuración del plugin.
 */
function dsrw_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Definir los idiomas disponibles
    $available_languages = array(
        'es' => __( 'Español', 'autonews-rss-rewriter' ),
        'de' => __( 'Alemán', 'autonews-rss-rewriter' ),
        'en' => __( 'Inglés', 'autonews-rss-rewriter' ),
        'fr' => __( 'Francés', 'autonews-rss-rewriter' ),
        'no' => __( 'Noruego', 'autonews-rss-rewriter' ),
        'is' => __( 'Islandés', 'autonews-rss-rewriter' ),
        'sv' => __( 'Sueco', 'autonews-rss-rewriter' ),
    );

    // Obtener estadísticas
    $total_posts = count( get_posts( array(
        'meta_key'    => '_dsrw_original_hash',
        'post_type'   => 'post',
        'numberposts' => -1,
    ) ) );
    $total_feeds = count( array_filter( array_map( 'trim', explode( "\n", get_option('dsrw_rss_urls') ) ) ) );
    $selected_language = get_option( 'dsrw_selected_language', 'es' );
    
    // Debug: Muestra el valor guardado (puedes borrar esto después)
    // echo '<pre>Valor guardado en dsrw_feed_categories: ';
    // var_dump(get_option('dsrw_feed_categories'));
    // echo '</pre>';

    ?>

    <div class="wrap dsrw-settings-page">
        <h1><?php esc_html_e( 'AutoNews - Configuración', 'autonews-rss-rewriter' ); ?></h1>
        
        <div class="dsrw-summary-wrapper">
            <h2><?php esc_html_e( 'Resumen', 'autonews-rss-rewriter' ); ?></h2>
            <ul class="dsrw-summary">
                <li><?php esc_html_e( 'Total de Feeds Configurados: ', 'autonews-rss-rewriter' ); ?><?php echo esc_html( $total_feeds ); ?></li>
                <li><?php esc_html_e( 'Total de Posts Publicados: ', 'autonews-rss-rewriter' ); ?><?php echo esc_html( $total_posts ); ?></li>
            </ul>
        </div>

        <h2 class="nav-tab-wrapper">
            <a href="#tab-main" class="nav-tab"><?php esc_html_e( 'Configuración Principal', 'autonews-rss-rewriter' ); ?></a>
            <a href="#tab-api" class="nav-tab"><?php esc_html_e( 'API (OpenAI)', 'autonews-rss-rewriter' ); ?></a>
            <a href="#tab-publishing" class="nav-tab"><?php esc_html_e( 'Publicación', 'autonews-rss-rewriter' ); ?></a>
            <a href="#tab-images" class="nav-tab"><?php esc_html_e( 'Imágenes', 'autonews-rss-rewriter' ); ?></a>
            <a href="#tab-tools" class="nav-tab"><?php esc_html_e( 'Herramientas', 'autonews-rss-rewriter' ); ?></a>
        </h2>

        <form method="post" action="options.php" class="dsrw-settings-form">
            <?php
            // Protege y registra nuestros campos
            settings_fields( 'dsrw_options_group' );
            do_settings_sections( 'dsrw_options_group' );
            ?>

            <div id="tab-main" class="tab-content">
                <div class="dsrw-field-group">
                    <label for="dsrw_rss_urls"><?php esc_html_e( 'Feeds RSS (uno por línea)', 'autonews-rss-rewriter' ); ?></label>
                    <textarea 
                        id="dsrw_rss_urls" 
                        name="dsrw_rss_urls" 
                        rows="5" 
                        cols="50" 
                        placeholder="https://tusitio.com/feed
https://otro-sitio.com/feed"
                    ><?php echo esc_textarea( get_option('dsrw_rss_urls') ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Ingresa las URLs de los feeds RSS que deseas procesar, una por línea.', 'autonews-rss-rewriter' ); ?></p>
                </div>

                <div class="dsrw-field-group">
                    <label><?php esc_html_e( 'Mapeo de Feeds a Categorías', 'autonews-rss-rewriter' ); ?></label>
                    <p><?php esc_html_e( 'Asigna una categoría de WordPress a cada feed RSS.', 'autonews-rss-rewriter' ); ?></p>
                    <?php
                    $rss_urls_raw = get_option( 'dsrw_rss_urls', '' );
                    $feed_categories = get_option( 'dsrw_feed_categories', array() ); // Obtiene el array guardado
                    $rss_urls = array_filter( array_map( 'trim', explode( "\n", $rss_urls_raw ) ) );
                    
                    // Asegúrate de que $feed_categories sea un array
                    if (!is_array($feed_categories)) {
                        $feed_categories = array();
                    }

                    foreach ( $rss_urls as $index => $url ) :
                        // Obtiene el valor guardado para este índice específico, o '' si no existe
                        $saved_value = isset($feed_categories[$index]) ? $feed_categories[$index] : ''; 
                        ?>
                        <div class="dsrw-feed-mapping">
                            <label for="dsrw_feed_categories_<?php echo esc_attr( $index ); ?>"><?php echo esc_html( $url ); ?></label>
                            <?php
                            $categories = get_categories( array( 'hide_empty' => false ) );
                            ?>
                            <select name="dsrw_feed_categories[<?php echo esc_attr( $index ); ?>]" id="dsrw_feed_categories_<?php echo esc_attr( $index ); ?>">
                                <option value="" <?php selected( $saved_value, '' ); ?>>
                                    <?php esc_html_e( '-- Categoría Automática (IA) --', 'autonews-rss-rewriter' ); ?>
                                </option>
                                
                                <?php foreach ( $categories as $category ) : ?>
                                    <option 
                                        value="<?php echo esc_attr( $category->term_id ); ?>" 
                                        <?php selected( $saved_value, (string) $category->term_id ); // Compara como strings ?>
                                    >
                                        <?php echo esc_html( $category->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                                
                                <option 
                                    value="none" 
                                    <?php selected( $saved_value, 'none' ); ?>
                                >
                                    <?php esc_html_e( '-- Ninguna --', 'autonews-rss-rewriter' ); ?>
                                </option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php esc_html_e( 'Selecciona la categoría correspondiente para cada feed RSS. "Categoría Automática" usará la sugerencia de la IA.', 'autonews-rss-rewriter' ); ?>
                    </p>
                </div>


                <div class="dsrw-field-group">
                    <label for="dsrw_default_author"><?php esc_html_e( 'Autor Predeterminado', 'autonews-rss-rewriter' ); ?></label>
                    <?php
                    $users = get_users( array(
                        'who'     => 'authors',
                        'orderby' => 'display_name',
                        'order'   => 'ASC',
                    ) );
                    ?>
                    <select name="dsrw_default_author" id="dsrw_default_author">
                        <option value="">
                            <?php esc_html_e( '-- Seleccionar Autor --', 'autonews-rss-rewriter' ); ?>
                        </option>
                        <option 
                            value="random" 
                            <?php selected( get_option('dsrw_default_author'), 'random' ); ?>
                        >
                            <?php esc_html_e( '-- Aleatorio --', 'autonews-rss-rewriter' ); ?>
                        </option>
                        <?php foreach ( $users as $user ) : ?>
                            <option 
                                value="<?php echo esc_attr( $user->ID ); ?>" 
                                <?php selected( get_option('dsrw_default_author'), $user->ID ); ?>
                            >
                                <?php echo esc_html( $user->display_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Selecciona el autor predeterminado para los posts generados o elige "Aleatorio".', 'autonews-rss-rewriter' ); ?>
                    </p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_selected_language">
                        <?php esc_html_e( 'Idioma de Respuesta', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <select name="dsrw_selected_language" id="dsrw_selected_language">
                        <?php 
                        foreach ( $available_languages as $lang_code => $lang_name ) {
                            echo '<option value="' . esc_attr( $lang_code ) . '"' 
                                 . selected( $selected_language, $lang_code, false ) 
                                 . '>' . esc_html( $lang_name ) . '</option>';
                        } 
                        ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Selecciona el idioma en el que deseas que la API reescriba los artículos.', 'autonews-rss-rewriter' ); ?>
                    </p>
                </div>
            </div> <div id="tab-api" class="tab-content">
                <div class="dsrw-field-group">
                    <label for="dsrw_openai_api_key">
                        <?php esc_html_e( 'OpenAI API Key', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <input 
                        type="password" 
                        name="dsrw_openai_api_key" 
                        id="dsrw_openai_api_key"
                        value="<?php echo esc_attr( get_option('dsrw_openai_api_key') ); ?>" 
                        size="50" 
                        placeholder="<?php esc_attr_e( 'Ingresa tu clave de API aquí', 'autonews-rss-rewriter' ); ?>" 
                    />
                    <p class="description">
                        <?php esc_html_e( 'Tu clave de API proporcionada por OpenAI.', 'autonews-rss-rewriter' ); ?>
                    </p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_openai_api_base">
                        <?php esc_html_e( 'OpenAI API Base (URL)', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <input 
                        type="text" 
                        name="dsrw_openai_api_base" 
                        id="dsrw_openai_api_base"
                        value="<?php echo esc_attr( get_option('dsrw_openai_api_base', 'https://api.openai.com') ); ?>" 
                        size="50" 
                        placeholder="https://api.openai.com" 
                    />
                    <p class="description">
                        <?php esc_html_e( 'URL base de la API de OpenAI. Modifícala solo si es necesario.', 'autonews-rss-rewriter' ); ?>
                    </p>
                </div>
            </div> <div id="tab-publishing" class="tab-content">
                <div class="dsrw-field-group">
                    <label for="dsrw_num_articulos">
                        <?php esc_html_e( 'Número de artículos a procesar por feed', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <input 
                        type="number" 
                        name="dsrw_num_articulos" 
                        id="dsrw_num_articulos"
                        value="<?php echo esc_attr( get_option('dsrw_num_articulos', 5) ); ?>" 
                        min="1" 
                        max="50" 
                    />
                    <p class="description">
                        <?php esc_html_e( 'Define cuántos artículos deseas procesar por cada feed en cada ejecución.', 'autonews-rss-rewriter' ); ?>
                    </p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_publish_delay">
                        <?php esc_html_e( 'Desfase de Publicación (minutos)', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <input 
                        type="number" 
                        name="dsrw_publish_delay" 
                        id="dsrw_publish_delay"
                        value="<?php echo esc_attr( get_option('dsrw_publish_delay', 0) ); ?>" 
                        min="0" 
                        max="4320" 
                    />
                    <p class="description">
                        <?php esc_html_e( 'Minutos de diferencia entre publicaciones (0 para inmediato).', 'autonews-rss-rewriter' ); ?>
                    </p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_cron_interval">
                        <?php esc_html_e( 'Intervalo de Cron (minutos)', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <select name="dsrw_cron_interval" id="dsrw_cron_interval">
                        <option 
                            value="disabled" 
                            <?php selected( get_option('dsrw_cron_interval'), 'disabled' ); ?>
                        >
                            <?php esc_html_e( '-- Deshabilitado --', 'autonews-rss-rewriter' ); ?>
                        </option>
                        <option 
                            value="30" 
                            <?php selected( get_option('dsrw_cron_interval'), '30' ); ?>
                        >
                            <?php esc_html_e( 'Cada 30 minutos', 'autonews-rss-rewriter' ); ?>
                        </option>
                        <option 
                            value="60" 
                            <?php selected( get_option('dsrw_cron_interval'), '60' ); ?>
                        >
                            <?php esc_html_e( 'Cada hora', 'autonews-rss-rewriter' ); ?>
                        </option>
                        <option 
                            value="120" 
                            <?php selected( get_option('dsrw_cron_interval'), '120' ); ?>
                        >
                            <?php esc_html_e( 'Cada 2 horas', 'autonews-rss-rewriter' ); ?>
                        </option>
                        <option 
                            value="180" 
                            <?php selected( get_option('dsrw_cron_interval'), '180' ); ?>
                        >
                            <?php esc_html_e( 'Cada 3 horas', 'autonews-rss-rewriter' ); ?>
                        </option>
                        <option 
                            value="600" 
                            <?php selected( get_option('dsrw_cron_interval'), '600' ); ?>
                        >
                            <?php esc_html_e( 'Cada 10 horas', 'autonews-rss-rewriter' ); ?>
                        </option>
                        <option 
                            value="720" 
                            <?php selected( get_option('dsrw_cron_interval'), '720' ); ?>
                        >
                            <?php esc_html_e( 'Cada 12 horas', 'autonews-rss-rewriter' ); ?>
                        </option>
                        <option 
                            value="1440" 
                            <?php selected( get_option('dsrw_cron_interval'), '1440' ); ?>
                        >
                            <?php esc_html_e( 'Cada 24 horas', 'autonews-rss-rewriter' ); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Selecciona el intervalo para ejecutar la tarea cron.', 'autonews-rss-rewriter' ); ?>
                    </p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_allow_category_creation">
                        <?php esc_html_e('Permitir crear categorías sugeridas por la IA', 'autonews-rss-rewriter'); ?>
                    </label>
                    <input
                        type="checkbox"
                        name="dsrw_allow_category_creation"
                        id="dsrw_allow_category_creation"
                        value="1"
                        <?php checked(get_option('dsrw_allow_category_creation'), '1'); ?>
                    />
                    <span><?php esc_html_e('Si está activado, se creará la categoría sugerida si no existe.', 'autonews-rss-rewriter'); ?></span>
                </div>
            </div> <div id="tab-images" class="tab-content">
                <div class="dsrw-field-group">
                    <label for="dsrw_enable_thumbnail_generator">
                        <?php esc_html_e( 'Generar miniaturas automáticas con el título', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <input 
                        type="checkbox" 
                        name="dsrw_enable_thumbnail_generator" 
                        id="dsrw_enable_thumbnail_generator"
                        value="1" 
                        <?php checked( get_option('dsrw_enable_thumbnail_generator'), '1' ); ?> 
                    />
                    <span><?php esc_html_e( 'Activar generación automática si no hay imagen', 'autonews-rss-rewriter' ); ?></span>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_enable_image_extraction">
                        <?php esc_html_e( 'Extraer imágenes del contenido', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <input 
                        type="checkbox" 
                        name="dsrw_enable_image_extraction" 
                        id="dsrw_enable_image_extraction"
                        value="1" 
                        <?php checked( get_option('dsrw_enable_image_extraction'), '1' ); ?> 
                    />
                    <span><?php esc_html_e( 'Activar extracción de imágenes de los feeds', 'autonews-rss-rewriter' ); ?></span>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw-upload-bg-button">
                        <?php esc_html_e( 'Imagen de Fondo Personalizada (para miniaturas generadas)', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <?php
                    // Obtenemos el ID de la imagen guardada
                    $custom_bg_id = get_option('dsrw_thumbnail_custom_bg_id');
                    $bg_image_url = '';
                    if ( $custom_bg_id ) {
                        // Obtenemos la URL de la imagen en tamaño mediano para la vista previa
                        $bg_image_url = wp_get_attachment_image_url($custom_bg_id, 'medium');
                    }
                    ?>
                    <div class="dsrw-bg-preview-wrapper" style="margin: 10px 0; <?php echo $custom_bg_id ? '' : 'display: none;'; ?>">
                        <img id="dsrw-bg-preview" 
                             src="<?php echo esc_url($bg_image_url); ?>" 
                             style="max-width: 300px; height: auto; border: 1px solid #ddd;"/>
                    </div>
                    
                    <input type="hidden" 
                           id="dsrw_thumbnail_custom_bg_id" 
                           name="dsrw_thumbnail_custom_bg_id" 
                           value="<?php echo esc_attr($custom_bg_id); ?>">
                    
                    <button type="button" class="button" id="dsrw-upload-bg-button">
                        <?php esc_html_e('Elegir Imagen', 'autonews-rss-rewriter'); ?>
                    </button>
                    <button type="button" 
                            class="button button-link-delete" 
                            id="dsrw-remove-bg-button" 
                            style="<?php echo $custom_bg_id ? '' : 'display: none;'; ?>">
                        <?php esc_html_e('Quitar Imagen', 'autonews-rss-rewriter'); ?>
                    </button>

                    <p class="description">
                        <?php esc_html_e( 'Sube o elige una imagen de fondo. Si no eliges ninguna, se usará la imagen por defecto (wpblur.webp). Se recomienda 1200x630px.', 'autonews-rss-rewriter' ); ?>
                    </p>
                </div>
                <div class="dsrw-field-group">
                    <label for="dsrw_thumbnail_bg_color">
                        <?php esc_html_e( 'Color de Tinte de Fondo (Acento)', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <input 
                        type="color" 
                        name="dsrw_thumbnail_bg_color" 
                        id="dsrw_thumbnail_bg_color"
                        value="<?php echo esc_attr( get_option('dsrw_thumbnail_bg_color', '#0073aa') ); ?>"
                    />
                    <p class="description"><?php esc_html_e( 'Este color se aplicará como una capa semitransparente sobre la imagen de fondo.', 'autonews-rss-rewriter' ); ?></p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_thumbnail_text_color">
                        <?php esc_html_e( 'Color del texto en miniatura', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <input 
                        type="color" 
                        name="dsrw_thumbnail_text_color" 
                        id="dsrw_thumbnail_text_color"
                        value="<?php echo esc_attr( get_option('dsrw_thumbnail_text_color', '#ffffff') ); ?>"
                    />
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_thumbnail_font_size">
                        <?php esc_html_e( 'Tamaño de fuente en miniatura (px)', 'autonews-rss-rewriter' ); ?>
                    </label>
                    <input 
                        type="number" 
                        name="dsrw_thumbnail_font_size" 
                        id="dsrw_thumbnail_font_size"
                        value="<?php echo esc_attr( get_option('dsrw_thumbnail_font_size', 36) ); ?>" 
                        min="12" 
                        max="100"
                    />
                </div>
            </div> <?php 
            // Botón de guardar para todas las pestañas de ajustes
            submit_button(); 
            ?>
        </form>

        <div id="tab-tools" class="tab-content">
            
            <h2><?php esc_html_e( 'Administrar Tareas Cron', 'autonews-rss-rewriter' ); ?></h2>
            <p><?php esc_html_e( 'Programa o elimina la tarea cron para procesar los feeds RSS.', 'autonews-rss-rewriter' ); ?></p>
            <form method="post">
                <?php
                wp_nonce_field( 'dsrw_schedule_cron_action', 'dsrw_schedule_cron_nonce' );
                submit_button( __( 'Programar Tarea Cron', 'autonews-rss-rewriter' ), 'secondary', 'dsrw_schedule_cron', true, array( 'id' => 'dsrw_schedule_cron_button' ) );
                ?>
            </form>
            <form method="post" style="margin-top: 10px;">
                <?php
                wp_nonce_field( 'dsrw_unschedule_cron_action', 'dsrw_unschedule_cron_nonce' );
                submit_button( __( 'Eliminar Tarea Cron', 'autonews-rss-rewriter' ), 'secondary', 'dsrw_unschedule_cron', true, array( 'id' => 'dsrw_unschedule_cron_button' ) );
                ?>
            </form>

            <hr />
            
            <h2><?php esc_html_e( 'Ejecutar Procesamiento Manualmente', 'autonews-rss-rewriter' ); ?></h2>
            <p><?php esc_html_e( 'Haz clic para procesar inmediatamente todos los feeds RSS configurados.', 'autonews-rss-rewriter' ); ?></p>
            
            <p>
                <button type="button" class="button button-primary" id="autonews-manual-run-button">
                    <?php esc_html_e( 'Ejecutar Manualmente', 'autonews-rss-rewriter' ); ?>
                </button>
                <span id="dsrw_manual_spinner" style="display:none; margin-left: 10px; vertical-align: middle;">⏳ Procesando...</span>
            </p>
            
            <div id="autonews-manual-log" style="font-family: monospace; background: #f6f8fa; border: 1px solid #ccc; padding: 10px; margin-top: 10px; display: none; max-height: 400px; overflow-y: auto; box-shadow: inset 0 0 5px rgba(0,0,0,0.1);"></div>

        </div> </div> <?php
    // --- MANEJO DE FORMULARIOS CRON (SIN AJAX) ---
    // Esta lógica debe permanecer en la página para manejar los formularios de Cron
    
    if ( isset( $_POST['dsrw_schedule_cron'] ) ) {
        if ( ! isset( $_POST['dsrw_schedule_cron_nonce'] ) || ! wp_verify_nonce( $_POST['dsrw_schedule_cron_nonce'], 'dsrw_schedule_cron_action' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Permiso denegado para programar la tarea cron.', 'autonews-rss-rewriter' ) . '</p></div>';
        } else {
            $cron_interval = sanitize_text_field( get_option( 'dsrw_cron_interval', 'disabled' ) );
            if ( $cron_interval === 'disabled' ) {
                echo '<div class="notice notice-warning"><p>' . esc_html__( 'No se ha seleccionado un intervalo válido para programar la tarea cron.', 'autonews-rss-rewriter' ) . '</p></div>';
            } else {
                wp_clear_scheduled_hook( 'dsrw_cron_hook' );
                add_filter( 'cron_schedules', 'dsrw_add_custom_cron_intervals' );
                $scheduled = wp_schedule_event( time(), 'dsrw_interval_' . $cron_interval, 'dsrw_cron_hook' );
                if ( $scheduled ) {
                    echo '<div class="notice notice-success"><p>' . esc_html__( 'Tarea cron programada exitosamente cada ', 'autonews-rss-rewriter' ) . esc_html( dsrw_get_cron_interval_label( $cron_interval ) ) . '.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'No se pudo programar la tarea cron. Puede que ya esté programada.', 'autonews-rss-rewriter' ) . '</p></div>';
                }
            }
        }
    }

    if ( isset( $_POST['dsrw_unschedule_cron'] ) ) {
        if ( ! isset( $_POST['dsrw_unschedule_cron_nonce'] ) || ! wp_verify_nonce( $_POST['dsrw_unschedule_cron_nonce'], 'dsrw_unschedule_cron_action' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Permiso denegado para eliminar la tarea cron.', 'autonews-rss-rewriter' ) . '</p></div>';
        } else {
            $unscheduled = wp_clear_scheduled_hook( 'dsrw_cron_hook' );
            if ( $unscheduled ) {
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Tarea cron eliminada exitosamente.', 'autonews-rss-rewriter' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>' . esc_html__( 'No se encontró ninguna tarea cron para eliminar.', 'autonews-rss-rewriter' ) . '</p></div>';
            }
        }
    }
}


// --- Callback de AJAX ---
add_action('wp_ajax_autonews_manual_run', 'autonews_manual_run_callback');

/**
 * Función de callback de AJAX para ejecutar el procesamiento manual de feeds.
 */
function autonews_manual_run_callback() {
    
    // 1. Seguridad: Verificar Nonce y permisos
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'dsrw_run_feeds_nonce' ) ) {
        wp_send_json_error( [ 'logs' => [ '❌ Error de seguridad (Nonce inválido). Intenta recargar la página.' ] ], 403 );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'logs' => [ '❌ Error: No tienes permisos.' ] ], 403 );
    }

    // 2. Preparar el array de logs
    $logs = [];
    $logs[] = "▶️ " . esc_html__( 'Iniciando procesamiento manual...', 'autonews-rss-rewriter' );

    // 3. Ejecutar el proceso real (pasando el array $logs por referencia)
    try {
        dsrw_process_all_feeds( $logs );
    } catch (Exception $e) {
        $logs[] = "❌ " . esc_html__( 'Error fatal durante la ejecución: ', 'autonews-rss-rewriter' ) . $e->getMessage();
        dsrw_write_log( "[AutoNews] Error fatal en ejecución AJAX: " . $e->getMessage() );
        wp_send_json_error( [ 'logs' => $logs ] );
    }
    
    // 4. Devolver el resultado
    $logs[] = "✅ " . esc_html__( 'Proceso manual completado.', 'autonews-rss-rewriter' );
    wp_send_json_success( [ 'logs' => $logs ] );
}