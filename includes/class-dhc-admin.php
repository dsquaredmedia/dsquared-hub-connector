<?php
/**
 * DHC_Admin — Admin settings page styled to match the Hub backend
 *
 * @package Dsquared_Hub_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DHC_Admin {

    /**
     * Initialize admin hooks
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_dhc_save_settings', array( __CLASS__, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_dhc_validate_key', array( __CLASS__, 'ajax_validate_key' ) );
        add_action( 'wp_ajax_dhc_clear_activity_log', array( __CLASS__, 'ajax_clear_log' ) );
    }

    /**
     * Add the admin menu page
     */
    public static function add_menu_page() {
        add_menu_page(
            'Dsquared Hub',
            'Dsquared Hub',
            'manage_options',
            'dsquared-hub',
            array( __CLASS__, 'render_page' ),
            'data:image/svg+xml;base64,' . base64_encode( self::get_menu_icon() ),
            30
        );
    }

    /**
     * Enqueue admin CSS and JS
     */
    public static function enqueue_assets( $hook ) {
        if ( 'toplevel_page_dsquared-hub' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'dhc-admin',
            DHC_PLUGIN_URL . 'admin/css/dhc-admin.css',
            array(),
            DHC_VERSION
        );

        // Load Plus Jakarta Sans
        wp_enqueue_style(
            'dhc-google-fonts',
            'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
            array(),
            null
        );

        wp_enqueue_script(
            'dhc-admin',
            DHC_PLUGIN_URL . 'admin/js/dhc-admin.js',
            array( 'jquery' ),
            DHC_VERSION,
            true
        );

        wp_localize_script( 'dhc-admin', 'dhcAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'dhc_admin_nonce' ),
            'restUrl' => rest_url( 'dsquared-hub/v1/' ),
        ) );
    }

    /**
     * Render the admin settings page
     */
    public static function render_page() {
        $api_key      = get_option( 'dhc_api_key', '' );
        $modules      = get_option( 'dhc_modules', array() );
        $subscription = DHC_API_Key::validate();
        $activity_log = get_option( 'dhc_activity_log', array() );
        $cwv_metrics  = DHC_Site_Health::get_aggregated_metrics( 30 );
        $seo_plugin   = DHC_SEO_Meta::detect_seo_plugin();

        // Reverse log so newest is first
        $activity_log = array_reverse( $activity_log );
        ?>
        <div class="dhc-wrap">
            <!-- Header -->
            <div class="dhc-header">
                <div class="dhc-header-left">
                    <div class="dhc-logo">
                        <svg width="28" height="28" viewBox="0 0 40 40" fill="none">
                            <rect width="40" height="40" rx="8" fill="#5661FF"/>
                            <path d="M10 12h8c5.5 0 10 4.5 10 10s-4.5 10-10 10h-8V12z" fill="none" stroke="#fff" stroke-width="2.5"/>
                            <path d="M22 12h8v8" fill="none" stroke="#E8466D" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="dhc-title">Dsquared Hub Connector</h1>
                        <span class="dhc-version">v<?php echo esc_html( DHC_VERSION ); ?></span>
                    </div>
                </div>
                <div class="dhc-header-right">
                    <?php if ( ! empty( $subscription['valid'] ) ) : ?>
                        <span class="dhc-badge dhc-badge-success">
                            <span class="dhc-badge-dot"></span>
                            Connected — <?php echo esc_html( DHC_API_Key::get_tier_label( $subscription['tier'] ?? '' ) ); ?>
                        </span>
                    <?php elseif ( ! empty( $subscription['expired'] ) ) : ?>
                        <span class="dhc-badge dhc-badge-warning">
                            <span class="dhc-badge-dot"></span>
                            Subscription Expired
                        </span>
                    <?php else : ?>
                        <span class="dhc-badge dhc-badge-inactive">
                            <span class="dhc-badge-dot"></span>
                            Not Connected
                        </span>
                    <?php endif; ?>
                    <a href="https://hub.dsquaredmedia.net" target="_blank" class="dhc-btn dhc-btn-outline">Open Hub</a>
                </div>
            </div>

            <?php if ( ! empty( $subscription['expired'] ) ) : ?>
            <div class="dhc-notice dhc-notice-warning">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div>
                    <strong>Your subscription has expired.</strong> All Hub features are currently disabled, but your website is completely unaffected.
                    Keeping an active subscription is suggested to maintain full functionality.
                    <a href="https://hub.dsquaredmedia.net/dashboard.html#account" target="_blank">Renew your subscription &rarr;</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="dhc-tabs">
                <button class="dhc-tab active" data-tab="connection">Connection</button>
                <button class="dhc-tab" data-tab="modules">Modules</button>
                <button class="dhc-tab" data-tab="health">Site Health</button>
                <button class="dhc-tab" data-tab="activity">Activity Log</button>
            </div>

            <!-- Connection Tab -->
            <div class="dhc-tab-content active" id="tab-connection">
                <div class="dhc-card">
                    <div class="dhc-card-header">
                        <h2>API Connection</h2>
                        <p class="dhc-card-desc">Connect this WordPress site to your Dsquared Media Hub account.</p>
                    </div>
                    <div class="dhc-card-body">
                        <div class="dhc-field">
                            <label for="dhc-api-key">API Key</label>
                            <div class="dhc-input-group">
                                <input type="password" id="dhc-api-key" class="dhc-input"
                                       value="<?php echo esc_attr( $api_key ); ?>"
                                       placeholder="Enter your Hub API key" autocomplete="off">
                                <button type="button" class="dhc-btn dhc-btn-icon" id="dhc-toggle-key" title="Show/hide key">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <p class="dhc-field-hint">Find your API key in <a href="https://hub.dsquaredmedia.net/dashboard.html#account" target="_blank">Hub &rarr; Account &rarr; API Keys</a></p>
                        </div>
                        <div class="dhc-actions">
                            <button type="button" class="dhc-btn dhc-btn-primary" id="dhc-save-key">Save &amp; Validate</button>
                            <span id="dhc-key-status" class="dhc-status-msg"></span>
                        </div>
                    </div>
                </div>

                <div class="dhc-card">
                    <div class="dhc-card-header">
                        <h2>Subscription Details</h2>
                    </div>
                    <div class="dhc-card-body">
                        <div class="dhc-info-grid">
                            <div class="dhc-info-item">
                                <span class="dhc-info-label">Status</span>
                                <span class="dhc-info-value <?php echo ! empty( $subscription['valid'] ) ? 'dhc-text-success' : 'dhc-text-muted'; ?>">
                                    <?php echo ! empty( $subscription['valid'] ) ? 'Active' : ( ! empty( $subscription['expired'] ) ? 'Expired' : 'Inactive' ); ?>
                                </span>
                            </div>
                            <div class="dhc-info-item">
                                <span class="dhc-info-label">Tier</span>
                                <span class="dhc-info-value"><?php echo esc_html( DHC_API_Key::get_tier_label( $subscription['tier'] ?? '' ) ?: '—' ); ?></span>
                            </div>
                            <div class="dhc-info-item">
                                <span class="dhc-info-label">Expires</span>
                                <span class="dhc-info-value"><?php echo ! empty( $subscription['expires'] ) ? esc_html( date( 'M j, Y', strtotime( $subscription['expires'] ) ) ) : '—'; ?></span>
                            </div>
                            <div class="dhc-info-item">
                                <span class="dhc-info-label">WordPress</span>
                                <span class="dhc-info-value"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
                            </div>
                            <div class="dhc-info-item">
                                <span class="dhc-info-label">SEO Plugin</span>
                                <span class="dhc-info-value"><?php echo $seo_plugin ? esc_html( ucfirst( $seo_plugin ) ) : 'None detected'; ?></span>
                            </div>
                            <div class="dhc-info-item">
                                <span class="dhc-info-label">REST Endpoint</span>
                                <span class="dhc-info-value dhc-mono"><?php echo esc_html( rest_url( 'dsquared-hub/v1/' ) ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dhc-card dhc-card-subtle">
                    <div class="dhc-card-body">
                        <div class="dhc-notice-inline">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <p>If the plugin is disabled or your subscription lapses, it will <strong>not interrupt your website</strong>. Hub features will simply become unavailable until reactivated. Your content, schema markup, and SEO settings will be preserved. Keeping an active subscription is suggested for continued access to all features.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modules Tab -->
            <div class="dhc-tab-content" id="tab-modules">
                <div class="dhc-modules-grid">
                    <?php
                    $module_list = array(
                        'auto_post' => array(
                            'name'  => 'Auto-Post to Draft',
                            'desc'  => 'Receive blog content from the Hub and create WordPress draft posts automatically. Supports title, body, categories, tags, and featured images.',
                            'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
                            'tier'  => 'Starter+',
                        ),
                        'schema' => array(
                            'name'  => 'Schema Injector',
                            'desc'  => 'Push JSON-LD structured data from the Hub\'s Schema Generator directly into your pages. Supports per-post and site-wide schemas.',
                            'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
                            'tier'  => 'Growth+',
                        ),
                        'seo_meta' => array(
                            'name'  => 'SEO Meta Sync',
                            'desc'  => 'Sync optimized meta titles, descriptions, and OG data from the Hub\'s Page Optimizer. Compatible with Yoast, Rank Math, AIOSEO, and SEOPress.',
                            'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
                            'tier'  => 'Growth+',
                        ),
                        'site_health' => array(
                            'name'  => 'Site Health Monitor',
                            'desc'  => 'Collect real-user Core Web Vitals (LCP, CLS, INP, TTFB, FCP) and report them to the Hub for monitoring. Lightweight ~2KB script.',
                            'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
                            'tier'  => 'Pro',
                        ),
                    );

                    foreach ( $module_list as $slug => $module ) :
                        $is_enabled   = ! empty( $modules[ $slug ] );
                        $is_available = DHC_API_Key::is_module_available( $slug );
                        $tier_ok      = in_array( $slug, $subscription['modules'] ?? array(), true );
                    ?>
                    <div class="dhc-module-card <?php echo $is_available ? 'dhc-module-active' : ''; ?> <?php echo ! $tier_ok ? 'dhc-module-locked' : ''; ?>">
                        <div class="dhc-module-header">
                            <div class="dhc-module-icon"><?php echo $module['icon']; ?></div>
                            <div class="dhc-module-meta">
                                <h3><?php echo esc_html( $module['name'] ); ?></h3>
                                <span class="dhc-tier-badge"><?php echo esc_html( $module['tier'] ); ?></span>
                            </div>
                            <label class="dhc-toggle">
                                <input type="checkbox" class="dhc-module-toggle" data-module="<?php echo esc_attr( $slug ); ?>"
                                       <?php checked( $is_enabled ); ?>
                                       <?php disabled( ! $tier_ok ); ?>>
                                <span class="dhc-toggle-slider"></span>
                            </label>
                        </div>
                        <p class="dhc-module-desc"><?php echo esc_html( $module['desc'] ); ?></p>
                        <?php if ( ! $tier_ok ) : ?>
                            <div class="dhc-module-upgrade">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                                Upgrade your plan to unlock this module
                            </div>
                        <?php endif; ?>
                        <?php if ( $is_available ) : ?>
                            <div class="dhc-module-status">
                                <span class="dhc-status-dot dhc-status-active"></span> Active
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="dhc-actions" style="margin-top: 20px;">
                    <button type="button" class="dhc-btn dhc-btn-primary" id="dhc-save-modules">Save Module Settings</button>
                    <span id="dhc-modules-status" class="dhc-status-msg"></span>
                </div>
            </div>

            <!-- Site Health Tab -->
            <div class="dhc-tab-content" id="tab-health">
                <div class="dhc-card">
                    <div class="dhc-card-header">
                        <h2>Core Web Vitals — Last 30 Days</h2>
                        <p class="dhc-card-desc">Real-user metrics collected from your site visitors (p75 values).</p>
                    </div>
                    <div class="dhc-card-body">
                        <?php if ( $cwv_metrics['count'] > 0 ) : ?>
                        <div class="dhc-cwv-grid">
                            <?php
                            $cwv_items = array(
                                'lcp'  => array( 'label' => 'LCP', 'full' => 'Largest Contentful Paint', 'unit' => 'ms', 'good' => '< 2500ms' ),
                                'cls'  => array( 'label' => 'CLS', 'full' => 'Cumulative Layout Shift', 'unit' => '', 'good' => '< 0.1' ),
                                'inp'  => array( 'label' => 'INP', 'full' => 'Interaction to Next Paint', 'unit' => 'ms', 'good' => '< 200ms' ),
                                'ttfb' => array( 'label' => 'TTFB', 'full' => 'Time to First Byte', 'unit' => 'ms', 'good' => '< 800ms' ),
                                'fid'  => array( 'label' => 'FID', 'full' => 'First Input Delay', 'unit' => 'ms', 'good' => '< 100ms' ),
                            );
                            foreach ( $cwv_items as $key => $item ) :
                                $value  = $cwv_metrics[ $key . '_p75' ];
                                $rating = DHC_Site_Health::get_rating( $key, $value );
                            ?>
                            <div class="dhc-cwv-card dhc-cwv-<?php echo esc_attr( $rating ); ?>">
                                <div class="dhc-cwv-label"><?php echo esc_html( $item['label'] ); ?></div>
                                <div class="dhc-cwv-value">
                                    <?php echo null !== $value ? esc_html( $value . $item['unit'] ) : '—'; ?>
                                </div>
                                <div class="dhc-cwv-full"><?php echo esc_html( $item['full'] ); ?></div>
                                <div class="dhc-cwv-threshold">Good: <?php echo esc_html( $item['good'] ); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="dhc-cwv-count"><?php echo esc_html( number_format( $cwv_metrics['count'] ) ); ?> page loads measured</p>
                        <?php else : ?>
                        <div class="dhc-empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.4"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            <p>No Core Web Vitals data collected yet.</p>
                            <p class="dhc-text-muted">Data will appear once real users visit your site with the Site Health module enabled.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Activity Log Tab -->
            <div class="dhc-tab-content" id="tab-activity">
                <div class="dhc-card">
                    <div class="dhc-card-header">
                        <h2>Recent Activity</h2>
                        <?php if ( ! empty( $activity_log ) ) : ?>
                        <button type="button" class="dhc-btn dhc-btn-outline dhc-btn-sm" id="dhc-clear-log">Clear Log</button>
                        <?php endif; ?>
                    </div>
                    <div class="dhc-card-body">
                        <?php if ( ! empty( $activity_log ) ) : ?>
                        <div class="dhc-activity-list">
                            <?php foreach ( array_slice( $activity_log, 0, 25 ) as $entry ) : ?>
                            <div class="dhc-activity-item">
                                <div class="dhc-activity-icon">
                                    <?php echo self::get_activity_icon( $entry['action'] ); ?>
                                </div>
                                <div class="dhc-activity-content">
                                    <span class="dhc-activity-text"><?php echo esc_html( self::format_activity( $entry ) ); ?></span>
                                    <span class="dhc-activity-time"><?php echo esc_html( $entry['time'] ?? '' ); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else : ?>
                        <div class="dhc-empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.4"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <p>No activity recorded yet.</p>
                            <p class="dhc-text-muted">Actions from the Hub will appear here as they happen.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="dhc-footer">
                <span>Dsquared Hub Connector v<?php echo esc_html( DHC_VERSION ); ?> &mdash; by <a href="https://dsquaredmedia.net" target="_blank">Dsquared Media</a></span>
                <span><a href="https://hub.dsquaredmedia.net/dashboard.html#help-center" target="_blank">Support</a></span>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save settings
     */
    public static function ajax_save_settings() {
        check_ajax_referer( 'dhc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
        $modules = isset( $_POST['modules'] ) ? (array) $_POST['modules'] : array();

        // Sanitize modules
        $clean_modules = array();
        foreach ( array( 'auto_post', 'schema', 'seo_meta', 'site_health' ) as $mod ) {
            $clean_modules[ $mod ] = ! empty( $modules[ $mod ] );
        }

        update_option( 'dhc_api_key', $api_key );
        update_option( 'dhc_modules', $clean_modules );

        // Clear cached subscription so it re-validates
        DHC_API_Key::clear_cache();

        wp_send_json_success( 'Settings saved.' );
    }

    /**
     * AJAX: Validate API key
     */
    public static function ajax_validate_key() {
        check_ajax_referer( 'dhc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );

        if ( empty( $api_key ) ) {
            wp_send_json_error( 'Please enter an API key.' );
        }

        // Save the key first
        update_option( 'dhc_api_key', $api_key );
        DHC_API_Key::clear_cache();

        // Validate
        $result = DHC_API_Key::validate( $api_key, true );

        if ( ! empty( $result['valid'] ) ) {
            wp_send_json_success( array(
                'message' => 'API key validated successfully! Connected as ' . DHC_API_Key::get_tier_label( $result['tier'] ?? '' ) . '.',
                'tier'    => $result['tier'] ?? '',
                'expires' => $result['expires'] ?? '',
            ) );
        } else {
            wp_send_json_error( $result['message'] ?? 'Invalid API key.' );
        }
    }

    /**
     * AJAX: Clear activity log
     */
    public static function ajax_clear_log() {
        check_ajax_referer( 'dhc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        update_option( 'dhc_activity_log', array() );
        wp_send_json_success( 'Activity log cleared.' );
    }

    /**
     * Get activity icon SVG
     */
    private static function get_activity_icon( $action ) {
        switch ( $action ) {
            case 'auto_post':
                return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5661FF" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
            case 'schema_updated':
            case 'global_schema_updated':
                return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>';
            case 'seo_meta_sync':
                return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
            default:
                return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8892A8" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
        }
    }

    /**
     * Format activity log entry for display
     */
    private static function format_activity( $entry ) {
        switch ( $entry['action'] ) {
            case 'auto_post':
                return 'Draft post created: "' . ( $entry['title'] ?? 'Untitled' ) . '" (#' . ( $entry['post_id'] ?? '?' ) . ')';
            case 'schema_updated':
                return 'Schema markup updated for post #' . ( $entry['post_id'] ?? '?' ) . ' (' . ( $entry['schema_type'] ?? 'custom' ) . ')';
            case 'global_schema_updated':
                return 'Global schema markup updated (' . ( $entry['schema_type'] ?? 'custom' ) . ')';
            case 'seo_meta_sync':
                return 'SEO meta synced for post #' . ( $entry['post_id'] ?? '?' ) . ' → ' . ( $entry['synced_to'] ?? 'native' );
            default:
                return ucfirst( str_replace( '_', ' ', $entry['action'] ?? 'Unknown action' ) );
        }
    }

    /**
     * Menu icon SVG
     */
    private static function get_menu_icon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><rect x="2" y="3" width="16" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M5 7h4c2 0 3.5 1.5 3.5 3.5S11 14 9 14H5V7z" fill="none" stroke="currentColor" stroke-width="1.2"/><path d="M13 7h3v3" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>';
    }
}
