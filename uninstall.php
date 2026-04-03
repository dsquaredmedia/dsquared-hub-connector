<?php
/**
 * Dsquared Hub Connector — Uninstall
 *
 * Cleans up all plugin data when the plugin is deleted (not just deactivated).
 * Deactivation does NOT remove data — only full deletion does.
 * This ensures the website is never affected by simply disabling the plugin.
 *
 * @package Dsquared_Hub_Connector
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options
delete_option( 'dhc_api_key' );
delete_option( 'dhc_modules' );
delete_option( 'dhc_subscription' );
delete_option( 'dhc_activity_log' );
delete_option( 'dhc_cwv_metrics' );
delete_option( 'dhc_global_schemas' );
delete_option( 'dhc_default_author' );

// Remove transients
delete_transient( 'dhc_subscription_cache' );

// Remove per-post meta (schema and SEO meta)
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_dhc_schema_markup', '_dhc_seo_meta', '_dhc_source', '_dhc_created_at')" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_dhc\_%'" );
