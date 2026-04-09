<?php
/**
 * DHC_Event_Logger — Centralized event logging to the Hub
 *
 * Provides a single static method that any module can call to report
 * events to the Hub's /api/plugin/event endpoint. Also logs locally
 * to the dhc_activity_log option for the WP admin Activity Log tab.
 *
 * @package Dsquared_Hub_Connector
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DHC_Event_Logger {

    /**
     * Log an event to the Hub and locally
     *
     * @param string $event  Event type identifier (e.g., 'schema_injected', 'content_decay_scan').
     * @param string $module Module slug (e.g., 'ai_discovery', 'content_decay').
     * @param array  $data   Additional event data.
     * @param string $local_message Human-readable message for local activity log.
     */
    public static function log( $event, $module = 'general', $data = array(), $local_message = '' ) {
        // 1. Send to Hub
        self::send_to_hub( $event, $module, $data );

        // 2. Log locally
        if ( ! empty( $local_message ) ) {
            self::log_local( $event, $module, $local_message );
        }
    }

    /**
     * Send event to Hub's /api/plugin/event endpoint
     *
     * @param string $event  Event type.
     * @param string $module Module slug.
     * @param array  $data   Event data.
     */
    private static function send_to_hub( $event, $module, $data ) {
        $api_key = get_option( 'dhc_api_key', '' );
        if ( empty( $api_key ) ) {
            return;
        }

        $hub_url = DHC_Heartbeat::get_hub_url();

        wp_remote_post( $hub_url . '/api/plugin/event', array(
            'body'    => wp_json_encode( array(
                'event'  => $event,
                'site'   => home_url( '/' ),
                'module' => $module,
                'data'   => $data,
            ) ),
            'headers' => array(
                'Content-Type'  => 'application/json',
                'X-DHC-API-Key' => $api_key,
            ),
            'timeout'  => 15,
            'blocking' => false,
        ) );
    }

    /**
     * Log event to local WordPress activity log
     *
     * @param string $event   Event type.
     * @param string $module  Module slug.
     * @param string $message Human-readable message.
     */
    private static function log_local( $event, $module, $message ) {
        $log = get_option( 'dhc_activity_log', array() );

        array_unshift( $log, array(
            'action'  => $event,
            'module'  => $module,
            'message' => $message,
            'time'    => current_time( 'mysql' ),
        ) );

        // Keep last 200 entries
        $log = array_slice( $log, 0, 200 );
        update_option( 'dhc_activity_log', $log );
    }

    /**
     * Convenience: Log an AI Discovery event
     *
     * @param string $event   Event type (e.g., 'schema_injected', 'llms_updated', 'profile_synced').
     * @param array  $data    Event data.
     * @param string $message Local log message.
     */
    public static function ai_discovery( $event, $data = array(), $message = '' ) {
        self::log( $event, 'ai_discovery', $data, $message );
    }

    /**
     * Convenience: Log a Content Decay event
     *
     * @param string $event   Event type.
     * @param array  $data    Event data.
     * @param string $message Local log message.
     */
    public static function content_decay( $event, $data = array(), $message = '' ) {
        self::log( $event, 'content_decay', $data, $message );
    }

    /**
     * Convenience: Log a Blog Writer / Auto-Post event
     *
     * @param string $event   Event type.
     * @param array  $data    Event data.
     * @param string $message Local log message.
     */
    public static function auto_post( $event, $data = array(), $message = '' ) {
        self::log( $event, 'auto_post', $data, $message );
    }

    /**
     * Convenience: Log a Meta Sync event
     *
     * @param string $event   Event type.
     * @param array  $data    Event data.
     * @param string $message Local log message.
     */
    public static function seo_meta( $event, $data = array(), $message = '' ) {
        self::log( $event, 'seo_meta_sync', $data, $message );
    }

    /**
     * Convenience: Log a Schema event
     *
     * @param string $event   Event type.
     * @param array  $data    Event data.
     * @param string $message Local log message.
     */
    public static function schema( $event, $data = array(), $message = '' ) {
        self::log( $event, 'schema_injector', $data, $message );
    }

    /**
     * Convenience: Log a Form Capture event
     *
     * @param string $event   Event type.
     * @param array  $data    Event data.
     * @param string $message Local log message.
     */
    public static function form_capture( $event, $data = array(), $message = '' ) {
        self::log( $event, 'form_capture', $data, $message );
    }

    /**
     * Convenience: Log a Site Health event
     *
     * @param string $event   Event type.
     * @param array  $data    Event data.
     * @param string $message Local log message.
     */
    public static function site_health( $event, $data = array(), $message = '' ) {
        self::log( $event, 'site_health', $data, $message );
    }
}
