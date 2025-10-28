<?php
/**
 * Archivo: admin-settings.php
 * Función: Registro de ajustes, creación del menú y página de administración.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acceso directo.
}

/**
 * Registrar configuraciones y ajustes del plugin.
 */
function dsrw_register_settings() {
    register_setting( 'dsrw_options_group', 'dsrw_rss_urls', 'dsrw_validate_rss_urls' );
    register_setting( 'dsrw_options_group', 'dsrw_feed_categories' );
    register_setting( 'dsrw_options_group', 'dsrw_openai_api_key', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_openai_api_base', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_num_articulos', 'absint' );
    register_setting( 'dsrw_options_group', 'dsrw_publish_delay', 'absint' );
    register_setting( 'dsrw_options_group', 'dsrw_cron_interval', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_default_author', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_selected_language', 'sanitize_text_field' );
    register_setting( 'dsrw_options_group', 'dsrw_enable_thumbnail_generator', 'sanitize_text_field' );
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
    // Ajusta este valor al que realmente tiene tu página de ajustes
    if ($hook !== 'toplevel_page_dsrw-settings') {
        return;
    }

     // Encolar la hoja de estilo
    wp_enqueue_style(
        'dsrw-admin-css',
        plugin_dir_url(__FILE__) . '../assets/dsrw-admin.css',
        array(),
        '1.0',
        'all'
    );

    // Encolar el script
    wp_enqueue_script(
        'dsrw-admin-js',
        plugin_dir_url(__FILE__) . '../assets/dsrw-admin.js',
        array('jquery'),
        '1.0',
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
    ?>

    <div class="wrap dsrw-settings-page">
        <h1><?php esc_html_e( 'AutoNews - Configuración', 'autonews-rss-rewriter' ); ?></h1>
        <h2><?php esc_html_e( 'Resumen', 'autonews-rss-rewriter' ); ?></h2>
        <ul class="dsrw-summary">
            <li><?php esc_html_e( 'Total de Feeds Configurados: ', 'autonews-rss-rewriter' ); ?><?php echo esc_html( $total_feeds ); ?></li>
            <li><?php esc_html_e( 'Total de Posts Publicados: ', 'autonews-rss-rewriter' ); ?><?php echo esc_html( $total_posts ); ?></li>
        </ul>

        <form method="post" action="options.php" class="dsrw-settings-form">
            <?php
            // Protege y registra nuestros campos
            settings_fields( 'dsrw_options_group' );
            do_settings_sections( 'dsrw_options_group' );
            ?>

            <!-- Feeds RSS -->
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

            <!-- Mapeo de Feeds a Categorías -->
            <div class="dsrw-field-group">
                <label><?php esc_html_e( 'Mapeo de Feeds a Categorías', 'autonews-rss-rewriter' ); ?></label>
                <p><?php esc_html_e( 'Asigna una categoría de WordPress a cada feed RSS. Si no existe la categoría, se creará automáticamente.', 'autonews-rss-rewriter' ); ?></p>
                <?php
                $rss_urls_raw = get_option( 'dsrw_rss_urls', '' );
                $feed_categories = get_option( 'dsrw_feed_categories', array() );
                $rss_urls = array_filter( array_map( 'trim', explode( "\n", $rss_urls_raw ) ) );
                foreach ( $rss_urls as $index => $url ) :
                    ?>
                    <div class="dsrw-feed-mapping">
                        <label for="dsrw_feed_categories[<?php echo esc_attr( $index ); ?>]"><?php echo esc_html( $url ); ?></label>
                        <?php
                        $categories = get_categories( array( 'hide_empty' => false ) );
                        ?>
                        <select name="dsrw_feed_categories[<?php echo esc_attr( $index ); ?>]" id="dsrw_feed_categories[<?php echo esc_attr( $index ); ?>]">
                            <option value=""><?php esc_html_e( '-- Categoría Automática --', 'autonews-rss-rewriter' ); ?></option>
                            <?php foreach ( $categories as $category ) : ?>
                                <option 
                                    value="<?php echo esc_attr( $category->term_id ); ?>" 
                                    <?php selected( $feed_categories[ $index ] ?? '', $category->term_id, false ); ?>
                                >
                                    <?php echo esc_html( $category->name ); ?>
                                </option>
                            <?php endforeach; ?>
                            <option 
                                value="none" 
                                <?php selected( $feed_categories[ $index ] ?? '', 'none' ); ?>
                            >
                                <?php esc_html_e( '-- Ninguna --', 'autonews-rss-rewriter' ); ?>
                            </option>
                        </select>
                    </div>
                <?php endforeach; ?>
                <p class="description">
                    <?php esc_html_e( 'Selecciona la categoría correspondiente para cada feed RSS. "Categoría Automática" asignará una categoría basada en la respuesta de la IA si no se elige ninguna.', 'autonews-rss-rewriter' ); ?>
                </p>
            </div>

            <!-- Autor Predeterminado -->
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

            <!-- Idioma de Respuesta -->
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

            <!-- OpenAI API Key -->
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

            <!-- OpenAI API Base (URL) -->
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

            <!-- Número de artículos a procesar por feed -->
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

            <!-- Desfase de Publicación (minutos) -->
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

            <!-- Intervalo de Cron (minutos) -->
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

            <!-- Generar miniaturas automáticas con el título -->
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

            <!-- Extraer imágenes del contenido -->
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

            <!-- Color de fondo para miniatura -->
            <div class="dsrw-field-group">
                <label for="dsrw_thumbnail_bg_color">
                    <?php esc_html_e( 'Color de fondo para miniatura', 'autonews-rss-rewriter' ); ?>
                </label>
                <input 
                    type="color" 
                    name="dsrw_thumbnail_bg_color" 
                    id="dsrw_thumbnail_bg_color"
                    value="<?php echo esc_attr( get_option('dsrw_thumbnail_bg_color', '#0073aa') ); ?>"
                />
            </div>

            <!-- Color del texto en miniatura -->
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

            <!-- Tamaño de fuente en miniatura (px) -->
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

            <!-- Permitir creación automática de categorías sugeridas por la IA -->
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

            <?php submit_button(); ?>
        </form>

        <hr />

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
        
        <!-- Área para ejecutar manualmente el procesamiento de feeds -->
<h2><?php esc_html_e( 'Ejecutar Procesamiento Manualmente', 'autonews-rss-rewriter' ); ?></h2>
<p><?php esc_html_e( 'Haz clic para procesar inmediatamente todos los feeds RSS configurados.', 'autonews-rss-rewriter' ); ?></p>
<form method="post">
    <?php
    wp_nonce_field( 'dsrw_manual_process_action', 'dsrw_manual_process_nonce' );
    submit_button( __( 'Ejecutar Manualmente', 'autonews-rss-rewriter' ), 'primary', 'dsrw_manual_process', true, array( 'id' => 'dsrw_manual_process_button' ) );
    ?>
    <span id="dsrw_manual_spinner" style="display:none; margin-left: 10px;">⏳ Procesando...</span>
    <div id="autonews-manual-log" style="font-family: monospace; background: #f6f8fa; border: 1px solid #ccc; padding: 10px; margin-top: 10px; display: none;"></div>

</form>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const runButton = document.getElementById("dsrw_manual_process_button");
  const logBox = document.getElementById("autonews-manual-log");

  if (runButton && logBox) {
    runButton.addEventListener("click", function(e) {
      e.preventDefault();
      logBox.innerHTML = "⏳ Ejecutando feed manualmente...<br>";
      logBox.style.display = "block";

      fetch(ajaxurl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded"
        },
        body: new URLSearchParams({
          action: "autonews_manual_run"
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.data.logs) {
          data.data.logs.forEach(line => {
            logBox.innerHTML += line + "<br>";
          });
        } else {
          logBox.innerHTML += "❌ Error inesperado al ejecutar.";
        }
      });
    });
  }
});
</script>

<!-- Aquí el div extra para mostrar mensajes de estado -->
<div id="dsrw_manual_status" style="margin-top:10px;"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('dsrw_manual_process_button');
        const spinner = document.getElementById('dsrw_manual_spinner');
        if (btn && spinner) {
            btn.addEventListener('click', function () {
                spinner.style.display = 'inline-block';
            });
        }
    });
    </script>
    <?php

    // Sección de acciones para cron
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

    if ( isset( $_POST['dsrw_manual_process'] ) ) {
        if ( ! isset( $_POST['dsrw_manual_process_nonce'] ) || ! wp_verify_nonce( $_POST['dsrw_manual_process_nonce'], 'dsrw_manual_process_action' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Permiso denegado para ejecutar el procesamiento manualmente.', 'autonews-rss-rewriter' ) . '</p></div>';
        } else {
            dsrw_process_all_feeds();
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Procesamiento de feeds ejecutado manualmente exitosamente.', 'autonews-rss-rewriter' ) . '</p></div>';
        }
    }
}

add_action('wp_ajax_autonews_manual_run', 'autonews_manual_run_callback');

function autonews_manual_run_callback() {
    // Simulación de pasos reales
    $logs = [
        "Puedes salir de esta página si lo deseas."
    ];
    wp_send_json_success([ 'logs' => $logs ]);
}
