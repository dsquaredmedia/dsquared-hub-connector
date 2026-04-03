<?php
/**
 * DHC_API_Key — Handles API key validation and subscription status
 *
 * @package Dsquared_Hub_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DHC_API_Key {

    /**
     * Transient key for caching subscription data
     */
    const CACHE_KEY = 'dhc_subscription_cache';

    /**
     * Cache duration in seconds (12 hours)
     */
    const CACHE_DURATION = 43200;

    /**
     * Module tier mapping
     */
    const TIER_MODULES = array(
        'starter' => array( 'auto_post' ),
        'growth'  => array( 'auto_post', 'schema', 'seo_meta' ),
        'pro'     => array( 'auto_post', 'schema', 'seo_meta', 'site_health' ),
    );

    /**
     * Validate the API key against the Hub
     *
     * @param string $api_key The API key to validate.
     * @param bool   $force   Force a fresh validation (bypass cache).
     * @return array Subscription data or error.
     */
    public static function validate( $api_key = '', $force = false ) {
        if ( empty( $api_key ) ) {
            $api_key = get_option( 'dhc_api_key', '' );
        }

        if ( empty( $api_key ) ) {
            return array(
                'valid'   => false,
                'message' => 'No API key configured.',
            );
        }

        // Check cache first (unless forced)
        if ( ! $force ) {
            $cached = get_transient( self::CACHE_KEY );
            if ( false !== $cached && is_array( $cached ) ) {
                return $cached;
            }
        }

        // Call Hub API to validate
        $response = wp_remote_get(
            DHC_HUB_API_BASE . '/plugin/validate-key',
            array(
                'headers' => array(
                    'X-DHC-API-Key' => $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            // Network error — use cached data if available, otherwise return error
            $cached = get_option( 'dhc_subscription', array() );
            if ( ! empty( $cached['status'] ) && 'active' === $cached['status'] ) {
                // Trust the last known good state during network issues
                return array(
                    'valid'        => true,
                    'tier'         => $cached['tier'],
                    'expires'      => $cached['expires'],
                    'modules'      => self::get_tier_modules( $cached['tier'] ),
                    'cached'       => true,
                    'network_error' => true,
                );
            }
            return array(
                'valid'   => false,
                'message' => 'Unable to reach Dsquared Hub. Please check your connection.',
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || empty( $body ) ) {
            return array(
                'valid'   => false,
                'message' => isset( $body['message'] ) ? $body['message'] : 'Invalid API key.',
            );
        }

        // Build subscription data
        $subscription = array(
            'valid'   => true,
            'tier'    => sanitize_text_field( $body['tier'] ?? 'starter' ),
            'expires' => sanitize_text_field( $body['expires'] ?? '' ),
            'modules' => self::get_tier_modules( $body['tier'] ?? 'starter' ),
            'site_id' => sanitize_text_field( $body['site_id'] ?? '' ),
        );

        // Check if subscription has expired
        if ( ! empty( $subscription['expires'] ) ) {
            $expiry = strtotime( $subscription['expires'] );
            if ( $expiry && $expiry < time() ) {
                $subscription['valid'] = false;
                $subscription['message'] = 'Your Dsquared Hub subscription has expired. Features are currently disabled but your website is unaffected. Renew your subscription to restore full functionality.';
                $subscription['expired'] = true;
            }
        }

        // Cache the result
        set_transient( self::CACHE_KEY, $subscription, self::CACHE_DURATION );

        // Also persist to options for offline fallback
        update_option( 'dhc_subscription', array(
            'status'  => $subscription['valid'] ? 'active' : 'inactive',
            'tier'    => $subscription['tier'],
            'expires' => $subscription['expires'],
        ) );

        return $subscription;
    }

    /**
     * Check if a specific module is available for the current subscription
     *
     * @param string $module Module slug.
     * @return bool
     */
    public static function is_module_available( $module ) {
        $subscription = self::validate();

        if ( ! $subscription['valid'] ) {
            return false;
        }

        // Check if module is enabled in plugin settings
        $enabled_modules = get_option( 'dhc_modules', array() );
        if ( empty( $enabled_modules[ $module ] ) ) {
            return false;
        }

        // Check if module is available in current tier
        return in_array( $module, $subscription['modules'] ?? array(), true );
    }

    /**
     * Get modules available for a given tier
     *
     * @param string $tier Subscription tier.
     * @return array
     */
    public static function get_tier_modules( $tier ) {
        $tier = strtolower( $tier );
        return self::TIER_MODULES[ $tier ] ?? array( 'auto_post' );
    }

    /**
     * Get human-readable tier name
     *
     * @param string $tier Tier slug.
     * @return string
     */
    public static function get_tier_label( $tier ) {
        $labels = array(
            'starter' => 'Starter',
            'growth'  => 'Growth',
            'pro'     => 'Professional',
        );
        return $labels[ strtolower( $tier ) ] ?? ucfirst( $tier );
    }

    /**
     * Clear cached subscription data
     */
    public static function clear_cache() {
        delete_transient( self::CACHE_KEY );
    }

    /**
     * Authenticate an incoming REST request from the Hub
     *
     * @param WP_REST_Request $request The incoming request.
     * @return bool|WP_Error
     */
    public static function authenticate_request( $request ) {
        $api_key = $request->get_header( 'X-DHC-API-Key' );

        if ( empty( $api_key ) ) {
            return new WP_Error(
                'dhc_missing_key',
                'API key is required.',
                array( 'status' => 401 )
            );
        }

        $stored_key = get_option( 'dhc_api_key', '' );

        if ( empty( $stored_key ) || ! hash_equals( $stored_key, $api_key ) ) {
            return new WP_Error(
                'dhc_invalid_key',
                'Invalid API key.',
                array( 'status' => 403 )
            );
        }

        // Verify subscription is active
        $subscription = self::validate( $stored_key );
        if ( ! $subscription['valid'] ) {
            return new WP_Error(
                'dhc_subscription_inactive',
                $subscription['message'] ?? 'Subscription is not active.',
                array( 'status' => 403 )
            );
        }

        return true;
    }
}
