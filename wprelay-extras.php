<?php
/**
 * Plugin Name:       WPRelay Extras
 * Description:       Funciones adicionales para WPRelay Pro: pagos manuales y gestión de comisiones (crear, editar, anular).
 * Version:           1.0.2
 * Requires PHP:      7.3
 * Author:            Custom
 * Text Domain:       wprelay-extras
 */

defined('ABSPATH') or exit;

define('WPRELAY_EXTRAS_PATH', plugin_dir_path(__FILE__));
define('WPRELAY_EXTRAS_URL', plugin_dir_url(__FILE__));
define('WPRELAY_EXTRAS_VERSION', '1.0.2');
define('WPRELAY_EXTRAS_SLUG', 'wprelay-extras');

// Auto-update desde GitHub
require_once WPRELAY_EXTRAS_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

PucFactory::buildUpdateChecker(
    'https://github.com/ideaalab/wprelay-extras/',
    __FILE__,
    'wprelay-extras'
);

// Verificar que el plugin base esté activo y cargado
add_action('plugins_loaded', function () {
    if (!class_exists('RelayWp\\Affiliate\\Core\\Models\\CommissionEarning')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>WPRelay Extras:</strong> requiere que el plugin <em>Relay Affiliate</em> esté instalado y activo.</p></div>';
        });
        return;
    }

    require_once WPRELAY_EXTRAS_PATH . 'includes/class-helper.php';
    require_once WPRELAY_EXTRAS_PATH . 'includes/class-commissions.php';
    require_once WPRELAY_EXTRAS_PATH . 'includes/class-payouts.php';
    require_once WPRELAY_EXTRAS_PATH . 'includes/class-admin.php';

    \WPRelayExtras\Admin::init();
}, 20);
