<?php
/**
 * KDNA Emails Module
 *
 * Comprehensive follow-up email system: scheduled emails triggered by purchases,
 * sign-ups, re-engagement, manual sends, subscriptions, and bookings. Includes
 * subscriber management, email tracking (opens/clicks/bounces), coupon generation,
 * DKIM/SPF email authentication, batch sending, and reporting.
 */
defined( 'ABSPATH' ) || exit;

class KDNA_Followup_Emails {

    // Custom post type.
    const CPT = 'kdna_fue';

    // Table names (without prefix).
    const TABLE_QUEUE        = 'kdna_fue_queue';
    const TABLE_LOGS         = 'kdna_fue_logs';
    const TABLE_SUBSCRIBERS  = 'kdna_fue_subscribers';
    const TABLE_UNSUBSCRIBES = 'kdna_fue_unsubscribes';
    const TABLE_EXCLUSIONS   = 'kdna_fue_exclusions';

    // Meta keys.
    const META_TYPE           = '_kdna_fue_type';
    const META_TRIGGER        = '_kdna_fue_trigger';
    const META_DELAY          = '_kdna_fue_delay';
    const META_DELAY_UNIT     = '_kdna_fue_delay_unit';
    const META_STATUS         = '_kdna_fue_status';
    const META_TEMPLATE_ID    = '_kdna_fue_template_id';
    const META_INCLUDE_COUPON = '_kdna_fue_include_coupon';
    const META_COUPON_CONFIG  = '_kdna_fue_coupon_config';
    const META_EXCLUSIONS     = '_kdna_fue_exclusions';
    const META_SENT_COUNT     = '_kdna_fue_sent_count';
    const META_OPEN_COUNT     = '_kdna_fue_open_count';
    const META_CLICK_COUNT    = '_kdna_fue_click_count';
    const META_TRIGGER_CONFIG = '_kdna_fue_trigger_config';
    const META_SUBJECT        = '_kdna_fue_subject';
    const META_HEADING        = '_kdna_fue_heading';
    const META_PREHEADER      = '_kdna_fue_preheader';

    private $settings;

    public function __construct() {
        $this->settings = self::get_settings();

        // Register CPT.
        add_action( 'init', [ $this, 'register_post_type' ] );

        // Admin.
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
            add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
            add_action( 'save_post_' . self::CPT, [ $this, 'save_email' ], 10, 2 );
            add_filter( 'manage_' . self::CPT . '_posts_columns', [ $this, 'admin_columns' ] );
            add_action( 'manage_' . self::CPT . '_posts_custom_column', [ $this, 'admin_column_content' ], 10, 2 );
            add_action( 'admin_menu', [ $this, 'add_admin_pages' ] );
        }

        // Order triggers.
        add_action( 'woocommerce_order_status_completed', [ $this, 'trigger_order_completed' ] );
        add_action( 'woocommerce_order_status_processing', [ $this, 'trigger_order_processing' ] );
        add_action( 'woocommerce_order_status_changed', [ $this, 'trigger_order_status_changed' ], 10, 4 );

        // Registration trigger.
        add_action( 'woocommerce_created_customer', [ $this, 'trigger_signup' ], 10, 3 );
        add_action( 'user_register', [ $this, 'trigger_user_registered' ] );

        // Checkout subscription.
        if ( $this->settings['checkout_subscription_enabled'] === 'yes' ) {
            add_action( 'woocommerce_review_order_before_submit', [ $this, 'render_checkout_subscription' ] );
            add_action( 'woocommerce_checkout_order_processed', [ $this, 'save_checkout_subscription' ], 10, 3 );
        }

        // Tracking endpoints.
        add_action( 'init', [ $this, 'add_tracking_endpoints' ] );
        add_action( 'template_redirect', [ $this, 'handle_tracking' ] );

        // Unsubscribe.
        add_action( 'init', [ $this, 'add_unsubscribe_endpoint' ] );

        // Cron via Action Scheduler.
        add_action( 'kdna_fue_process_queue', [ $this, 'process_queue' ] );
        add_action( 'kdna_fue_check_bounces', [ $this, 'check_bounces' ] );
        add_action( 'kdna_fue_daily_summary', [ $this, 'send_daily_summary' ] );
        add_action( 'kdna_fue_cleanup_logs', [ $this, 'cleanup_old_logs' ] );
        add_action( 'init', [ $this, 'schedule_cron_events' ] );

        // DKIM signing.
        if ( $this->settings['dkim_enabled'] === 'yes' ) {
            add_action( 'phpmailer_init', [ $this, 'add_dkim_signature' ] );
        }

        // AJAX handlers.
        add_action( 'wp_ajax_kdna_fue_send_test_email', [ $this, 'ajax_send_test_email' ] );
        add_action( 'wp_ajax_kdna_fue_cancel_queue_item', [ $this, 'ajax_cancel_queue_item' ] );
        add_action( 'wp_ajax_kdna_fue_reschedule_queue_item', [ $this, 'ajax_reschedule_queue_item' ] );
        add_action( 'wp_ajax_kdna_fue_delete_subscriber', [ $this, 'ajax_delete_subscriber' ] );
        add_action( 'wp_ajax_kdna_fue_import_subscribers', [ $this, 'ajax_import_subscribers' ] );
        add_action( 'wp_ajax_kdna_fue_export_subscribers', [ $this, 'ajax_export_subscribers' ] );
        add_action( 'wp_ajax_kdna_fue_export_report', [ $this, 'ajax_export_report' ] );
        add_action( 'wp_ajax_kdna_fue_resend_to_subscriber', [ $this, 'ajax_resend_to_subscriber' ] );

        // Shortcodes.
        add_shortcode( 'kdna_fue_unsubscribe', [ $this, 'shortcode_unsubscribe' ] );
        add_shortcode( 'kdna_fue_subscriptions', [ $this, 'shortcode_subscriptions' ] );
        add_shortcode( 'kdna_fue_preferences', [ $this, 'shortcode_preferences' ] );

        // REST API.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Dashboard widget.
        add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widget' ] );
    }

    // =========================================================================
    // Settings
    // =========================================================================

    public static function get_settings() {
        return wp_parse_args(
            get_option( 'kdna_followup_emails_settings', [] ),
            self::get_default_settings()
        );
    }

    public static function get_default_settings() {
        return [
            // System.
            'roles'                        => [ 'administrator' ],
            'enable_daily_summary'         => 'no',
            'daily_emails'                 => '',
            'daily_emails_time'            => '08:00',
            'staging'                      => 'no',
            'bcc'                          => '',
            'from_name'                    => '',
            'from_email'                   => '',

            // Bounce handling.
            'bounce_handle_bounces'        => 'no',
            'bounce_email'                 => '',
            'bounce_server'                => '',
            'bounce_port'                  => '110',
            'bounce_username'              => '',
            'bounce_password'              => '',
            'bounce_ssl'                   => 'no',
            'bounce_delete_messages'       => 'no',
            'bounce_soft_bounce_resend_limit'    => 3,
            'bounce_soft_bounce_resend_interval' => 24,

            // Batch sending.
            'email_batch_enabled'          => 'no',
            'emails_per_batch'             => 50,
            'email_batch_interval'         => 300,

            // Subscribers.
            'unsubscribe_endpoint'         => 'email-unsubscribe',
            'email_subscriptions_endpoint' => 'email-subscriptions',
            'email_preferences_endpoint'   => 'email-preferences',
            'checkout_subscription_enabled'      => 'no',
            'checkout_subscription_default'      => 'checked',
            'checkout_subscription_field_label'  => 'Subscribe to our newsletter',

            // DKIM.
            'dkim_enabled'                 => 'no',
            'dkim_domain'                  => '',
            'dkim_selector'                => 'kdna',
            'dkim_identity'                => '',
            'dkim_passphrase'              => '',
            'dkim_key_size'                => 2048,
            'dkim_public_key'              => '',
            'dkim_private_key'             => '',

            // SPF.
            'spf_enabled'                  => 'no',
            'spf_check_ip'                 => '',
            'spf_domain'                   => '',
            'spf_record'                   => '',
        ];
    }


    // =========================================================================
    // Installation
    // =========================================================================

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $tables = [];

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_QUEUE . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email_id bigint(20) UNSIGNED NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_id bigint(20) UNSIGNED DEFAULT 0,
            order_id bigint(20) UNSIGNED DEFAULT 0,
            product_id bigint(20) UNSIGNED DEFAULT 0,
            subject varchar(500) DEFAULT '',
            content longtext,
            coupon_code varchar(100) DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            priority int(11) DEFAULT 10,
            scheduled_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime DEFAULT NULL,
            tracking_key varchar(64) DEFAULT '',
            attempt_count int(11) DEFAULT 0,
            failure_message text DEFAULT NULL,
            meta longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY email_id (email_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY customer_email (customer_email),
            KEY tracking_key (tracking_key)
        ) $charset;";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_LOGS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email_id bigint(20) UNSIGNED NOT NULL,
            email_name varchar(255) DEFAULT '',
            customer_email varchar(255) NOT NULL,
            customer_id bigint(20) UNSIGNED DEFAULT 0,
            order_id bigint(20) UNSIGNED DEFAULT 0,
            subject varchar(500) DEFAULT '',
            tracking_key varchar(64) DEFAULT '',
            status varchar(20) DEFAULT 'sent',
            opened tinyint(1) DEFAULT 0,
            opened_at datetime DEFAULT NULL,
            clicked tinyint(1) DEFAULT 0,
            clicked_at datetime DEFAULT NULL,
            click_count int(11) DEFAULT 0,
            bounced tinyint(1) DEFAULT 0,
            bounce_type varchar(10) DEFAULT '',
            unsubscribed tinyint(1) DEFAULT 0,
            converted tinyint(1) DEFAULT 0,
            conversion_order_id bigint(20) UNSIGNED DEFAULT 0,
            conversion_total decimal(12,2) DEFAULT 0,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email_id (email_id),
            KEY customer_email (customer_email),
            KEY tracking_key (tracking_key),
            KEY sent_at (sent_at)
        ) $charset;";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_SUBSCRIBERS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            first_name varchar(100) DEFAULT '',
            last_name varchar(100) DEFAULT '',
            user_id bigint(20) UNSIGNED DEFAULT 0,
            status varchar(20) DEFAULT 'subscribed',
            source varchar(50) DEFAULT 'manual',
            double_optin_confirmed tinyint(1) DEFAULT 0,
            double_optin_token varchar(64) DEFAULT '',
            total_orders int(11) DEFAULT 0,
            total_spent decimal(12,2) DEFAULT 0,
            last_order_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY user_id (user_id)
        ) $charset;";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_UNSUBSCRIBES . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            email_id bigint(20) UNSIGNED DEFAULT 0,
            email_type varchar(50) DEFAULT 'all',
            reason text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY email_type (email_type)
        ) $charset;";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_EXCLUSIONS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email_id bigint(20) UNSIGNED NOT NULL,
            exclusion_type varchar(50) NOT NULL,
            exclusion_value varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email_id (email_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }
    }

    // =========================================================================
    // Custom Post Type
    // =========================================================================

    public function register_post_type() {
        register_post_type( self::CPT, [
            'labels' => [
                'name'               => __( 'Emails', 'kdna-ecommerce' ),
                'singular_name'      => __( 'Email', 'kdna-ecommerce' ),
                'add_new'            => __( 'Add Email', 'kdna-ecommerce' ),
                'add_new_item'       => __( 'Add New Email', 'kdna-ecommerce' ),
                'edit_item'          => __( 'Edit Email', 'kdna-ecommerce' ),
                'new_item'           => __( 'New Email', 'kdna-ecommerce' ),
                'view_item'          => __( 'View Email', 'kdna-ecommerce' ),
                'search_items'       => __( 'Search Emails', 'kdna-ecommerce' ),
                'not_found'          => __( 'No emails found.', 'kdna-ecommerce' ),
                'not_found_in_trash' => __( 'No emails in trash.', 'kdna-ecommerce' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'supports'     => [ 'title', 'editor' ],
            'capability_type' => 'post',
        ] );
    }


    // =========================================================================
    // Admin Interface
    // =========================================================================

    public function enqueue_admin_assets( $hook ) {
        global $post_type;
        if ( $post_type !== self::CPT && strpos( $hook, 'kdna-fue-' ) === false ) {
            return;
        }
        wp_enqueue_style( 'kdna-followup-emails-admin', KDNA_ECOMMERCE_URL . 'modules/followup-emails/assets/followup-emails-admin.css', [], KDNA_ECOMMERCE_VERSION );
        wp_enqueue_script( 'kdna-followup-emails-admin', KDNA_ECOMMERCE_URL . 'modules/followup-emails/assets/followup-emails-admin.js', [ 'jquery', 'wp-util', 'jquery-ui-datepicker' ], KDNA_ECOMMERCE_VERSION, true );

        $email_data = null;
        if ( isset( $_GET['post'] ) ) {
            $post_id    = absint( $_GET['post'] );
            $email_data = [
                'type'           => get_post_meta( $post_id, self::META_TYPE, true ),
                'include_coupon' => get_post_meta( $post_id, self::META_INCLUDE_COUPON, true ),
            ];
        }

        wp_localize_script( 'kdna-followup-emails-admin', 'kdnaFUE', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'kdna_fue_admin' ),
            'postId'  => isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0,
            'email'   => $email_data,
            'mergeTags' => $this->get_merge_tags_grouped(),
            'i18n'    => [
                'enter_email'      => __( 'Please enter an email address.', 'kdna-ecommerce' ),
                'sending'          => __( 'Sending...', 'kdna-ecommerce' ),
                'send_test'        => __( 'Send Test', 'kdna-ecommerce' ),
                'test_sent'        => __( 'Test email sent!', 'kdna-ecommerce' ),
                'test_failed'      => __( 'Failed to send test email.', 'kdna-ecommerce' ),
                'confirm_cancel'   => __( 'Cancel this scheduled email?', 'kdna-ecommerce' ),
                'confirm_delete'   => __( 'Delete this subscriber?', 'kdna-ecommerce' ),
                'importing'        => __( 'Importing...', 'kdna-ecommerce' ),
                'import'           => __( 'Import', 'kdna-ecommerce' ),
            ],
        ] );
    }

    public function add_meta_boxes() {
        add_meta_box( 'kdna-fue-email-config', __( 'Email Configuration', 'kdna-ecommerce' ), [ $this, 'render_email_config' ], self::CPT, 'normal', 'high' );
        add_meta_box( 'kdna-fue-email-status', __( 'Status & Stats', 'kdna-ecommerce' ), [ $this, 'render_status_box' ], self::CPT, 'side', 'high' );
        add_meta_box( 'kdna-fue-test-email', __( 'Test Email', 'kdna-ecommerce' ), [ $this, 'render_test_box' ], self::CPT, 'side', 'default' );
    }

    public function render_status_box( $post ) {
        $status = get_post_meta( $post->ID, self::META_STATUS, true ) ?: 'disabled';
        $sent   = (int) get_post_meta( $post->ID, self::META_SENT_COUNT, true );
        $opens  = (int) get_post_meta( $post->ID, self::META_OPEN_COUNT, true );
        $clicks = (int) get_post_meta( $post->ID, self::META_CLICK_COUNT, true );
        $rate   = $sent > 0 ? round( ( $opens / $sent ) * 100, 1 ) : 0;
        wp_nonce_field( 'kdna_fue_save_email', 'kdna_fue_nonce' );
        ?>
        <p>
            <label for="kdna_fue_status"><strong><?php esc_html_e( 'Email Status', 'kdna-ecommerce' ); ?></strong></label><br>
            <select name="kdna_fue_status" id="kdna_fue_status" style="width:100%;margin-top:4px;">
                <option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'kdna-ecommerce' ); ?></option>
                <option value="disabled" <?php selected( $status, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'kdna-ecommerce' ); ?></option>
            </select>
        </p>
        <hr>
        <p><?php printf( esc_html__( 'Sent: %d', 'kdna-ecommerce' ), $sent ); ?></p>
        <p><?php printf( esc_html__( 'Opens: %d (%s%%)', 'kdna-ecommerce' ), $opens, $rate ); ?></p>
        <p><?php printf( esc_html__( 'Clicks: %d', 'kdna-ecommerce' ), $clicks ); ?></p>
        <?php
    }

    public function render_test_box( $post ) {
        ?>
        <div class="kdna-fue-test-email">
            <input type="email" class="kdna-fue-test-email-input" placeholder="<?php esc_attr_e( 'test@example.com', 'kdna-ecommerce' ); ?>" style="flex:1;" />
            <button type="button" class="button kdna-fue-send-test"><?php esc_html_e( 'Send Test', 'kdna-ecommerce' ); ?></button>
        </div>
        <?php
    }

    public function render_email_config( $post ) {
        $type          = get_post_meta( $post->ID, self::META_TYPE, true ) ?: '';
        $trigger       = get_post_meta( $post->ID, self::META_TRIGGER, true ) ?: '';
        $trigger_config= get_post_meta( $post->ID, self::META_TRIGGER_CONFIG, true ) ?: [];
        $delay         = get_post_meta( $post->ID, self::META_DELAY, true ) ?: '1';
        $delay_unit    = get_post_meta( $post->ID, self::META_DELAY_UNIT, true ) ?: 'days';
        $subject       = get_post_meta( $post->ID, self::META_SUBJECT, true ) ?: '';
        $heading       = get_post_meta( $post->ID, self::META_HEADING, true ) ?: '';
        $preheader     = get_post_meta( $post->ID, self::META_PREHEADER, true ) ?: '';
        $template_id   = get_post_meta( $post->ID, self::META_TEMPLATE_ID, true ) ?: '';
        $include_coupon= get_post_meta( $post->ID, self::META_INCLUDE_COUPON, true ) ?: 'no';
        $coupon_config = get_post_meta( $post->ID, self::META_COUPON_CONFIG, true ) ?: [];

        $types = [
            'purchase'       => [ 'label' => __( 'Purchase', 'kdna-ecommerce' ), 'icon' => 'dashicons-cart', 'desc' => __( 'After a purchase', 'kdna-ecommerce' ) ],
            're_engagement'  => [ 'label' => __( 'Re-Engagement', 'kdna-ecommerce' ), 'icon' => 'dashicons-update', 'desc' => __( 'Win back inactive customers', 'kdna-ecommerce' ) ],
            'signup'         => [ 'label' => __( 'Sign-up', 'kdna-ecommerce' ), 'icon' => 'dashicons-admin-users', 'desc' => __( 'After registration', 'kdna-ecommerce' ) ],
            'manual'         => [ 'label' => __( 'Manual', 'kdna-ecommerce' ), 'icon' => 'dashicons-email-alt', 'desc' => __( 'One-time send', 'kdna-ecommerce' ) ],
            'subscription'   => [ 'label' => __( 'Subscription', 'kdna-ecommerce' ), 'icon' => 'dashicons-admin-network', 'desc' => __( 'Subscription events', 'kdna-ecommerce' ) ],
            'booking'        => [ 'label' => __( 'Booking', 'kdna-ecommerce' ), 'icon' => 'dashicons-calendar-alt', 'desc' => __( 'Booking events', 'kdna-ecommerce' ) ],
        ];
        ?>
        <div id="kdna-fue-email-editor">
            <input type="hidden" name="fue_email_type" value="<?php echo esc_attr( $type ); ?>" />

            <!-- Email Type -->
            <div class="kdna-fue-editor-section">
                <div class="kdna-fue-editor-section-header"><h3><?php esc_html_e( 'Email Type', 'kdna-ecommerce' ); ?></h3></div>
                <div class="kdna-fue-editor-section-body">
                    <div class="kdna-fue-type-cards">
                        <?php foreach ( $types as $key => $t ) : ?>
                        <div class="kdna-fue-type-card <?php echo $type === $key ? 'selected' : ''; ?>" data-type="<?php echo esc_attr( $key ); ?>">
                            <span class="dashicons <?php echo esc_attr( $t['icon'] ); ?>"></span>
                            <h4><?php echo esc_html( $t['label'] ); ?></h4>
                            <p><?php echo esc_html( $t['desc'] ); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Trigger & Delay -->
            <div class="kdna-fue-editor-section">
                <div class="kdna-fue-editor-section-header"><h3><?php esc_html_e( 'Trigger & Timing', 'kdna-ecommerce' ); ?></h3></div>
                <div class="kdna-fue-editor-section-body">
                    <div class="kdna-fue-trigger-config">
                        <!-- Purchase triggers -->
                        <div class="kdna-fue-trigger-section" data-type="purchase" <?php echo $type !== 'purchase' ? 'style="display:none;"' : ''; ?>>
                            <div class="form-field">
                                <label><?php esc_html_e( 'Trigger', 'kdna-ecommerce' ); ?></label>
                                <select name="fue_trigger" class="kdna-fue-trigger-subtype">
                                    <option value="order_completed" <?php selected( $trigger, 'order_completed' ); ?>><?php esc_html_e( 'Order Completed', 'kdna-ecommerce' ); ?></option>
                                    <option value="order_processing" <?php selected( $trigger, 'order_processing' ); ?>><?php esc_html_e( 'Order Processing', 'kdna-ecommerce' ); ?></option>
                                    <option value="specific_product" <?php selected( $trigger, 'specific_product' ); ?>><?php esc_html_e( 'Specific Product Purchased', 'kdna-ecommerce' ); ?></option>
                                    <option value="specific_category" <?php selected( $trigger, 'specific_category' ); ?>><?php esc_html_e( 'Specific Category Purchased', 'kdna-ecommerce' ); ?></option>
                                    <option value="first_purchase" <?php selected( $trigger, 'first_purchase' ); ?>><?php esc_html_e( 'First Purchase', 'kdna-ecommerce' ); ?></option>
                                </select>
                            </div>
                            <div class="kdna-fue-subtype-fields" data-subtype="specific_product" <?php echo $trigger !== 'specific_product' ? 'style="display:none;"' : ''; ?>>
                                <div class="form-field">
                                    <label><?php esc_html_e( 'Product IDs (comma separated)', 'kdna-ecommerce' ); ?></label>
                                    <input type="text" name="fue_trigger_config[product_ids]" value="<?php echo esc_attr( $trigger_config['product_ids'] ?? '' ); ?>" />
                                </div>
                            </div>
                            <div class="kdna-fue-subtype-fields" data-subtype="specific_category" <?php echo $trigger !== 'specific_category' ? 'style="display:none;"' : ''; ?>>
                                <div class="form-field">
                                    <label><?php esc_html_e( 'Category IDs (comma separated)', 'kdna-ecommerce' ); ?></label>
                                    <input type="text" name="fue_trigger_config[category_ids]" value="<?php echo esc_attr( $trigger_config['category_ids'] ?? '' ); ?>" />
                                </div>
                            </div>
                        </div>

                        <!-- Re-engagement triggers -->
                        <div class="kdna-fue-trigger-section" data-type="re_engagement" <?php echo $type !== 're_engagement' ? 'style="display:none;"' : ''; ?>>
                            <div class="form-field">
                                <label><?php esc_html_e( 'Trigger', 'kdna-ecommerce' ); ?></label>
                                <select name="fue_trigger">
                                    <option value="inactive_customer" <?php selected( $trigger, 'inactive_customer' ); ?>><?php esc_html_e( 'Inactive Customer (no order in X days)', 'kdna-ecommerce' ); ?></option>
                                    <option value="abandoned_cart" <?php selected( $trigger, 'abandoned_cart' ); ?>><?php esc_html_e( 'Abandoned Cart Follow-up', 'kdna-ecommerce' ); ?></option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label><?php esc_html_e( 'Inactivity Period (days)', 'kdna-ecommerce' ); ?></label>
                                <input type="number" name="fue_trigger_config[inactivity_days]" value="<?php echo esc_attr( $trigger_config['inactivity_days'] ?? '30' ); ?>" min="1" />
                            </div>
                        </div>

                        <!-- Signup triggers -->
                        <div class="kdna-fue-trigger-section" data-type="signup" <?php echo $type !== 'signup' ? 'style="display:none;"' : ''; ?>>
                            <div class="form-field">
                                <label><?php esc_html_e( 'Trigger', 'kdna-ecommerce' ); ?></label>
                                <select name="fue_trigger">
                                    <option value="user_registered" <?php selected( $trigger, 'user_registered' ); ?>><?php esc_html_e( 'After Registration', 'kdna-ecommerce' ); ?></option>
                                    <option value="newsletter_signup" <?php selected( $trigger, 'newsletter_signup' ); ?>><?php esc_html_e( 'Newsletter Sign-up', 'kdna-ecommerce' ); ?></option>
                                </select>
                            </div>
                        </div>

                        <!-- Subscription triggers -->
                        <div class="kdna-fue-trigger-section" data-type="subscription" <?php echo $type !== 'subscription' ? 'style="display:none;"' : ''; ?>>
                            <div class="form-field">
                                <label><?php esc_html_e( 'Trigger', 'kdna-ecommerce' ); ?></label>
                                <select name="fue_trigger">
                                    <option value="sub_before_renewal" <?php selected( $trigger, 'sub_before_renewal' ); ?>><?php esc_html_e( 'Before Renewal', 'kdna-ecommerce' ); ?></option>
                                    <option value="sub_after_renewal" <?php selected( $trigger, 'sub_after_renewal' ); ?>><?php esc_html_e( 'After Renewal', 'kdna-ecommerce' ); ?></option>
                                    <option value="sub_cancelled" <?php selected( $trigger, 'sub_cancelled' ); ?>><?php esc_html_e( 'Subscription Cancelled', 'kdna-ecommerce' ); ?></option>
                                    <option value="sub_expired" <?php selected( $trigger, 'sub_expired' ); ?>><?php esc_html_e( 'Subscription Expired', 'kdna-ecommerce' ); ?></option>
                                </select>
                            </div>
                        </div>

                        <!-- Booking triggers -->
                        <div class="kdna-fue-trigger-section" data-type="booking" <?php echo $type !== 'booking' ? 'style="display:none;"' : ''; ?>>
                            <div class="form-field">
                                <label><?php esc_html_e( 'Trigger', 'kdna-ecommerce' ); ?></label>
                                <select name="fue_trigger">
                                    <option value="booking_confirmed" <?php selected( $trigger, 'booking_confirmed' ); ?>><?php esc_html_e( 'Booking Confirmed', 'kdna-ecommerce' ); ?></option>
                                    <option value="booking_reminder" <?php selected( $trigger, 'booking_reminder' ); ?>><?php esc_html_e( 'Booking Reminder', 'kdna-ecommerce' ); ?></option>
                                    <option value="booking_completed" <?php selected( $trigger, 'booking_completed' ); ?>><?php esc_html_e( 'Booking Completed', 'kdna-ecommerce' ); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Delay -->
                    <div class="form-field" style="margin-top:12px;">
                        <label><?php esc_html_e( 'Send After', 'kdna-ecommerce' ); ?></label>
                        <div class="kdna-fue-delay-row">
                            <input type="number" name="fue_delay" value="<?php echo esc_attr( $delay ); ?>" min="0" />
                            <select name="fue_delay_unit">
                                <option value="minutes" <?php selected( $delay_unit, 'minutes' ); ?>><?php esc_html_e( 'Minutes', 'kdna-ecommerce' ); ?></option>
                                <option value="hours" <?php selected( $delay_unit, 'hours' ); ?>><?php esc_html_e( 'Hours', 'kdna-ecommerce' ); ?></option>
                                <option value="days" <?php selected( $delay_unit, 'days' ); ?>><?php esc_html_e( 'Days', 'kdna-ecommerce' ); ?></option>
                                <option value="weeks" <?php selected( $delay_unit, 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'kdna-ecommerce' ); ?></option>
                            </select>
                            <span><?php esc_html_e( 'after trigger event', 'kdna-ecommerce' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Content -->
            <div class="kdna-fue-editor-section">
                <div class="kdna-fue-editor-section-header"><h3><?php esc_html_e( 'Email Content', 'kdna-ecommerce' ); ?></h3></div>
                <div class="kdna-fue-editor-section-body">
                    <div class="form-field">
                        <label><?php esc_html_e( 'Subject', 'kdna-ecommerce' ); ?></label>
                        <input type="text" name="fue_subject" value="<?php echo esc_attr( $subject ); ?>" class="regular-text" style="width:100%;" />
                    </div>
                    <div class="form-field">
                        <label><?php esc_html_e( 'Heading', 'kdna-ecommerce' ); ?></label>
                        <input type="text" name="fue_heading" value="<?php echo esc_attr( $heading ); ?>" class="regular-text" style="width:100%;" />
                    </div>
                    <div class="form-field">
                        <label><?php esc_html_e( 'Preheader', 'kdna-ecommerce' ); ?></label>
                        <input type="text" name="fue_preheader" value="<?php echo esc_attr( $preheader ); ?>" class="regular-text" style="width:100%;" />
                    </div>
                    <div class="form-field">
                        <label><?php esc_html_e( 'Template (from Email Builder)', 'kdna-ecommerce' ); ?></label>
                        <div class="kdna-fue-template-selector">
                            <select name="fue_template_id" class="kdna-fue-template-select">
                                <option value=""><?php esc_html_e( '— Use post content below —', 'kdna-ecommerce' ); ?></option>
                                <?php
                                $templates = get_posts( [ 'post_type' => 'kdna_email_tpl', 'posts_per_page' => -1, 'post_status' => 'publish' ] );
                                foreach ( $templates as $tpl ) :
                                ?>
                                    <option value="<?php echo esc_attr( $tpl->ID ); ?>" <?php selected( $template_id, $tpl->ID ); ?>><?php echo esc_html( $tpl->post_title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="fue_selected_template_id" class="kdna-fue-selected-template-id" value="<?php echo esc_attr( $template_id ); ?>" />
                        </div>
                        <div class="kdna-fue-template-preview" <?php echo empty( $template_id ) ? 'style="display:none;"' : ''; ?>>
                            <h4><?php esc_html_e( 'Template Preview', 'kdna-ecommerce' ); ?></h4>
                            <div class="kdna-fue-template-preview-frame"></div>
                        </div>
                    </div>
                    <p class="description"><?php esc_html_e( 'If no template is selected, the post content editor above will be used as the email body.', 'kdna-ecommerce' ); ?></p>
                </div>
            </div>

            <!-- Coupon -->
            <div class="kdna-fue-editor-section kdna-fue-coupon-section">
                <div class="kdna-fue-editor-section-header"><h3><?php esc_html_e( 'Coupon', 'kdna-ecommerce' ); ?></h3></div>
                <div class="kdna-fue-editor-section-body">
                    <label>
                        <input type="checkbox" id="fue_include_coupon" name="fue_include_coupon" value="yes" <?php checked( $include_coupon, 'yes' ); ?> />
                        <?php esc_html_e( 'Include auto-generated coupon', 'kdna-ecommerce' ); ?>
                    </label>
                    <div class="kdna-fue-coupon-config" <?php echo $include_coupon !== 'yes' ? 'style="display:none;"' : ''; ?>>
                        <h4><?php esc_html_e( 'Coupon Settings', 'kdna-ecommerce' ); ?></h4>
                        <div class="kdna-fue-coupon-fields">
                            <div class="form-field">
                                <label><?php esc_html_e( 'Discount Type', 'kdna-ecommerce' ); ?></label>
                                <select name="fue_coupon_config[type]">
                                    <option value="percent" <?php selected( $coupon_config['type'] ?? '', 'percent' ); ?>><?php esc_html_e( 'Percentage', 'kdna-ecommerce' ); ?></option>
                                    <option value="fixed_cart" <?php selected( $coupon_config['type'] ?? '', 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed Cart', 'kdna-ecommerce' ); ?></option>
                                    <option value="fixed_product" <?php selected( $coupon_config['type'] ?? '', 'fixed_product' ); ?>><?php esc_html_e( 'Fixed Product', 'kdna-ecommerce' ); ?></option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label><?php esc_html_e( 'Amount', 'kdna-ecommerce' ); ?></label>
                                <input type="number" name="fue_coupon_config[amount]" value="<?php echo esc_attr( $coupon_config['amount'] ?? '10' ); ?>" step="0.01" />
                            </div>
                            <div class="form-field">
                                <label><?php esc_html_e( 'Code Prefix', 'kdna-ecommerce' ); ?></label>
                                <input type="text" name="fue_coupon_config[prefix]" value="<?php echo esc_attr( $coupon_config['prefix'] ?? 'FUE-' ); ?>" />
                            </div>
                            <div class="form-field">
                                <label><?php esc_html_e( 'Valid For (days)', 'kdna-ecommerce' ); ?></label>
                                <input type="number" name="fue_coupon_config[expiry_days]" value="<?php echo esc_attr( $coupon_config['expiry_days'] ?? '30' ); ?>" min="1" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function save_email( $post_id, $post ) {
        if ( ! isset( $_POST['kdna_fue_nonce'] ) || ! wp_verify_nonce( $_POST['kdna_fue_nonce'], 'kdna_fue_save_email' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        update_post_meta( $post_id, self::META_STATUS, sanitize_text_field( $_POST['kdna_fue_status'] ?? 'disabled' ) );
        update_post_meta( $post_id, self::META_TYPE, sanitize_text_field( $_POST['fue_email_type'] ?? '' ) );
        update_post_meta( $post_id, self::META_TRIGGER, sanitize_text_field( $_POST['fue_trigger'] ?? '' ) );
        update_post_meta( $post_id, self::META_TRIGGER_CONFIG, array_map( 'sanitize_text_field', $_POST['fue_trigger_config'] ?? [] ) );
        update_post_meta( $post_id, self::META_DELAY, absint( $_POST['fue_delay'] ?? 1 ) );
        update_post_meta( $post_id, self::META_DELAY_UNIT, sanitize_text_field( $_POST['fue_delay_unit'] ?? 'days' ) );
        update_post_meta( $post_id, self::META_SUBJECT, sanitize_text_field( $_POST['fue_subject'] ?? '' ) );
        update_post_meta( $post_id, self::META_HEADING, sanitize_text_field( $_POST['fue_heading'] ?? '' ) );
        update_post_meta( $post_id, self::META_PREHEADER, sanitize_text_field( $_POST['fue_preheader'] ?? '' ) );
        update_post_meta( $post_id, self::META_TEMPLATE_ID, absint( $_POST['fue_template_id'] ?? 0 ) );
        update_post_meta( $post_id, self::META_INCLUDE_COUPON, sanitize_text_field( $_POST['fue_include_coupon'] ?? 'no' ) );
        update_post_meta( $post_id, self::META_COUPON_CONFIG, array_map( 'sanitize_text_field', $_POST['fue_coupon_config'] ?? [] ) );

        // Exclusions.
        if ( isset( $_POST['fue_exclusions'] ) ) {
            global $wpdb;
            $wpdb->delete( $wpdb->prefix . self::TABLE_EXCLUSIONS, [ 'email_id' => $post_id ] );
            foreach ( $_POST['fue_exclusions'] as $exc ) {
                $wpdb->insert( $wpdb->prefix . self::TABLE_EXCLUSIONS, [
                    'email_id'        => $post_id,
                    'exclusion_type'  => sanitize_text_field( $exc['type'] ?? '' ),
                    'exclusion_value' => sanitize_text_field( $exc['value'] ?? '' ),
                ] );
            }
        }
    }

    public function admin_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['fue_type']      = __( 'Type', 'kdna-ecommerce' );
                $new['fue_status']    = __( 'Status', 'kdna-ecommerce' );
                $new['fue_sent']      = __( 'Sent', 'kdna-ecommerce' );
                $new['fue_open_rate'] = __( 'Open Rate', 'kdna-ecommerce' );
            }
        }
        unset( $new['date'] );
        return $new;
    }

    public function admin_column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'fue_type':
                $type = get_post_meta( $post_id, self::META_TYPE, true ) ?: 'manual';
                echo '<span class="kdna-fue-type-badge ' . esc_attr( $type ) . '">' . esc_html( ucfirst( str_replace( '_', '-', $type ) ) ) . '</span>';
                break;
            case 'fue_status':
                $status = get_post_meta( $post_id, self::META_STATUS, true ) ?: 'disabled';
                echo '<span class="kdna-fue-status-badge ' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
                break;
            case 'fue_sent':
                echo (int) get_post_meta( $post_id, self::META_SENT_COUNT, true );
                break;
            case 'fue_open_rate':
                $sent  = (int) get_post_meta( $post_id, self::META_SENT_COUNT, true );
                $opens = (int) get_post_meta( $post_id, self::META_OPEN_COUNT, true );
                echo $sent > 0 ? round( ( $opens / $sent ) * 100, 1 ) . '%' : '—';
                break;
        }
    }

    public function add_admin_pages() {
        add_submenu_page( 'kdna-ecommerce', __( 'Emails', 'kdna-ecommerce' ), __( 'Emails', 'kdna-ecommerce' ), 'manage_woocommerce', 'edit.php?post_type=' . self::CPT );
        add_submenu_page( 'kdna-ecommerce', __( 'FUE Queue', 'kdna-ecommerce' ), __( 'FUE Queue', 'kdna-ecommerce' ), 'manage_woocommerce', 'kdna-fue-queue', [ $this, 'render_queue_page' ] );
        add_submenu_page( 'kdna-ecommerce', __( 'FUE Reports', 'kdna-ecommerce' ), __( 'FUE Reports', 'kdna-ecommerce' ), 'manage_woocommerce', 'kdna-fue-reports', [ $this, 'render_reports_page' ] );
        add_submenu_page( 'kdna-ecommerce', __( 'Subscribers', 'kdna-ecommerce' ), __( 'Subscribers', 'kdna-ecommerce' ), 'manage_woocommerce', 'kdna-fue-subscribers', [ $this, 'render_subscribers_page' ] );
    }


    // =========================================================================
    // Triggers
    // =========================================================================

    public function trigger_order_completed( $order_id ) {
        $this->handle_order_trigger( $order_id, 'order_completed' );
    }

    public function trigger_order_processing( $order_id ) {
        $this->handle_order_trigger( $order_id, 'order_processing' );
    }

    public function trigger_order_status_changed( $order_id, $old_status, $new_status, $order ) {
        if ( $new_status === 'completed' ) {
            $this->handle_order_trigger( $order_id, 'order_completed' );
        }
    }

    private function handle_order_trigger( $order_id, $trigger_type ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $email    = $order->get_billing_email();
        $user_id  = $order->get_customer_id();

        // Check if unsubscribed.
        if ( $this->is_unsubscribed( $email ) ) return;

        // Find matching active emails.
        $emails = $this->get_active_emails_by_trigger( $trigger_type );

        foreach ( $emails as $email_post ) {
            $email_id      = $email_post->ID;
            $trigger_config= get_post_meta( $email_id, self::META_TRIGGER_CONFIG, true ) ?: [];

            // Check trigger-specific conditions.
            if ( $trigger_type === 'specific_product' || get_post_meta( $email_id, self::META_TRIGGER, true ) === 'specific_product' ) {
                $product_ids = array_map( 'absint', explode( ',', $trigger_config['product_ids'] ?? '' ) );
                $order_product_ids = [];
                foreach ( $order->get_items() as $item ) {
                    $order_product_ids[] = $item->get_product_id();
                }
                if ( ! array_intersect( $product_ids, $order_product_ids ) ) continue;
            }

            if ( get_post_meta( $email_id, self::META_TRIGGER, true ) === 'specific_category' ) {
                $cat_ids = array_map( 'absint', explode( ',', $trigger_config['category_ids'] ?? '' ) );
                $found   = false;
                foreach ( $order->get_items() as $item ) {
                    $terms = wp_get_object_terms( $item->get_product_id(), 'product_cat', [ 'fields' => 'ids' ] );
                    if ( array_intersect( $cat_ids, $terms ) ) {
                        $found = true;
                        break;
                    }
                }
                if ( ! $found ) continue;
            }

            if ( get_post_meta( $email_id, self::META_TRIGGER, true ) === 'first_purchase' ) {
                if ( $user_id ) {
                    $count = wc_get_customer_order_count( $user_id );
                    if ( $count > 1 ) continue;
                }
            }

            // Check exclusions.
            if ( $this->is_excluded( $email_id, $email, $order ) ) continue;

            // Queue the email.
            $this->queue_email( $email_id, $email, $user_id, $order_id );
        }
    }

    public function trigger_signup( $customer_id, $new_customer_data, $password_generated ) {
        $user  = get_userdata( $customer_id );
        $email = $user->user_email;

        if ( $this->is_unsubscribed( $email ) ) return;

        $emails = $this->get_active_emails_by_type( 'signup' );
        foreach ( $emails as $email_post ) {
            $this->queue_email( $email_post->ID, $email, $customer_id );
        }
    }

    public function trigger_user_registered( $user_id ) {
        $user  = get_userdata( $user_id );
        if ( ! $user ) return;
        $email = $user->user_email;

        if ( $this->is_unsubscribed( $email ) ) return;

        $emails = $this->get_active_emails_by_trigger( 'user_registered' );
        foreach ( $emails as $email_post ) {
            $this->queue_email( $email_post->ID, $email, $user_id );
        }
    }

    private function get_active_emails_by_trigger( $trigger ) {
        return get_posts( [
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => self::META_STATUS, 'value' => 'active' ],
                [ 'key' => self::META_TRIGGER, 'value' => $trigger ],
            ],
        ] );
    }

    private function get_active_emails_by_type( $type ) {
        return get_posts( [
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => self::META_STATUS, 'value' => 'active' ],
                [ 'key' => self::META_TYPE, 'value' => $type ],
            ],
        ] );
    }

    // =========================================================================
    // Queue Management
    // =========================================================================

    private function queue_email( $email_id, $customer_email, $customer_id = 0, $order_id = 0, $product_id = 0 ) {
        global $wpdb;

        $delay      = (int) get_post_meta( $email_id, self::META_DELAY, true ) ?: 1;
        $delay_unit = get_post_meta( $email_id, self::META_DELAY_UNIT, true ) ?: 'days';

        switch ( $delay_unit ) {
            case 'minutes': $scheduled = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delay} minutes" ) ); break;
            case 'hours':   $scheduled = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delay} hours" ) ); break;
            case 'days':    $scheduled = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delay} days" ) ); break;
            case 'weeks':   $scheduled = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delay} weeks" ) ); break;
            default:        $scheduled = current_time( 'mysql' );
        }

        $tracking_key = wp_generate_password( 32, false );
        $subject      = get_post_meta( $email_id, self::META_SUBJECT, true ) ?: get_the_title( $email_id );

        // Generate coupon if needed.
        $coupon_code = '';
        if ( get_post_meta( $email_id, self::META_INCLUDE_COUPON, true ) === 'yes' ) {
            $coupon_code = $this->generate_coupon( $email_id, $customer_email );
        }

        $wpdb->insert( $wpdb->prefix . self::TABLE_QUEUE, [
            'email_id'       => $email_id,
            'customer_email' => $customer_email,
            'customer_id'    => $customer_id,
            'order_id'       => $order_id,
            'product_id'     => $product_id,
            'subject'        => $subject,
            'coupon_code'    => $coupon_code,
            'status'         => 'pending',
            'priority'       => 10,
            'scheduled_at'   => $scheduled,
            'tracking_key'   => $tracking_key,
        ] );
    }

    public function process_queue() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_QUEUE;

        $batch_size = $this->settings['email_batch_enabled'] === 'yes'
            ? absint( $this->settings['emails_per_batch'] ) ?: 50
            : 50;

        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s ORDER BY priority ASC, scheduled_at ASC LIMIT %d",
            current_time( 'mysql' ), $batch_size
        ) );

        foreach ( $items as $item ) {
            $wpdb->update( $table, [ 'status' => 'sending' ], [ 'id' => $item->id ] );

            $result = $this->send_email( $item );

            if ( $result ) {
                $wpdb->update( $table, [
                    'status'  => 'sent',
                    'sent_at' => current_time( 'mysql' ),
                ], [ 'id' => $item->id ] );

                // Log.
                $this->log_sent_email( $item );

                // Update stats.
                $sent = (int) get_post_meta( $item->email_id, self::META_SENT_COUNT, true );
                update_post_meta( $item->email_id, self::META_SENT_COUNT, $sent + 1 );
            } else {
                $attempts = $item->attempt_count + 1;
                $status   = $attempts >= 3 ? 'failed' : 'pending';
                $wpdb->update( $table, [
                    'status'          => $status,
                    'attempt_count'   => $attempts,
                    'failure_message' => 'Send failed after attempt ' . $attempts,
                ], [ 'id' => $item->id ] );
            }
        }
    }

    // =========================================================================
    // Email Sending
    // =========================================================================

    private function send_email( $queue_item ) {
        // Staging mode - log but don't send.
        if ( $this->settings['staging'] === 'yes' ) {
            $this->log_sent_email( $queue_item );
            return true;
        }

        $email_id = $queue_item->email_id;
        $to       = $queue_item->customer_email;

        // Generate coupon at send time if the email requires one but the queue entry has none.
        if ( empty( $queue_item->coupon_code ) && get_post_meta( $email_id, self::META_INCLUDE_COUPON, true ) === 'yes' ) {
            $coupon_config = get_post_meta( $email_id, self::META_COUPON_CONFIG, true ) ?: [];
            $coupon_code   = $this->generate_coupon( $email_id, $to );

            // Store the coupon code back in the queue record.
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . self::TABLE_QUEUE,
                [ 'coupon_code' => $coupon_code ],
                [ 'id' => $queue_item->id ]
            );
            $queue_item->coupon_code = $coupon_code;
        }

        // Build context for merge tags.
        $context = $this->build_context( $queue_item );

        // Get subject.
        $subject = $this->replace_merge_tags( $queue_item->subject, $context );

        // Get content.
        $template_id = get_post_meta( $email_id, self::META_TEMPLATE_ID, true );
        if ( $template_id && class_exists( 'KDNA_Email_Builder' ) ) {
            $html = KDNA_Email_Builder::compile_template( $template_id );
        } else {
            $content = get_post_field( 'post_content', $email_id );
            $heading = get_post_meta( $email_id, self::META_HEADING, true );
            $mailer  = WC()->mailer();
            $html    = $mailer->wrap_message( $heading ?: $subject, wpautop( $content ) );
        }

        // Replace merge tags in content.
        $html = $this->replace_merge_tags( $html, $context );

        // Add tracking pixel.
        $pixel_url = add_query_arg( [
            'kdna_fue_track' => 'open',
            'key'            => $queue_item->tracking_key,
        ], home_url( '/' ) );
        $html .= '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" style="display:none;" />';

        // Wrap links for click tracking.
        $html = $this->wrap_links_for_tracking( $html, $queue_item->tracking_key );

        // Headers.
        $from_name  = $this->settings['from_name'] ?: get_option( 'woocommerce_email_from_name' );
        $from_email = $this->settings['from_email'] ?: get_option( 'woocommerce_email_from_address' );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'List-Unsubscribe: <' . $this->get_unsubscribe_url( $to, $email_id ) . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        ];

        // BCC.
        if ( ! empty( $this->settings['bcc'] ) ) {
            $headers[] = 'Bcc: ' . $this->settings['bcc'];
        }

        return wp_mail( $to, $subject, $html, $headers );
    }

    private function build_context( $queue_item ) {
        $context = [
            'email'       => $queue_item->customer_email,
            'coupon_code' => $queue_item->coupon_code,
            'tracking_key'=> $queue_item->tracking_key,
        ];

        if ( $queue_item->customer_id ) {
            $context['customer'] = new \WC_Customer( $queue_item->customer_id );
        } else {
            $user = get_user_by( 'email', $queue_item->customer_email );
            if ( $user ) {
                $context['customer'] = new \WC_Customer( $user->ID );
            }
        }

        if ( $queue_item->order_id ) {
            $context['order'] = wc_get_order( $queue_item->order_id );
        }

        if ( $queue_item->product_id ) {
            $context['product'] = wc_get_product( $queue_item->product_id );
        }

        // Coupon details.
        if ( $queue_item->coupon_code ) {
            $coupon_id = wc_get_coupon_id_by_code( $queue_item->coupon_code );
            if ( $coupon_id ) {
                $context['coupon'] = new \WC_Coupon( $coupon_id );
            }
        }

        return $context;
    }

    // =========================================================================
    // Merge Tags
    // =========================================================================

    private function get_merge_tags_grouped() {
        return [
            __( 'Customer', 'kdna-ecommerce' ) => [
                '{customer_first_name}' => __( 'First Name', 'kdna-ecommerce' ),
                '{customer_last_name}'  => __( 'Last Name', 'kdna-ecommerce' ),
                '{customer_name}'       => __( 'Full Name', 'kdna-ecommerce' ),
                '{customer_email}'      => __( 'Email', 'kdna-ecommerce' ),
            ],
            __( 'Order', 'kdna-ecommerce' ) => [
                '{order_number}'           => __( 'Order Number', 'kdna-ecommerce' ),
                '{order_date}'             => __( 'Order Date', 'kdna-ecommerce' ),
                '{order_total}'            => __( 'Order Total', 'kdna-ecommerce' ),
                '{order_items}'            => __( 'Order Items', 'kdna-ecommerce' ),
                '{order_billing_address}'  => __( 'Billing Address', 'kdna-ecommerce' ),
                '{order_shipping_address}' => __( 'Shipping Address', 'kdna-ecommerce' ),
                '{order_url}'              => __( 'View Order URL', 'kdna-ecommerce' ),
            ],
            __( 'Product', 'kdna-ecommerce' ) => [
                '{product_name}'  => __( 'Product Name', 'kdna-ecommerce' ),
                '{product_price}' => __( 'Product Price', 'kdna-ecommerce' ),
                '{product_url}'   => __( 'Product URL', 'kdna-ecommerce' ),
            ],
            __( 'Coupon', 'kdna-ecommerce' ) => [
                '{coupon_code}'   => __( 'Coupon Code', 'kdna-ecommerce' ),
                '{coupon_amount}' => __( 'Coupon Amount', 'kdna-ecommerce' ),
                '{coupon_expiry}' => __( 'Coupon Expiry', 'kdna-ecommerce' ),
            ],
            __( 'Store', 'kdna-ecommerce' ) => [
                '{store_name}' => __( 'Store Name', 'kdna-ecommerce' ),
                '{store_url}'  => __( 'Store URL', 'kdna-ecommerce' ),
                '{site_title}' => __( 'Site Title', 'kdna-ecommerce' ),
            ],
            __( 'Links', 'kdna-ecommerce' ) => [
                '{unsubscribe_url}'          => __( 'Unsubscribe URL', 'kdna-ecommerce' ),
                '{manage_subscriptions_url}' => __( 'Manage Subscriptions URL', 'kdna-ecommerce' ),
            ],
        ];
    }

    public function replace_merge_tags( $text, $context ) {
        $customer = $context['customer'] ?? null;
        $order    = $context['order'] ?? null;
        $product  = $context['product'] ?? null;
        $coupon   = $context['coupon'] ?? null;
        $email    = $context['email'] ?? '';

        $tags = [
            '{customer_first_name}' => $customer && method_exists( $customer, 'get_first_name' ) ? $customer->get_first_name() : '',
            '{customer_last_name}'  => $customer && method_exists( $customer, 'get_last_name' ) ? $customer->get_last_name() : '',
            '{customer_name}'       => $customer && method_exists( $customer, 'get_first_name' ) ? trim( $customer->get_first_name() . ' ' . $customer->get_last_name() ) : '',
            '{customer_email}'      => $email,

            '{order_number}'           => $order ? $order->get_order_number() : '',
            '{order_date}'             => $order ? wc_format_datetime( $order->get_date_created() ) : '',
            '{order_total}'            => $order ? $order->get_formatted_order_total() : '',
            '{order_items}'            => $order ? $this->format_order_items_html( $order ) : '',
            '{order_billing_address}'  => $order ? $order->get_formatted_billing_address() : '',
            '{order_shipping_address}' => $order ? $order->get_formatted_shipping_address() : '',
            '{order_url}'              => $order ? $order->get_view_order_url() : '',

            '{product_name}'  => $product ? $product->get_name() : '',
            '{product_price}' => $product ? wc_price( $product->get_price() ) : '',
            '{product_url}'   => $product ? $product->get_permalink() : '',

            '{coupon_code}'   => $coupon ? $coupon->get_code() : ( $context['coupon_code'] ?? '' ),
            '{coupon_amount}' => $coupon ? wc_price( $coupon->get_amount() ) : '',
            '{coupon_expiry}' => $coupon && $coupon->get_date_expires() ? wc_format_datetime( $coupon->get_date_expires() ) : '',

            '{store_name}'  => get_bloginfo( 'name' ),
            '{store_url}'   => home_url( '/' ),
            '{site_title}'  => get_bloginfo( 'name' ),

            '{unsubscribe_url}'          => $this->get_unsubscribe_url( $email ),
            '{manage_subscriptions_url}' => wc_get_account_endpoint_url( $this->settings['email_subscriptions_endpoint'] ?: 'email-subscriptions' ),
        ];

        return str_replace( array_keys( $tags ), array_values( $tags ), $text );
    }

    private function format_order_items_html( $order ) {
        $html = '<table style="width:100%;border-collapse:collapse;">';
        foreach ( $order->get_items() as $item ) {
            $html .= '<tr>';
            $html .= '<td style="padding:6px;border-bottom:1px solid #eee;">' . esc_html( $item->get_name() ) . '</td>';
            $html .= '<td style="padding:6px;border-bottom:1px solid #eee;text-align:center;">&times; ' . $item->get_quantity() . '</td>';
            $html .= '<td style="padding:6px;border-bottom:1px solid #eee;text-align:right;">' . wc_price( $item->get_total() ) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }


    // =========================================================================
    // Coupon Generation
    // =========================================================================

    private function generate_coupon( $email_id, $customer_email ) {
        $config = get_post_meta( $email_id, self::META_COUPON_CONFIG, true ) ?: [];

        $prefix      = $config['prefix'] ?? 'FUE-';
        $type        = $config['type'] ?? 'percent';
        $amount      = (float) ( $config['amount'] ?? 10 );
        $expiry_days = absint( $config['expiry_days'] ?? 30 );

        $code = $prefix . strtoupper( wp_generate_password( 8, false ) );

        $coupon = new \WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( $type );
        $coupon->set_amount( $amount );
        $coupon->set_usage_limit( 1 );
        $coupon->set_individual_use( true );
        $coupon->set_email_restrictions( [ $customer_email ] );

        if ( $expiry_days > 0 ) {
            $coupon->set_date_expires( strtotime( '+' . $expiry_days . ' days' ) );
        }

        $coupon->save();

        return $code;
    }

    // =========================================================================
    // Tracking (Opens & Clicks)
    // =========================================================================

    public function add_tracking_endpoints() {
        add_rewrite_rule( '^kdna-fue-click/([^/]+)/(.+)/?', 'index.php?kdna_fue_click=$matches[1]&kdna_fue_url=$matches[2]', 'top' );
        add_filter( 'query_vars', function( $vars ) {
            $vars[] = 'kdna_fue_click';
            $vars[] = 'kdna_fue_url';
            return $vars;
        } );
    }

    public function handle_tracking() {
        // Open tracking.
        if ( isset( $_GET['kdna_fue_track'] ) && $_GET['kdna_fue_track'] === 'open' ) {
            $key = sanitize_text_field( $_GET['key'] ?? '' );
            if ( $key ) {
                $this->record_open( $key );
            }
            // Return 1x1 transparent GIF.
            header( 'Content-Type: image/gif' );
            echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
            exit;
        }

        // Click tracking.
        $click_key = get_query_var( 'kdna_fue_click' );
        if ( $click_key ) {
            $url = urldecode( get_query_var( 'kdna_fue_url', '' ) );
            $this->record_click( $click_key );
            if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
                wp_redirect( $url );
                exit;
            }
            wp_redirect( home_url( '/' ) );
            exit;
        }
    }

    private function record_open( $tracking_key ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LOGS;
        $log   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE tracking_key = %s LIMIT 1", $tracking_key ) );
        if ( $log && ! $log->opened ) {
            $wpdb->update( $table, [
                'opened'    => 1,
                'opened_at' => current_time( 'mysql' ),
            ], [ 'id' => $log->id ] );

            // Update post meta count.
            $opens = (int) get_post_meta( $log->email_id, self::META_OPEN_COUNT, true );
            update_post_meta( $log->email_id, self::META_OPEN_COUNT, $opens + 1 );
        }
    }

    private function record_click( $tracking_key ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LOGS;
        $log   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE tracking_key = %s LIMIT 1", $tracking_key ) );
        if ( $log ) {
            $wpdb->update( $table, [
                'clicked'     => 1,
                'clicked_at'  => $log->clicked_at ?: current_time( 'mysql' ),
                'click_count' => $log->click_count + 1,
            ], [ 'id' => $log->id ] );

            if ( ! $log->clicked ) {
                $clicks = (int) get_post_meta( $log->email_id, self::META_CLICK_COUNT, true );
                update_post_meta( $log->email_id, self::META_CLICK_COUNT, $clicks + 1 );
            }
        }
    }

    private function wrap_links_for_tracking( $html, $tracking_key ) {
        return preg_replace_callback( '/<a\s+([^>]*?)href=["\']([^"\']+)["\']/i', function ( $matches ) use ( $tracking_key ) {
            $url = $matches[2];
            // Don't track unsubscribe or mailto links.
            if ( strpos( $url, 'unsubscribe' ) !== false || strpos( $url, 'mailto:' ) === 0 ) {
                return $matches[0];
            }
            $tracked_url = home_url( '/kdna-fue-click/' . $tracking_key . '/' . urlencode( $url ) );
            return '<a ' . $matches[1] . 'href="' . esc_url( $tracked_url ) . '"';
        }, $html );
    }

    // =========================================================================
    // Logging
    // =========================================================================

    private function log_sent_email( $queue_item ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . self::TABLE_LOGS, [
            'email_id'       => $queue_item->email_id,
            'email_name'     => get_the_title( $queue_item->email_id ),
            'customer_email' => $queue_item->customer_email,
            'customer_id'    => $queue_item->customer_id,
            'order_id'       => $queue_item->order_id,
            'subject'        => $queue_item->subject,
            'tracking_key'   => $queue_item->tracking_key,
            'status'         => 'sent',
        ] );
    }

    // =========================================================================
    // Unsubscribe
    // =========================================================================

    public function add_unsubscribe_endpoint() {
        $endpoint = $this->settings['unsubscribe_endpoint'] ?: 'email-unsubscribe';
        add_rewrite_endpoint( $endpoint, EP_ROOT | EP_PAGES );

        // Handle unsubscribe requests.
        add_action( 'template_redirect', function () use ( $endpoint ) {
            if ( ! isset( $_GET['kdna_fue_unsub'] ) ) return;

            $token = sanitize_text_field( $_GET['kdna_fue_unsub'] );
            $data  = json_decode( base64_decode( $token ), true );
            if ( ! $data || empty( $data['email'] ) ) {
                wp_die( __( 'Invalid unsubscribe link.', 'kdna-ecommerce' ), '', [ 'response' => 400 ] );
            }

            global $wpdb;
            $wpdb->insert( $wpdb->prefix . self::TABLE_UNSUBSCRIBES, [
                'email'      => $data['email'],
                'email_id'   => $data['email_id'] ?? 0,
                'email_type' => $data['type'] ?? 'all',
            ] );

            wp_die(
                __( 'You have been successfully unsubscribed from our emails.', 'kdna-ecommerce' ),
                __( 'Unsubscribed', 'kdna-ecommerce' ),
                [ 'response' => 200 ]
            );
        } );
    }

    private function get_unsubscribe_url( $email, $email_id = 0 ) {
        $token = base64_encode( wp_json_encode( [
            'email'    => $email,
            'email_id' => $email_id,
            'type'     => 'all',
        ] ) );
        return add_query_arg( 'kdna_fue_unsub', $token, home_url( '/' ) );
    }

    private function is_unsubscribed( $email, $email_type = 'all' ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}" . self::TABLE_UNSUBSCRIBES . " WHERE email = %s AND (email_type = 'all' OR email_type = %s) LIMIT 1",
            $email, $email_type
        ) );
    }

    // =========================================================================
    // Exclusions
    // =========================================================================

    private function is_excluded( $email_id, $customer_email, $order = null ) {
        global $wpdb;
        $exclusions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::TABLE_EXCLUSIONS . " WHERE email_id = %d",
            $email_id
        ) );

        foreach ( $exclusions as $exc ) {
            switch ( $exc->exclusion_type ) {
                case 'email':
                    if ( $exc->exclusion_value === $customer_email ) return true;
                    break;
                case 'product':
                    if ( $order ) {
                        foreach ( $order->get_items() as $item ) {
                            if ( $item->get_product_id() == $exc->exclusion_value ) return true;
                        }
                    }
                    break;
                case 'category':
                    if ( $order ) {
                        foreach ( $order->get_items() as $item ) {
                            $terms = wp_get_object_terms( $item->get_product_id(), 'product_cat', [ 'fields' => 'ids' ] );
                            if ( in_array( (int) $exc->exclusion_value, $terms, true ) ) return true;
                        }
                    }
                    break;
            }
        }

        return false;
    }

    // =========================================================================
    // Subscriber Management
    // =========================================================================

    public function render_checkout_subscription() {
        $default = $this->settings['checkout_subscription_default'] === 'checked' ? 'checked' : '';
        $label   = $this->settings['checkout_subscription_field_label'] ?: __( 'Subscribe to our newsletter', 'kdna-ecommerce' );
        ?>
        <p class="form-row kdna-fue-subscribe-field">
            <label class="woocommerce-form__label checkbox">
                <input type="checkbox" name="kdna_fue_subscribe" value="1" <?php echo $default; ?> />
                <span><?php echo esc_html( $label ); ?></span>
            </label>
        </p>
        <?php
    }

    public function save_checkout_subscription( $order_id, $posted_data, $order ) {
        if ( ! empty( $_POST['kdna_fue_subscribe'] ) ) {
            $email = $order->get_billing_email();
            $this->add_subscriber( $email, $order->get_billing_first_name(), $order->get_billing_last_name(), $order->get_customer_id(), 'checkout' );
        }
    }

    private function add_subscriber( $email, $first_name = '', $last_name = '', $user_id = 0, $source = 'manual' ) {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_SUBSCRIBERS;
        $exists  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) );

        if ( $exists ) {
            $wpdb->update( $table, [
                'status'     => 'subscribed',
                'updated_at' => current_time( 'mysql' ),
            ], [ 'id' => $exists ] );
        } else {
            $wpdb->insert( $table, [
                'email'      => $email,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'user_id'    => $user_id,
                'status'     => 'subscribed',
                'source'     => $source,
            ] );
        }
    }


    // =========================================================================
    // Bounce Handling
    // =========================================================================

    public function check_bounces() {
        if ( $this->settings['bounce_handle_bounces'] !== 'yes' ) return;

        $server   = $this->settings['bounce_server'];
        $port     = $this->settings['bounce_port'] ?: '110';
        $username = $this->settings['bounce_username'];
        $password = $this->settings['bounce_password'];
        $use_ssl  = $this->settings['bounce_ssl'] === 'yes';

        if ( empty( $server ) || empty( $username ) ) return;

        $connection_string = '{' . $server . ':' . $port . '/pop3';
        if ( $use_ssl ) {
            $connection_string .= '/ssl/novalidate-cert';
        }
        $connection_string .= '}INBOX';

        if ( ! function_exists( 'imap_open' ) ) return;

        $inbox = @imap_open( $connection_string, $username, $password );
        if ( ! $inbox ) return;

        $emails = imap_search( $inbox, 'UNSEEN' );
        if ( ! $emails ) {
            imap_close( $inbox );
            return;
        }

        global $wpdb;
        $resend_limit = absint( $this->settings['bounce_soft_bounce_resend_limit'] ) ?: 3;

        foreach ( $emails as $email_number ) {
            $header = imap_headerinfo( $inbox, $email_number );
            $body   = imap_fetchbody( $inbox, $email_number, 1 );

            // Determine bounce type.
            $is_hard_bounce = false;
            $is_soft_bounce = false;
            $bounced_email  = '';

            $subject = $header->subject ?? '';
            if ( stripos( $subject, 'Undelivered' ) !== false || stripos( $subject, 'Delivery Status' ) !== false || stripos( $subject, 'failure' ) !== false ) {
                // Try to extract the bounced email.
                if ( preg_match( '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $body, $matches ) ) {
                    $bounced_email = $matches[1];
                }

                // Check for hard bounce indicators.
                if ( stripos( $body, 'does not exist' ) !== false || stripos( $body, 'unknown user' ) !== false || stripos( $body, '550' ) !== false || stripos( $body, '551' ) !== false || stripos( $body, '553' ) !== false ) {
                    $is_hard_bounce = true;
                } else {
                    $is_soft_bounce = true;
                }
            }

            if ( $bounced_email ) {
                // Update logs.
                $log = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}" . self::TABLE_LOGS . " WHERE customer_email = %s AND bounced = 0 ORDER BY sent_at DESC LIMIT 1",
                    $bounced_email
                ) );

                if ( $log ) {
                    $bounce_type = $is_hard_bounce ? 'hard' : 'soft';
                    $wpdb->update( $wpdb->prefix . self::TABLE_LOGS, [
                        'bounced'     => 1,
                        'bounce_type' => $bounce_type,
                    ], [ 'id' => $log->id ] );

                    if ( $is_hard_bounce ) {
                        // Remove from queue and unsubscribe.
                        $wpdb->delete( $wpdb->prefix . self::TABLE_QUEUE, [ 'customer_email' => $bounced_email, 'status' => 'pending' ] );
                        $wpdb->update( $wpdb->prefix . self::TABLE_SUBSCRIBERS, [ 'status' => 'bounced' ], [ 'email' => $bounced_email ] );
                    }
                }
            }

            // Delete processed message if configured.
            if ( $this->settings['bounce_delete_messages'] === 'yes' ) {
                imap_delete( $inbox, $email_number );
            }
        }

        if ( $this->settings['bounce_delete_messages'] === 'yes' ) {
            imap_expunge( $inbox );
        }
        imap_close( $inbox );
    }

    // =========================================================================
    // DKIM Signing
    // =========================================================================

    public function add_dkim_signature( $phpmailer ) {
        if ( $this->settings['dkim_enabled'] !== 'yes' ) return;

        $domain     = $this->settings['dkim_domain'];
        $selector   = $this->settings['dkim_selector'] ?: 'kdna';
        $private_key= $this->settings['dkim_private_key'];
        $identity   = $this->settings['dkim_identity'];
        $passphrase = $this->settings['dkim_passphrase'];

        if ( empty( $domain ) || empty( $private_key ) ) return;

        $phpmailer->DKIM_domain     = $domain;
        $phpmailer->DKIM_selector   = $selector;
        $phpmailer->DKIM_private    = '';
        $phpmailer->DKIM_private_string = $private_key;
        $phpmailer->DKIM_passphrase = $passphrase;
        $phpmailer->DKIM_identity   = $identity ?: $phpmailer->From;
    }

    // =========================================================================
    // Daily Summary
    // =========================================================================

    public function send_daily_summary() {
        if ( $this->settings['enable_daily_summary'] !== 'yes' ) return;

        $recipients = $this->settings['daily_emails'] ?: get_option( 'admin_email' );

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LOGS;
        $since = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );

        $sent   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE sent_at >= %s", $since ) );
        $opened = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE sent_at >= %s AND opened = 1", $since ) );
        $clicked= (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE sent_at >= %s AND clicked = 1", $since ) );
        $bounced= (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE sent_at >= %s AND bounced = 1", $since ) );

        $subject = sprintf( __( '[%s] Emails Daily Summary', 'kdna-ecommerce' ), get_bloginfo( 'name' ) );
        $body    = sprintf(
            __( "Daily Summary for %s\n\nSent: %d\nOpened: %d (%s%%)\nClicked: %d\nBounced: %d", 'kdna-ecommerce' ),
            wp_date( 'F j, Y' ),
            $sent,
            $opened,
            $sent > 0 ? round( ( $opened / $sent ) * 100, 1 ) : 0,
            $clicked,
            $bounced
        );

        wp_mail( $recipients, $subject, $body );
    }

    // =========================================================================
    // Cron Scheduling
    // =========================================================================

    public function schedule_cron_events() {
        if ( ! function_exists( 'as_has_scheduled_action' ) ) return;

        $interval = $this->settings['email_batch_enabled'] === 'yes'
            ? absint( $this->settings['email_batch_interval'] ) ?: 300
            : 60;

        if ( ! as_has_scheduled_action( 'kdna_fue_process_queue' ) ) {
            as_schedule_recurring_action( time(), $interval, 'kdna_fue_process_queue', [], 'kdna-followup-emails' );
        }

        if ( $this->settings['bounce_handle_bounces'] === 'yes' && ! as_has_scheduled_action( 'kdna_fue_check_bounces' ) ) {
            as_schedule_recurring_action( time(), HOUR_IN_SECONDS, 'kdna_fue_check_bounces', [], 'kdna-followup-emails' );
        }

        if ( $this->settings['enable_daily_summary'] === 'yes' && ! as_has_scheduled_action( 'kdna_fue_daily_summary' ) ) {
            as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'kdna_fue_daily_summary', [], 'kdna-followup-emails' );
        }

        if ( ! as_has_scheduled_action( 'kdna_fue_cleanup_logs' ) ) {
            as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'kdna_fue_cleanup_logs', [], 'kdna-followup-emails' );
        }
    }

    public function cleanup_old_logs() {
        global $wpdb;
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}" . self::TABLE_LOGS . " WHERE sent_at < %s",
            $cutoff
        ) );
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    public function ajax_send_test_email() {
        check_ajax_referer( 'kdna_fue_admin', '_wpnonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $to      = sanitize_email( $_POST['email'] ?? '' );

        if ( ! $to || ! is_email( $to ) ) {
            wp_send_json_error( __( 'Invalid email.', 'kdna-ecommerce' ) );
        }

        $subject  = get_post_meta( $post_id, self::META_SUBJECT, true ) ?: '[Test] ' . get_the_title( $post_id );
        $template = get_post_meta( $post_id, self::META_TEMPLATE_ID, true );

        if ( $template && class_exists( 'KDNA_Email_Builder' ) ) {
            $html = KDNA_Email_Builder::compile_template( $template );
        } else {
            $content = get_post_field( 'post_content', $post_id );
            $heading = get_post_meta( $post_id, self::META_HEADING, true );
            $mailer  = WC()->mailer();
            $html    = $mailer->wrap_message( $heading ?: $subject, wpautop( $content ) );
        }

        // Replace merge tags with sample data.
        $context = [
            'email'    => $to,
            'customer' => is_user_logged_in() ? new \WC_Customer( get_current_user_id() ) : null,
        ];
        $html = $this->replace_merge_tags( $html, $context );

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $result  = wp_mail( $to, '[Test] ' . $subject, $html, $headers );

        wp_send_json_success( [ 'sent' => $result ] );
    }

    public function ajax_cancel_queue_item() {
        check_ajax_referer( 'kdna_fue_admin', '_wpnonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );
        $wpdb->update( $wpdb->prefix . self::TABLE_QUEUE, [ 'status' => 'cancelled' ], [ 'id' => $id ] );
        wp_send_json_success();
    }

    public function ajax_reschedule_queue_item() {
        check_ajax_referer( 'kdna_fue_admin', '_wpnonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        $id   = absint( $_POST['id'] ?? 0 );
        $date = sanitize_text_field( $_POST['date'] ?? '' );
        if ( ! $id || ! $date ) wp_send_json_error( 'Missing data' );

        global $wpdb;
        $wpdb->update( $wpdb->prefix . self::TABLE_QUEUE, [ 'scheduled_at' => $date . ':00' ], [ 'id' => $id ] );
        wp_send_json_success();
    }

    public function ajax_delete_subscriber() {
        check_ajax_referer( 'kdna_fue_admin', '_wpnonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . self::TABLE_SUBSCRIBERS, [ 'id' => absint( $_POST['id'] ?? 0 ) ] );
        wp_send_json_success();
    }

    public function ajax_import_subscribers() {
        check_ajax_referer( 'kdna_fue_admin', '_wpnonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        if ( empty( $_FILES['csv_file'] ) ) wp_send_json_error( 'No file uploaded.' );

        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen( $file, 'r' );
        if ( ! $handle ) wp_send_json_error( 'Cannot open file.' );

        $imported = 0;
        $header   = fgetcsv( $handle ); // skip header row.

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $email = sanitize_email( $row[0] ?? '' );
            if ( ! is_email( $email ) ) continue;

            $this->add_subscriber(
                $email,
                sanitize_text_field( $row[1] ?? '' ),
                sanitize_text_field( $row[2] ?? '' ),
                0,
                'import'
            );
            $imported++;
        }
        fclose( $handle );

        wp_send_json_success( [ 'message' => sprintf( __( '%d subscribers imported.', 'kdna-ecommerce' ), $imported ) ] );
    }

    public function ajax_export_subscribers() {
        check_ajax_referer( 'kdna_fue_admin', '_wpnonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();

        global $wpdb;
        $subscribers = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}" . self::TABLE_SUBSCRIBERS . " ORDER BY created_at DESC" );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="subscribers-' . gmdate( 'Y-m-d' ) . '.csv"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, [ 'Email', 'First Name', 'Last Name', 'Status', 'Source', 'Created' ] );

        foreach ( $subscribers as $sub ) {
            fputcsv( $output, [ $sub->email, $sub->first_name, $sub->last_name, $sub->status, $sub->source, $sub->created_at ] );
        }

        fclose( $output );
        exit;
    }

    public function ajax_export_report() {
        check_ajax_referer( 'kdna_fue_admin', '_wpnonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();

        global $wpdb;
        $from = sanitize_text_field( $_GET['from'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) ) );
        $to   = sanitize_text_field( $_GET['to'] ?? gmdate( 'Y-m-d' ) );

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::TABLE_LOGS . " WHERE sent_at BETWEEN %s AND %s ORDER BY sent_at DESC",
            $from . ' 00:00:00', $to . ' 23:59:59'
        ) );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="fue-report-' . $from . '-to-' . $to . '.csv"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, [ 'Email Name', 'Recipient', 'Subject', 'Sent At', 'Opened', 'Clicked', 'Bounced' ] );

        foreach ( $logs as $log ) {
            fputcsv( $output, [ $log->email_name, $log->customer_email, $log->subject, $log->sent_at, $log->opened ? 'Yes' : 'No', $log->clicked ? 'Yes' : 'No', $log->bounced ? 'Yes' : 'No' ] );
        }

        fclose( $output );
        exit;
    }


    public function ajax_resend_to_subscriber() {
        check_ajax_referer( 'kdna_fue_admin', '_wpnonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        global $wpdb;
        $subscriber_id = absint( $_POST['id'] ?? 0 );
        if ( ! $subscriber_id ) {
            wp_send_json_error( __( 'Invalid subscriber ID.', 'kdna-ecommerce' ) );
        }

        // Look up the subscriber.
        $subscriber = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::TABLE_SUBSCRIBERS . " WHERE id = %d",
            $subscriber_id
        ) );

        if ( ! $subscriber ) {
            wp_send_json_error( __( 'Subscriber not found.', 'kdna-ecommerce' ) );
        }

        // Find the most recent sent queue entry for this subscriber to determine the email to resend.
        $last_sent = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::TABLE_QUEUE . " WHERE customer_email = %s AND status = 'sent' ORDER BY sent_at DESC LIMIT 1",
            $subscriber->email
        ) );

        if ( ! $last_sent ) {
            // No previously sent email found; try to find an active follow-up email to send.
            $active_emails = get_posts( [
                'post_type'      => self::CPT,
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [ 'key' => self::META_STATUS, 'value' => 'active' ],
                ],
            ] );

            if ( empty( $active_emails ) ) {
                wp_send_json_error( __( 'No active follow-up email found for this subscriber.', 'kdna-ecommerce' ) );
            }

            $email_id = $active_emails[0]->ID;
        } else {
            $email_id = $last_sent->email_id;
        }

        // Queue a new entry for re-sending.
        $tracking_key = wp_generate_password( 32, false );
        $subject      = get_post_meta( $email_id, self::META_SUBJECT, true ) ?: get_the_title( $email_id );

        // Generate coupon if needed.
        $coupon_code = '';
        if ( get_post_meta( $email_id, self::META_INCLUDE_COUPON, true ) === 'yes' ) {
            $coupon_code = $this->generate_coupon( $email_id, $subscriber->email );
        }

        $wpdb->insert( $wpdb->prefix . self::TABLE_QUEUE, [
            'email_id'       => $email_id,
            'customer_email' => $subscriber->email,
            'customer_id'    => $subscriber->user_id,
            'order_id'       => 0,
            'product_id'     => 0,
            'subject'        => $subject,
            'coupon_code'    => $coupon_code,
            'status'         => 'pending',
            'priority'       => 10,
            'scheduled_at'   => current_time( 'mysql' ),
            'tracking_key'   => $tracking_key,
        ] );

        wp_send_json_success( [
            'message' => sprintf(
                __( 'Email "%s" queued for resending to %s.', 'kdna-ecommerce' ),
                get_the_title( $email_id ),
                $subscriber->email
            ),
        ] );
    }

    // =========================================================================
    // Admin Pages (Queue, Reports, Subscribers)
    // =========================================================================

    public function render_queue_page() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_QUEUE;
        $items = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100" );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Emails Queue', 'kdna-ecommerce' ); ?></h1>
            <table class="wp-list-table widefat fixed striped kdna-fue-queue-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Recipient', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Subject', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'kdna-ecommerce' ); ?></th>
                        <th class="kdna-fue-scheduled-date"><?php esc_html_e( 'Scheduled', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'kdna-ecommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $item ) : ?>
                    <tr>
                        <td><?php echo esc_html( $item->id ); ?></td>
                        <td><?php echo esc_html( get_the_title( $item->email_id ) ); ?></td>
                        <td><?php echo esc_html( $item->customer_email ); ?></td>
                        <td><?php echo esc_html( $item->subject ); ?></td>
                        <td><span class="kdna-fue-queue-status <?php echo esc_attr( $item->status ); ?>"><?php echo esc_html( ucfirst( $item->status ) ); ?></span></td>
                        <td class="kdna-fue-scheduled-date"><?php echo esc_html( $item->scheduled_at ); ?></td>
                        <td>
                            <?php if ( $item->status === 'pending' ) : ?>
                                <a href="#" class="kdna-fue-queue-cancel" data-id="<?php echo esc_attr( $item->id ); ?>"><?php esc_html_e( 'Cancel', 'kdna-ecommerce' ); ?></a> |
                                <a href="#" class="kdna-fue-queue-reschedule" data-id="<?php echo esc_attr( $item->id ); ?>"><?php esc_html_e( 'Reschedule', 'kdna-ecommerce' ); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $items ) ) : ?>
                    <tr><td colspan="7"><?php esc_html_e( 'No queued emails.', 'kdna-ecommerce' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_reports_page() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LOGS;
        $from  = sanitize_text_field( $_GET['report_from'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) ) );
        $to    = sanitize_text_field( $_GET['report_to'] ?? gmdate( 'Y-m-d' ) );

        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_sent,
                SUM(opened) as total_opened,
                SUM(clicked) as total_clicked,
                SUM(bounced) as total_bounced,
                SUM(unsubscribed) as total_unsubscribed,
                SUM(converted) as total_converted
             FROM {$table} WHERE sent_at BETWEEN %s AND %s",
            $from . ' 00:00:00', $to . ' 23:59:59'
        ) );
        ?>
        <div class="wrap kdna-fue-report-page">
            <h1><?php esc_html_e( 'Emails Reports', 'kdna-ecommerce' ); ?></h1>

            <div class="kdna-fue-report-header">
                <div class="kdna-fue-report-date-range">
                    <label><?php esc_html_e( 'From:', 'kdna-ecommerce' ); ?></label>
                    <input type="text" name="report_from" class="kdna-fue-datepicker" value="<?php echo esc_attr( $from ); ?>" />
                    <label><?php esc_html_e( 'To:', 'kdna-ecommerce' ); ?></label>
                    <input type="text" name="report_to" class="kdna-fue-datepicker" value="<?php echo esc_attr( $to ); ?>" />
                    <button type="button" class="button kdna-fue-report-filter"><?php esc_html_e( 'Filter', 'kdna-ecommerce' ); ?></button>
                    <button type="button" class="button kdna-fue-export-report"><?php esc_html_e( 'Export CSV', 'kdna-ecommerce' ); ?></button>
                </div>
            </div>

            <div class="kdna-fue-report-stats">
                <div class="kdna-fue-stat-card">
                    <div class="value"><?php echo (int) $stats->total_sent; ?></div>
                    <div class="label"><?php esc_html_e( 'Sent', 'kdna-ecommerce' ); ?></div>
                </div>
                <div class="kdna-fue-stat-card">
                    <div class="value positive"><?php echo (int) $stats->total_opened; ?></div>
                    <div class="label"><?php esc_html_e( 'Opened', 'kdna-ecommerce' ); ?></div>
                </div>
                <div class="kdna-fue-stat-card">
                    <div class="value positive"><?php echo (int) $stats->total_clicked; ?></div>
                    <div class="label"><?php esc_html_e( 'Clicked', 'kdna-ecommerce' ); ?></div>
                </div>
                <div class="kdna-fue-stat-card">
                    <div class="value negative"><?php echo (int) $stats->total_bounced; ?></div>
                    <div class="label"><?php esc_html_e( 'Bounced', 'kdna-ecommerce' ); ?></div>
                </div>
                <div class="kdna-fue-stat-card">
                    <div class="value negative"><?php echo (int) $stats->total_unsubscribed; ?></div>
                    <div class="label"><?php esc_html_e( 'Unsubscribed', 'kdna-ecommerce' ); ?></div>
                </div>
                <div class="kdna-fue-stat-card">
                    <div class="value positive"><?php echo (int) $stats->total_converted; ?></div>
                    <div class="label"><?php esc_html_e( 'Converted', 'kdna-ecommerce' ); ?></div>
                </div>
            </div>

            <?php
            // Per-email breakdown.
            $email_stats = $wpdb->get_results( $wpdb->prepare(
                "SELECT email_id, email_name,
                    COUNT(*) as sent,
                    SUM(opened) as opens,
                    SUM(clicked) as clicks,
                    SUM(bounced) as bounces
                 FROM {$table}
                 WHERE sent_at BETWEEN %s AND %s
                 GROUP BY email_id
                 ORDER BY sent DESC",
                $from . ' 00:00:00', $to . ' 23:59:59'
            ) );
            ?>

            <h3><?php esc_html_e( 'Per-Email Breakdown', 'kdna-ecommerce' ); ?></h3>
            <table class="wp-list-table widefat fixed striped kdna-fue-email-report-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Email', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Sent', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Opens', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Open Rate', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Clicks', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Bounces', 'kdna-ecommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $email_stats as $es ) : $open_rate = $es->sent > 0 ? round( ( $es->opens / $es->sent ) * 100, 1 ) : 0; ?>
                    <tr>
                        <td><?php echo esc_html( $es->email_name ); ?></td>
                        <td><?php echo (int) $es->sent; ?></td>
                        <td><?php echo (int) $es->opens; ?></td>
                        <td><?php echo $open_rate; ?>% <span class="kdna-fue-bar" style="width:<?php echo min( 100, $open_rate ); ?>px;"></span></td>
                        <td><?php echo (int) $es->clicks; ?></td>
                        <td><?php echo (int) $es->bounces; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_subscribers_page() {
        global $wpdb;
        $table       = $wpdb->prefix . self::TABLE_SUBSCRIBERS;
        $subscribers = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" );
        $total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        ?>
        <div class="wrap kdna-fue-subscribers-page">
            <h1><?php esc_html_e( 'Subscribers', 'kdna-ecommerce' ); ?> <span class="title-count">(<?php echo $total; ?>)</span></h1>

            <div class="kdna-fue-subscriber-actions">
                <button type="button" class="button kdna-fue-toggle-import"><?php esc_html_e( 'Import CSV', 'kdna-ecommerce' ); ?></button>
                <button type="button" class="button kdna-fue-export-subscribers"><?php esc_html_e( 'Export CSV', 'kdna-ecommerce' ); ?></button>
            </div>

            <div class="kdna-fue-import-form">
                <form enctype="multipart/form-data">
                    <div class="form-field">
                        <label><?php esc_html_e( 'CSV File (email, first_name, last_name)', 'kdna-ecommerce' ); ?></label>
                        <input type="file" name="csv_file" accept=".csv" />
                    </div>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'kdna-ecommerce' ); ?></button>
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Email', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Source', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Subscribed', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'kdna-ecommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $subscribers as $sub ) : ?>
                    <tr>
                        <td><?php echo esc_html( $sub->email ); ?></td>
                        <td><?php echo esc_html( trim( $sub->first_name . ' ' . $sub->last_name ) ); ?></td>
                        <td><span class="kdna-fue-status-badge <?php echo esc_attr( $sub->status ); ?>"><?php echo esc_html( ucfirst( $sub->status ) ); ?></span></td>
                        <td><?php echo esc_html( ucfirst( $sub->source ) ); ?></td>
                        <td><?php echo esc_html( $sub->created_at ); ?></td>
                        <td>
                            <a href="#" class="kdna-fue-delete-subscriber" data-id="<?php echo esc_attr( $sub->id ); ?>"><?php esc_html_e( 'Delete', 'kdna-ecommerce' ); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $subscribers ) ) : ?>
                    <tr><td colspan="6"><?php esc_html_e( 'No subscribers.', 'kdna-ecommerce' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // =========================================================================
    // Dashboard Widget
    // =========================================================================

    public function add_dashboard_widget() {
        wp_add_dashboard_widget( 'kdna_fue_dashboard', __( 'Emails', 'kdna-ecommerce' ), [ $this, 'render_dashboard_widget' ] );
    }

    public function render_dashboard_widget() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LOGS;
        $since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

        $sent   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE sent_at >= %s", $since ) );
        $opened = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE sent_at >= %s AND opened = 1", $since ) );
        $queued = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::TABLE_QUEUE . " WHERE status = 'pending'" );
        ?>
        <div class="kdna-fue-dashboard-stats">
            <div class="kdna-fue-dashboard-stat">
                <div class="value"><?php echo $sent; ?></div>
                <div class="label"><?php esc_html_e( 'Sent (7d)', 'kdna-ecommerce' ); ?></div>
            </div>
            <div class="kdna-fue-dashboard-stat">
                <div class="value"><?php echo $opened; ?></div>
                <div class="label"><?php esc_html_e( 'Opened (7d)', 'kdna-ecommerce' ); ?></div>
            </div>
            <div class="kdna-fue-dashboard-stat">
                <div class="value"><?php echo $queued; ?></div>
                <div class="label"><?php esc_html_e( 'In Queue', 'kdna-ecommerce' ); ?></div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // Shortcodes
    // =========================================================================

    public function shortcode_unsubscribe( $atts ) {
        if ( isset( $_GET['kdna_fue_unsub'] ) ) {
            return '<p>' . esc_html__( 'You have been unsubscribed.', 'kdna-ecommerce' ) . '</p>';
        }
        return '<p>' . esc_html__( 'Use the link in your email to unsubscribe.', 'kdna-ecommerce' ) . '</p>';
    }

    public function shortcode_subscriptions( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in to manage your subscriptions.', 'kdna-ecommerce' ) . '</p>';
        }

        $user  = wp_get_current_user();
        $email = $user->user_email;

        global $wpdb;
        $unsubs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::TABLE_UNSUBSCRIBES . " WHERE email = %s",
            $email
        ) );

        $is_unsubbed = ! empty( $unsubs );

        if ( isset( $_POST['kdna_fue_resubscribe'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'kdna_fue_resub' ) ) {
            $wpdb->delete( $wpdb->prefix . self::TABLE_UNSUBSCRIBES, [ 'email' => $email ] );
            $is_unsubbed = false;
        }

        ob_start();
        if ( $is_unsubbed ) {
            echo '<p>' . esc_html__( 'You are currently unsubscribed from our emails.', 'kdna-ecommerce' ) . '</p>';
            echo '<form method="post">';
            wp_nonce_field( 'kdna_fue_resub' );
            echo '<button type="submit" name="kdna_fue_resubscribe" class="button">' . esc_html__( 'Re-subscribe', 'kdna-ecommerce' ) . '</button>';
            echo '</form>';
        } else {
            echo '<p>' . esc_html__( 'You are subscribed to our emails.', 'kdna-ecommerce' ) . '</p>';
            echo '<p><a href="' . esc_url( $this->get_unsubscribe_url( $email ) ) . '">' . esc_html__( 'Unsubscribe', 'kdna-ecommerce' ) . '</a></p>';
        }
        return ob_get_clean();
    }

    public function shortcode_preferences( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in to manage your preferences.', 'kdna-ecommerce' ) . '</p>';
        }
        return $this->shortcode_subscriptions( $atts );
    }

    // =========================================================================
    // REST API
    // =========================================================================

    public function register_rest_routes() {
        register_rest_route( 'kdna-fue/v1', '/emails', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_emails' ],
            'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
        ] );
        register_rest_route( 'kdna-fue/v1', '/queue', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_queue' ],
            'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
        ] );
        register_rest_route( 'kdna-fue/v1', '/subscribers', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_subscribers' ],
            'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
        ] );
        register_rest_route( 'kdna-fue/v1', '/reports', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_reports' ],
            'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
        ] );
    }

    public function rest_get_emails( $request ) {
        $posts = get_posts( [ 'post_type' => self::CPT, 'posts_per_page' => -1, 'post_status' => 'publish' ] );
        $data  = [];
        foreach ( $posts as $p ) {
            $data[] = [
                'id'        => $p->ID,
                'title'     => $p->post_title,
                'type'      => get_post_meta( $p->ID, self::META_TYPE, true ),
                'status'    => get_post_meta( $p->ID, self::META_STATUS, true ),
                'sent'      => (int) get_post_meta( $p->ID, self::META_SENT_COUNT, true ),
                'open_rate' => ( (int) get_post_meta( $p->ID, self::META_SENT_COUNT, true ) > 0 )
                    ? round( ( (int) get_post_meta( $p->ID, self::META_OPEN_COUNT, true ) / (int) get_post_meta( $p->ID, self::META_SENT_COUNT, true ) ) * 100, 1 )
                    : 0,
            ];
        }
        return rest_ensure_response( $data );
    }

    public function rest_get_queue( $request ) {
        global $wpdb;
        return rest_ensure_response( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}" . self::TABLE_QUEUE . " ORDER BY created_at DESC LIMIT 100" ) );
    }

    public function rest_get_subscribers( $request ) {
        global $wpdb;
        return rest_ensure_response( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}" . self::TABLE_SUBSCRIBERS . " ORDER BY created_at DESC LIMIT 200" ) );
    }

    public function rest_get_reports( $request ) {
        global $wpdb;
        $from = sanitize_text_field( $request->get_param( 'from' ) ?: gmdate( 'Y-m-d', strtotime( '-30 days' ) ) );
        $to   = sanitize_text_field( $request->get_param( 'to' ) ?: gmdate( 'Y-m-d' ) );

        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as sent, SUM(opened) as opened, SUM(clicked) as clicked, SUM(bounced) as bounced, SUM(unsubscribed) as unsubscribed, SUM(converted) as converted FROM {$wpdb->prefix}" . self::TABLE_LOGS . " WHERE sent_at BETWEEN %s AND %s",
            $from . ' 00:00:00', $to . ' 23:59:59'
        ) );

        return rest_ensure_response( $stats );
    }
}
