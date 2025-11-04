<?php
/**
 * Plugin Name: AutoNews - AI News Rewriter
 * Description: Reescribe artículos de múltiples feeds RSS y los publica en WordPress usando la API de OpenAI.
 * Version: 1.1.34
 * Author: <a href="https://albertomurillo.pro">Alberto Murillo</a>
 * Text Domain: autonews-rss-rewriter
 */

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita el acceso directo.
}

// Cargar el text domain para las traducciones.
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-settings.php';

// Cargar módulos adicionales
require_once plugin_dir_path( __FILE__ ) . 'includes/cron.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/feeds.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/image-handling.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/logs.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/error-handling.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/prompts.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/utilities.php';

// Registrar hooks de activación y desactivación
register_activation_hook( __FILE__, 'dsrw_activate_plugin' );
register_deactivation_hook( __FILE__, 'dsrw_deactivate_plugin' );

// Registrar el hook del cron para el procesamiento de feeds
add_action( 'dsrw_cron_hook', 'dsrw_process_all_feeds' );

// Carga inicial de idiomas
// add_action( 'plugins_loaded', 'dsrw_load_textdomain' );