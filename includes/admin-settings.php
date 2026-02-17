<?php
/**
 * Archivo: admin-settings.php
 * Función: Registro de ajustes, creación del menú y página de administración.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acceso directo.
}

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
            $clean_value = sanitize_text_field( trim( $value ) );
            $index_int = intval( $index );
            if ( $clean_value === '' || $clean_value === 'none' || absint( $clean_value ) > 0 ) {
                $sanitized[ $index_int ] = $clean_value;
            } else {
                 $sanitized[ $index_int ] = '';
            }
        }
    }
    return $sanitized;
}


/**
 * Sanitiza el array de intervalos de cron por feed.
 *
 * @param array $input El array recibido del formulario.
 * @return array El array sanitizado.
 */
function dsrw_sanitize_feed_cron_intervals( $input ) {
    $sanitized = array();
    $valid_values = array( 'disabled', '30', '60', '120', '180', '360', '720', '1440' );
    if ( is_array( $input ) ) {
        foreach ( $input as $index => $value ) {
            $clean_value = sanitize_text_field( trim( $value ) );
            $index_int = intval( $index );
            if ( in_array( $clean_value, $valid_values, true ) ) {
                $sanitized[ $index_int ] = $clean_value;
            } else {
                $sanitized[ $index_int ] = 'disabled';
            }
        }
    }
    return $sanitized;
}

/**
 * Sanitiza el array de número de artículos por feed.
 *
 * @param array $input El array recibido del formulario.
 * @return array El array sanitizado.
 */
function dsrw_sanitize_feed_num_articles( $input ) {
    $sanitized = array();
    if ( is_array( $input ) ) {
        foreach ( $input as $index => $value ) {
            $index_int = intval( $index );
            $val = absint( $value );
            if ( $val < 1 ) $val = 1;
            if ( $val > 50 ) $val = 50;
            $sanitized[ $index_int ] = $val;
        }
    }
    return $sanitized;
}


/**
 * Registrar configuraciones y ajustes del plugin.
 */
function dsrw_register_settings() {
    register_setting( 'dsrw_options_group', 'dsrw_rss_urls', 'dsrw_validate_rss_urls' );
    register_setting( 'dsrw_options_group', 'dsrw_feed_categories', 'sanitize_feed_categories_array' );
    register_setting( 'dsrw_options_group', 'dsrw_openai_api_key', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_openai_api_base', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_openai_model', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_openai_temperature', 'floatval' );
    register_setting( 'dsrw_options_group', 'dsrw_custom_prompt' ); 
    register_setting( 'dsrw_options_group', 'dsrw_num_articulos', 'absint' );
    register_setting( 'dsrw_options_group', 'dsrw_publish_delay', 'absint' );
    register_setting( 'dsrw_options_group', 'dsrw_parent_category_id', 'absint' );
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
    register_setting('dsrw_options_group', 'dsrw_enable_tags', 'intval');
    register_setting('dsrw_options_group', 'dsrw_feed_cron_intervals', 'dsrw_sanitize_feed_cron_intervals');
    register_setting('dsrw_options_group', 'dsrw_feed_num_articles', 'dsrw_sanitize_feed_num_articles');
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
    if ($hook !== 'toplevel_page_dsrw-settings') {
        return;
    }

    wp_enqueue_media();

    wp_enqueue_style(
        'dsrw-admin-css',
        plugin_dir_url(__FILE__) . '../assets/dsrw-admin.css',
        array(),
        '2.0.0',
        'all'
    );

    wp_enqueue_script(
        'dsrw-admin-js',
        plugin_dir_url(__FILE__) . '../assets/dsrw-admin.js',
        array('jquery', 'media-models'), 
        '2.0.0',
        true
    );

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
    
    $current_model = get_option( 'dsrw_openai_model', 'gpt-4.1-nano' );
    $current_temp = get_option( 'dsrw_openai_temperature', 0.2 );
    $custom_prompt = get_option( 'dsrw_custom_prompt', '' );

    // Datos para la tabla de feeds
    $rss_urls_raw = get_option( 'dsrw_rss_urls', '' );
    $rss_urls = array_filter( array_map( 'trim', explode( "\n", $rss_urls_raw ) ) );
    $feed_categories = get_option( 'dsrw_feed_categories', array() );
    $feed_cron_intervals = get_option( 'dsrw_feed_cron_intervals', array() );
    $feed_num_articles = get_option( 'dsrw_feed_num_articles', array() );
    $feed_status = get_option( 'dsrw_feed_status', array() );
    $categories = get_categories( array( 'hide_empty' => false ) );
    $global_num_articulos = (int) get_option( 'dsrw_num_articulos', 5 );

    if ( ! is_array( $feed_categories ) ) $feed_categories = array();
    if ( ! is_array( $feed_cron_intervals ) ) $feed_cron_intervals = array();
    if ( ! is_array( $feed_num_articles ) ) $feed_num_articles = array();
    if ( ! is_array( $feed_status ) ) $feed_status = array();

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
            <a href="#tab-feeds" class="nav-tab"><?php esc_html_e( 'Feeds RSS', 'autonews-rss-rewriter' ); ?></a>
            <a href="#tab-general" class="nav-tab"><?php esc_html_e( 'General', 'autonews-rss-rewriter' ); ?></a>
            <a href="#tab-api" class="nav-tab"><?php esc_html_e( 'API y Modelo', 'autonews-rss-rewriter' ); ?></a>
            <a href="#tab-publishing" class="nav-tab"><?php esc_html_e( 'Publicación', 'autonews-rss-rewriter' ); ?></a>
            <a href="#tab-images" class="nav-tab"><?php esc_html_e( 'Imágenes', 'autonews-rss-rewriter' ); ?></a>
            <a href="#tab-tools" class="nav-tab"><?php esc_html_e( 'Herramientas', 'autonews-rss-rewriter' ); ?></a>
        </h2>

        <form method="post" action="options.php" class="dsrw-settings-form">
            <?php
            settings_fields( 'dsrw_options_group' );
            do_settings_sections( 'dsrw_options_group' );
            ?>

            <!-- ===================== TAB FEEDS ===================== -->
            <div id="tab-feeds" class="tab-content">

                <div class="dsrw-field-group">
                    <label for="dsrw_rss_urls"><?php esc_html_e( 'Feeds RSS (uno por línea)', 'autonews-rss-rewriter' ); ?></label>
                    <textarea 
                        id="dsrw_rss_urls" 
                        name="dsrw_rss_urls" 
                        rows="4" 
                        cols="50" 
                        placeholder="https://tusitio.com/feed&#10;https://otro-sitio.com/feed"
                    ><?php echo esc_textarea( get_option('dsrw_rss_urls') ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Añade o elimina feeds aquí. Guarda para actualizar la tabla de abajo.', 'autonews-rss-rewriter' ); ?></p>
                </div>

                <?php if ( ! empty( $rss_urls ) ) : ?>
                <div class="dsrw-field-group">
                    <label><?php esc_html_e( 'Configuración por Feed', 'autonews-rss-rewriter' ); ?></label>
                    <p class="description" style="margin-bottom: 12px;"><?php esc_html_e( 'Configura cada feed individualmente. Recuerda pulsar "Activar Crons" en Herramientas después de guardar.', 'autonews-rss-rewriter' ); ?></p>
                    
                    <table class="widefat dsrw-feeds-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Feed', 'autonews-rss-rewriter' ); ?></th>
                                <th><?php esc_html_e( 'Categoría', 'autonews-rss-rewriter' ); ?></th>
                                <th><?php esc_html_e( 'Intervalo', 'autonews-rss-rewriter' ); ?></th>
                                <th><?php esc_html_e( 'Artículos', 'autonews-rss-rewriter' ); ?></th>
                                <th><?php esc_html_e( 'Estado', 'autonews-rss-rewriter' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $rss_urls as $index => $url ) :
                            $saved_cat = isset( $feed_categories[ $index ] ) ? $feed_categories[ $index ] : '';
                            $saved_cron = isset( $feed_cron_intervals[ $index ] ) ? $feed_cron_intervals[ $index ] : '180';
                            $saved_num = isset( $feed_num_articles[ $index ] ) ? intval( $feed_num_articles[ $index ] ) : $global_num_articulos;
                            $status = isset( $feed_status[ $index ] ) ? $feed_status[ $index ] : array();
                            $last_run = isset( $status['last_run'] ) ? $status['last_run'] : '';
                            $last_result = isset( $status['last_result'] ) ? $status['last_result'] : '';
                            $last_count = isset( $status['last_count'] ) ? intval( $status['last_count'] ) : 0;
                            $last_error = isset( $status['last_error'] ) ? $status['last_error'] : '';
                            $is_disabled = ( $saved_cron === 'disabled' );

                            // Calcular próxima ejecución
                            $hook_name = 'dsrw_feed_cron_hook_' . $index;
                            $next_run = wp_next_scheduled( $hook_name );
                        ?>
                            <tr class="<?php echo $is_disabled ? 'dsrw-feed-disabled' : ''; ?>">
                                <td class="dsrw-feed-url-cell">
                                    <strong title="<?php echo esc_attr( $url ); ?>"><?php 
                                        $parsed = parse_url( $url );
                                        $display_url = isset( $parsed['host'] ) ? $parsed['host'] : $url;
                                        if ( isset( $parsed['path'] ) && $parsed['path'] !== '/' && $parsed['path'] !== '/feed' && $parsed['path'] !== '/feed/' ) {
                                            $display_url .= $parsed['path'];
                                        }
                                        echo esc_html( $display_url );
                                    ?></strong>
                                </td>
                                <td>
                                    <select name="dsrw_feed_categories[<?php echo esc_attr( $index ); ?>]" class="dsrw-select-small">
                                        <option value="" <?php selected( $saved_cat, '' ); ?>><?php esc_html_e( 'Automática (IA)', 'autonews-rss-rewriter' ); ?></option>
                                        <?php foreach ( $categories as $category ) : ?>
                                            <option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $saved_cat, (string) $category->term_id ); ?>>
                                                <?php echo esc_html( $category->name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="none" <?php selected( $saved_cat, 'none' ); ?>><?php esc_html_e( 'Ninguna', 'autonews-rss-rewriter' ); ?></option>
                                    </select>
                                </td>
                                <td>
                                    <select name="dsrw_feed_cron_intervals[<?php echo esc_attr( $index ); ?>]" class="dsrw-select-small">
                                        <option value="30" <?php selected( $saved_cron, '30' ); ?>><?php esc_html_e( '30 min', 'autonews-rss-rewriter' ); ?></option>
                                        <option value="60" <?php selected( $saved_cron, '60' ); ?>><?php esc_html_e( '1 hora', 'autonews-rss-rewriter' ); ?></option>
                                        <option value="120" <?php selected( $saved_cron, '120' ); ?>><?php esc_html_e( '2 horas', 'autonews-rss-rewriter' ); ?></option>
                                        <option value="180" <?php selected( $saved_cron, '180' ); ?>><?php esc_html_e( '3 horas', 'autonews-rss-rewriter' ); ?></option>
                                        <option value="360" <?php selected( $saved_cron, '360' ); ?>><?php esc_html_e( '6 horas', 'autonews-rss-rewriter' ); ?></option>
                                        <option value="720" <?php selected( $saved_cron, '720' ); ?>><?php esc_html_e( '12 horas', 'autonews-rss-rewriter' ); ?></option>
                                        <option value="1440" <?php selected( $saved_cron, '1440' ); ?>><?php esc_html_e( '24 horas', 'autonews-rss-rewriter' ); ?></option>
                                        <option value="disabled" <?php selected( $saved_cron, 'disabled' ); ?>><?php esc_html_e( 'Desactivado', 'autonews-rss-rewriter' ); ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="dsrw_feed_num_articles[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $saved_num ); ?>" min="1" max="50" class="dsrw-input-tiny" />
                                </td>
                                <td class="dsrw-status-cell">
                                    <?php if ( $is_disabled ) : ?>
                                        <span class="dsrw-badge dsrw-badge-off" title="<?php esc_attr_e( 'Este feed está desactivado', 'autonews-rss-rewriter' ); ?>">⏸ <?php esc_html_e( 'Off', 'autonews-rss-rewriter' ); ?></span>
                                    <?php elseif ( empty( $last_run ) ) : ?>
                                        <span class="dsrw-badge dsrw-badge-pending" title="<?php esc_attr_e( 'Pendiente de primera ejecución', 'autonews-rss-rewriter' ); ?>">⏳ <?php esc_html_e( 'Pendiente', 'autonews-rss-rewriter' ); ?></span>
                                    <?php elseif ( $last_result === 'ok' ) : ?>
                                        <span class="dsrw-badge dsrw-badge-ok" title="<?php echo esc_attr( $last_run ); ?>">✅ <?php echo esc_html( $last_count ); ?> <?php esc_html_e( 'art.', 'autonews-rss-rewriter' ); ?></span>
                                        <br><small class="dsrw-status-time"><?php echo esc_html( dsrw_time_ago( $last_run ) ); ?></small>
                                    <?php elseif ( $last_result === 'error' ) : ?>
                                        <span class="dsrw-badge dsrw-badge-error" title="<?php echo esc_attr( $last_error ); ?>">❌ <?php esc_html_e( 'Error', 'autonews-rss-rewriter' ); ?></span>
                                        <br><small class="dsrw-status-time"><?php echo esc_html( dsrw_time_ago( $last_run ) ); ?></small>
                                    <?php endif; ?>

                                    <?php if ( $next_run && ! $is_disabled ) : ?>
                                        <br><small class="dsrw-next-run">▶ <?php echo esc_html( dsrw_time_until( $next_run ) ); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            </div>

            <!-- ===================== TAB GENERAL ===================== -->
            <div id="tab-general" class="tab-content">

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
                        <option value=""><?php esc_html_e( '-- Seleccionar Autor --', 'autonews-rss-rewriter' ); ?></option>
                        <option value="random" <?php selected( get_option('dsrw_default_author'), 'random' ); ?>><?php esc_html_e( '-- Aleatorio --', 'autonews-rss-rewriter' ); ?></option>
                        <?php foreach ( $users as $user ) : ?>
                            <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( get_option('dsrw_default_author'), $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Autor para los posts generados. "Aleatorio" elige uno distinto cada vez.', 'autonews-rss-rewriter' ); ?></p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_selected_language"><?php esc_html_e( 'Idioma de Respuesta', 'autonews-rss-rewriter' ); ?></label>
                    <select name="dsrw_selected_language" id="dsrw_selected_language">
                        <?php 
                        foreach ( $available_languages as $lang_code => $lang_name ) {
                            echo '<option value="' . esc_attr( $lang_code ) . '"' 
                                 . selected( $selected_language, $lang_code, false ) 
                                 . '>' . esc_html( $lang_name ) . '</option>';
                        } 
                        ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Idioma en el que la IA reescribirá los artículos.', 'autonews-rss-rewriter' ); ?></p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_publish_delay"><?php esc_html_e( 'Desfase de Publicación (minutos)', 'autonews-rss-rewriter' ); ?></label>
                    <input type="number" name="dsrw_publish_delay" id="dsrw_publish_delay" value="<?php echo esc_attr( get_option('dsrw_publish_delay', 0) ); ?>" min="0" max="4320" />
                    <p class="description"><?php esc_html_e( 'Minutos de diferencia entre publicaciones (0 para inmediato).', 'autonews-rss-rewriter' ); ?></p>
                </div>

                <!-- Campo oculto para mantener compatibilidad con dsrw_num_articulos como fallback -->
                <input type="hidden" name="dsrw_num_articulos" value="<?php echo esc_attr( get_option('dsrw_num_articulos', 5) ); ?>" />
                <!-- Campo oculto para mantener dsrw_cron_interval (ya no se usa activamente) -->
                <input type="hidden" name="dsrw_cron_interval" value="disabled" />

            </div>

            <!-- ===================== TAB API ===================== -->
            <div id="tab-api" class="tab-content">
                <div class="dsrw-field-group">
                    <label for="dsrw_openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'autonews-rss-rewriter' ); ?></label>
                    <input type="password" name="dsrw_openai_api_key" id="dsrw_openai_api_key" value="<?php echo esc_attr( get_option('dsrw_openai_api_key') ); ?>" size="50" placeholder="<?php esc_attr_e( 'Ingresa tu clave de API aquí', 'autonews-rss-rewriter' ); ?>" />
                    <p class="description"><?php esc_html_e( 'Tu clave de API proporcionada por OpenAI.', 'autonews-rss-rewriter' ); ?></p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_openai_api_base"><?php esc_html_e( 'OpenAI API Base (URL)', 'autonews-rss-rewriter' ); ?></label>
                    <input type="text" name="dsrw_openai_api_base" id="dsrw_openai_api_base" value="<?php echo esc_attr( get_option('dsrw_openai_api_base', 'https://api.openai.com') ); ?>" size="50" placeholder="https://api.openai.com" />
                    <p class="description"><?php esc_html_e( 'URL base de la API de OpenAI. Modifícala solo si es necesario.', 'autonews-rss-rewriter' ); ?></p>
                </div>

                <div class="dsrw-field-group">
                    <label><?php esc_html_e( 'Modelo de IA', 'autonews-rss-rewriter' ); ?></label>
                    
                    <div class="dsrw-radio-option">
                        <input type="radio" id="model-gpt-4-1-nano" name="dsrw_openai_model" value="gpt-4.1-nano" <?php checked( $current_model, 'gpt-4.1-nano' ); ?>>
                        <label for="model-gpt-4-1-nano"><strong>gpt-4.1-nano (Recomendado)</strong></label>
                        <p class="description"><?php esc_html_e( 'El mejor equilibrio. Sonido más natural en español y menos errores de formato.', 'autonews-rss-rewriter' ); ?></p>
                    </div>

                    <div class="dsrw-radio-option">
                        <input type="radio" id="model-gpt-5-nano" name="dsrw_openai_model" value="gpt-5-nano" <?php checked( $current_model, 'gpt-5-nano' ); ?>>
                        <label for="model-gpt-5-nano"><strong>gpt-5-nano</strong></label>
                        <p class="description"><?php esc_html_e( 'El más rápido y barato. Excelente si el coste es la prioridad absoluta.', 'autonews-rss-rewriter' ); ?></p>
                    </div>
                    
                    <div class="dsrw-radio-option">
                        <input type="radio" id="model-gpt-4o-mini" name="dsrw_openai_model" value="gpt-4o-mini" <?php checked( $current_model, 'gpt-4o-mini' ); ?>>
                        <label for="model-gpt-4o-mini"><strong>gpt-4o-mini</strong></label>
                        <p class="description"><?php esc_html_e( 'El modelo "mini" de la generación anterior. Sólido y fiable.', 'autonews-rss-rewriter' ); ?></p>
                    </div>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_openai_temperature"><?php esc_html_e( 'Temperatura (Creatividad)', 'autonews-rss-rewriter' ); ?></label>
                    <input type="number" name="dsrw_openai_temperature" id="dsrw_openai_temperature" value="<?php echo esc_attr( $current_temp ); ?>" min="0.1" max="2.0" step="0.1" />
                    <p class="description"><?php esc_html_e( 'Valor bajo (0.2) = más literal. Valor alto (1.0) = más creativo.', 'autonews-rss-rewriter' ); ?></p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_custom_prompt"><?php esc_html_e( 'Prompt Personalizado (Avanzado)', 'autonews-rss-rewriter' ); ?></label>
                    <textarea id="dsrw_custom_prompt" name="dsrw_custom_prompt" rows="15" cols="50" placeholder="<?php esc_attr_e( 'Déjalo vacío para usar los prompts por defecto del plugin (recomendado).', 'autonews-rss-rewriter' ); ?>"><?php echo esc_textarea( $custom_prompt ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Modifica bajo tu propio riesgo. Si el JSON devuelto no es correcto, el plugin fallará.', 'autonews-rss-rewriter' ); ?></p>
                </div>

            </div>

            <!-- ===================== TAB PUBLISHING ===================== -->
            <div id="tab-publishing" class="tab-content">

                <div class="dsrw-field-group">
                    <label for="dsrw_parent_category_id"><?php esc_html_e('Categoría padre (solo usar subcategorías)', 'autonews-rss-rewriter'); ?></label>
                    <?php
                    $saved_parent_id = (int) get_option('dsrw_parent_category_id', 0);
                    $parent_categories = get_categories(array( 'hide_empty' => false, 'parent' => 0 ));
                    ?>
                    <select name="dsrw_parent_category_id" id="dsrw_parent_category_id">
                        <option value="0" <?php selected($saved_parent_id, 0); ?>><?php esc_html_e('-- Desactivado (usar cualquier categoría) --', 'autonews-rss-rewriter'); ?></option>
                        <?php foreach ($parent_categories as $cat) : ?>
                            <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($saved_parent_id, (int)$cat->term_id); ?>><?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Si eliges una categoría padre, AutoNews solo publicará en sus subcategorías.', 'autonews-rss-rewriter'); ?></p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_allow_category_creation"><?php esc_html_e('Permitir crear categorías sugeridas por la IA', 'autonews-rss-rewriter'); ?></label>
                    <input type="checkbox" name="dsrw_allow_category_creation" id="dsrw_allow_category_creation" value="1" <?php checked(get_option('dsrw_allow_category_creation'), '1'); ?> />
                    <span><?php esc_html_e('Se creará la categoría sugerida si no existe.', 'autonews-rss-rewriter'); ?></span>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_enable_tags"><?php esc_html_e('Generar Etiquetas (Tags) Automáticamente', 'autonews-rss-rewriter'); ?></label>
                    <input type="checkbox" name="dsrw_enable_tags" id="dsrw_enable_tags" value="1" <?php checked(get_option('dsrw_enable_tags'), '1'); ?> />
                    <span><?php esc_html_e('La IA sugerirá y asignará etiquetas al post.', 'autonews-rss-rewriter'); ?></span>
                </div>
            </div>

            <!-- ===================== TAB IMAGES ===================== -->
            <div id="tab-images" class="tab-content">
                <div class="dsrw-field-group">
                    <label for="dsrw_enable_thumbnail_generator"><?php esc_html_e( 'Generar miniaturas automáticas con el título', 'autonews-rss-rewriter' ); ?></label>
                    <input type="checkbox" name="dsrw_enable_thumbnail_generator" id="dsrw_enable_thumbnail_generator" value="1" <?php checked( get_option('dsrw_enable_thumbnail_generator'), '1' ); ?> />
                    <span><?php esc_html_e( 'Activar generación automática si no hay imagen', 'autonews-rss-rewriter' ); ?></span>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_enable_image_extraction"><?php esc_html_e( 'Extraer imágenes del contenido', 'autonews-rss-rewriter' ); ?></label>
                    <input type="checkbox" name="dsrw_enable_image_extraction" id="dsrw_enable_image_extraction" value="1" <?php checked( get_option('dsrw_enable_image_extraction'), '1' ); ?> />
                    <span><?php esc_html_e( 'Activar extracción de imágenes de los feeds', 'autonews-rss-rewriter' ); ?></span>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw-upload-bg-button"><?php esc_html_e( 'Imagen de Fondo Personalizada (para miniaturas generadas)', 'autonews-rss-rewriter' ); ?></label>
                    <?php
                    $custom_bg_id = get_option('dsrw_thumbnail_custom_bg_id');
                    $bg_image_url = '';
                    if ( $custom_bg_id ) {
                        $bg_image_url = wp_get_attachment_image_url($custom_bg_id, 'medium');
                    }
                    ?>
                    <div class="dsrw-bg-preview-wrapper" style="margin: 10px 0; <?php echo $custom_bg_id ? '' : 'display: none;'; ?>">
                        <img id="dsrw-bg-preview" src="<?php echo esc_url($bg_image_url); ?>" style="max-width: 300px; height: auto; border: 1px solid #ddd;"/>
                    </div>
                    <input type="hidden" id="dsrw_thumbnail_custom_bg_id" name="dsrw_thumbnail_custom_bg_id" value="<?php echo esc_attr($custom_bg_id); ?>">
                    <button type="button" class="button" id="dsrw-upload-bg-button"><?php esc_html_e('Elegir Imagen', 'autonews-rss-rewriter'); ?></button>
                    <button type="button" class="button button-link-delete" id="dsrw-remove-bg-button" style="<?php echo $custom_bg_id ? '' : 'display: none;'; ?>"><?php esc_html_e('Quitar Imagen', 'autonews-rss-rewriter'); ?></button>
                    <p class="description"><?php esc_html_e( 'Se recomienda 1200x630px. Si no eliges ninguna, se usará la imagen por defecto.', 'autonews-rss-rewriter' ); ?></p>
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_thumbnail_bg_color"><?php esc_html_e( 'Color de Tinte de Fondo', 'autonews-rss-rewriter' ); ?></label>
                    <input type="color" name="dsrw_thumbnail_bg_color" id="dsrw_thumbnail_bg_color" value="<?php echo esc_attr( get_option('dsrw_thumbnail_bg_color', '#0073aa') ); ?>" />
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_thumbnail_text_color"><?php esc_html_e( 'Color del texto en miniatura', 'autonews-rss-rewriter' ); ?></label>
                    <input type="color" name="dsrw_thumbnail_text_color" id="dsrw_thumbnail_text_color" value="<?php echo esc_attr( get_option('dsrw_thumbnail_text_color', '#ffffff') ); ?>" />
                </div>

                <div class="dsrw-field-group">
                    <label for="dsrw_thumbnail_font_size"><?php esc_html_e( 'Tamaño de fuente (px)', 'autonews-rss-rewriter' ); ?></label>
                    <input type="number" name="dsrw_thumbnail_font_size" id="dsrw_thumbnail_font_size" value="<?php echo esc_attr( get_option('dsrw_thumbnail_font_size', 36) ); ?>" min="12" max="100" />
                </div>
            </div>

            <?php submit_button(); ?>
        </form>

        <!-- ===================== TAB TOOLS ===================== -->
        <div id="tab-tools" class="tab-content">
            
            <h2><?php esc_html_e( 'Administrar Tareas Cron', 'autonews-rss-rewriter' ); ?></h2>
            <p><?php esc_html_e( 'Programa o elimina las tareas cron de cada feed según la configuración de la pestaña "Feeds RSS".', 'autonews-rss-rewriter' ); ?></p>
            <form method="post">
                <?php
                wp_nonce_field( 'dsrw_schedule_cron_action', 'dsrw_schedule_cron_nonce' );
                submit_button( __( 'Activar Crons', 'autonews-rss-rewriter' ), 'primary', 'dsrw_schedule_cron', true, array( 'id' => 'dsrw_schedule_cron_button' ) );
                ?>
            </form>
            <form method="post" style="margin-top: 10px;">
                <?php
                wp_nonce_field( 'dsrw_unschedule_cron_action', 'dsrw_unschedule_cron_nonce' );
                submit_button( __( 'Desactivar Todos los Crons', 'autonews-rss-rewriter' ), 'secondary', 'dsrw_unschedule_cron', true, array( 'id' => 'dsrw_unschedule_cron_button' ) );
                ?>
            </form>

            <hr />
            
            <h2><?php esc_html_e( 'Ejecutar Procesamiento Manualmente', 'autonews-rss-rewriter' ); ?></h2>
            <p><?php esc_html_e( 'Procesa inmediatamente todos los feeds activos (ignora intervalos).', 'autonews-rss-rewriter' ); ?></p>
            
            <p>
                <button type="button" class="button button-primary" id="autonews-manual-run-button">
                    <?php esc_html_e( 'Ejecutar Manualmente', 'autonews-rss-rewriter' ); ?>
                </button>
                <span id="dsrw_manual_spinner" style="display:none; margin-left: 10px; vertical-align: middle;">⏳ Procesando...</span>
            </p>
            
            <div id="autonews-manual-log" style="font-family: monospace; background: #f6f8fa; border: 1px solid #ccc; padding: 10px; margin-top: 10px; display: none; max-height: 400px; overflow-y: auto; box-shadow: inset 0 0 5px rgba(0,0,0,0.1);"></div>

        </div>
    </div>
    <?php
    // --- MANEJO DE FORMULARIOS CRON ---
    
    if ( isset( $_POST['dsrw_schedule_cron'] ) ) {
        if ( ! isset( $_POST['dsrw_schedule_cron_nonce'] ) || ! wp_verify_nonce( $_POST['dsrw_schedule_cron_nonce'], 'dsrw_schedule_cron_action' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Permiso denegado.', 'autonews-rss-rewriter' ) . '</p></div>';
        } else {
            // Limpiar cron global antiguo si existiera
            wp_clear_scheduled_hook( 'dsrw_cron_hook' );
            
            // Programar crons individuales de cada feed
            dsrw_schedule_feed_crons();
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Crons de feeds activados correctamente.', 'autonews-rss-rewriter' ) . '</p></div>';
        }
    }

    if ( isset( $_POST['dsrw_unschedule_cron'] ) ) {
        if ( ! isset( $_POST['dsrw_unschedule_cron_nonce'] ) || ! wp_verify_nonce( $_POST['dsrw_unschedule_cron_nonce'], 'dsrw_unschedule_cron_action' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Permiso denegado.', 'autonews-rss-rewriter' ) . '</p></div>';
        } else {
            wp_clear_scheduled_hook( 'dsrw_cron_hook' );
            $rss_urls_unsched = array_filter( array_map( 'trim', explode( "\n", get_option( 'dsrw_rss_urls', '' ) ) ) );
            foreach ( $rss_urls_unsched as $unsched_index => $unsched_url ) {
                wp_clear_scheduled_hook( 'dsrw_feed_cron_hook_' . $unsched_index );
            }
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Todos los crons han sido desactivados.', 'autonews-rss-rewriter' ) . '</p></div>';
        }
    }
}


/**
 * Devuelve un texto legible "hace X minutos/horas" a partir de una fecha.
 */
function dsrw_time_ago( $datetime ) {
    if ( empty( $datetime ) ) return '';
    $timestamp = is_numeric( $datetime ) ? $datetime : strtotime( $datetime );
    $diff = current_time( 'timestamp' ) - $timestamp;
    
    if ( $diff < 60 ) return __( 'Hace un momento', 'autonews-rss-rewriter' );
    if ( $diff < 3600 ) return sprintf( __( 'Hace %d min', 'autonews-rss-rewriter' ), floor( $diff / 60 ) );
    if ( $diff < 86400 ) return sprintf( __( 'Hace %d h', 'autonews-rss-rewriter' ), floor( $diff / 3600 ) );
    return sprintf( __( 'Hace %d días', 'autonews-rss-rewriter' ), floor( $diff / 86400 ) );
}

/**
 * Devuelve un texto legible "en X minutos/horas" a partir de un timestamp futuro.
 */
function dsrw_time_until( $timestamp ) {
    if ( empty( $timestamp ) ) return '';
    $diff = $timestamp - time();
    if ( $diff < 0 ) return __( 'Ahora', 'autonews-rss-rewriter' );
    if ( $diff < 60 ) return __( 'En menos de 1 min', 'autonews-rss-rewriter' );
    if ( $diff < 3600 ) return sprintf( __( 'En %d min', 'autonews-rss-rewriter' ), floor( $diff / 60 ) );
    if ( $diff < 86400 ) return sprintf( __( 'En %d h', 'autonews-rss-rewriter' ), floor( $diff / 3600 ) );
    return sprintf( __( 'En %d días', 'autonews-rss-rewriter' ), floor( $diff / 86400 ) );
}


// --- Callback de AJAX ---
add_action('wp_ajax_autonews_manual_run', 'autonews_manual_run_callback');

function autonews_manual_run_callback() {
    
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'dsrw_run_feeds_nonce' ) ) {
        wp_send_json_error( [ 'logs' => [ '❌ Error de seguridad (Nonce inválido). Intenta recargar la página.' ] ], 403 );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'logs' => [ '❌ Error: No tienes permisos.' ] ], 403 );
    }

    $logs = [];
    $logs[] = "▶️ " . esc_html__( 'Iniciando procesamiento manual...', 'autonews-rss-rewriter' );

    try {
        dsrw_process_all_feeds_manual( $logs );
    } catch (Exception $e) {
        $logs[] = "❌ " . esc_html__( 'Error fatal durante la ejecución: ', 'autonews-rss-rewriter' ) . $e->getMessage();
        dsrw_write_log( "[AutoNews] Error fatal en ejecución AJAX: " . $e->getMessage() );
        wp_send_json_error( [ 'logs' => $logs ] );
    }
    
    $logs[] = "✅ " . esc_html__( 'Proceso manual completado.', 'autonews-rss-rewriter' );
    wp_send_json_success( [ 'logs' => $logs ] );
}