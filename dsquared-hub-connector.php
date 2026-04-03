<?php
/**
 * Plugin Name:       Dsquared Hub Connector
 * Plugin URI:        https://hub.dsquaredmedia.net
 * Description:       Connect your WordPress site to Dsquared Media Hub — auto-post drafts, inject schema markup, sync SEO meta, and monitor site health. All features are subscription-gated and will gracefully disable if your subscription lapses without affecting your website.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Dsquared Media
 * Author URI:        https://dsquaredmedia.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dsquared-hub-connector
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'DHC_VERSION', '1.0.0' );
define( 'DHC_PLUGIN_FILE', __FILE__ );
define( 'DHC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DHC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DHC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'DHC_HUB_API_BASE', 'https://hub.dsquaredmedia.net/api' );

// Autoload includes
require_once DHC_PLUGIN_DIR . 'includes/class-dhc-api-key.php';
require_once DHC_PLUGIN_DIR . 'includes/class-dhc-rest.php';
require_once DHC_PLUGIN_DIR . 'includes/class-dhc-admin.php';
require_once DHC_PLUGIN_DIR . 'includes/class-dhc-core.php';

// Module files
require_once DHC_PLUGIN_DIR . 'includes/modules/class-dhc-auto-post.php';
require_once DHC_PLUGIN_DIR . 'includes/modules/class-dhc-schema.php';
require_once DHC_PLUGIN_DIR . 'includes/modules/class-dhc-seo-meta.php';
require_once DHC_PLUGIN_DIR . 'includes/modules/class-dhc-site-health.php';

/**
 * Activation hook
 */
function dhc_activate() {
    // Set default options
    if ( false === get_option( 'dhc_api_key' ) ) {
        add_option( 'dhc_api_key', '' );
    }
    if ( false === get_option( 'dhc_modules' ) ) {
        add_option( 'dhc_modules', array(
            'auto_post'    => true,
            'schema'       => true,
            'seo_meta'     => true,
            'site_health'  => true,
        ) );
    }
    if ( false === get_option( 'dhc_subscription' ) ) {
        add_option( 'dhc_subscription', array(
            'status'  => 'inactive',
            'tier'    => '',
            'expires' => '',
        ) );
    }

    // Flush rewrite rules for REST endpoints
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dhc_activate' );

/**
 * Deactivation hook
 */
function dhc_deactivate() {
    // Clean up transients
    delete_transient( 'dhc_subscription_cache' );
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'dhc_deactivate' );

/**
 * Initialize the plugin
 */
function dhc_init() {
    DHC_Core::get_instance();
}
add_action( 'plugins_loaded', 'dhc_init' );

/**
 * Add settings link on plugin page
 */
function dhc_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=dsquared-hub' ) . '">' . __( 'Settings', 'dsquared-hub-connector' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . DHC_PLUGIN_BASENAME, 'dhc_plugin_action_links' );
