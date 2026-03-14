<?php
/**
 * KDNA AutomateWoo Module
 *
 * Full workflow automation engine: triggers → rules → actions with queue/scheduling,
 * abandoned cart tracking, marketing opt-in/out, guest tracking, conversion tracking,
 * email/SMS sending, and third-party integrations (Twilio, Mailchimp, ActiveCampaign,
 * Campaign Monitor, Bitly).
 */
defined( 'ABSPATH' ) || exit;

class KDNA_AutomateWoo {

    // Custom post type.
    const CPT = 'kdna_aw_workflow';

    // Table names (without prefix).
    const TABLE_GUESTS      = 'kdna_aw_guests';
    const TABLE_QUEUE       = 'kdna_aw_queue';
    const TABLE_LOGS        = 'kdna_aw_logs';
    const TABLE_CARTS       = 'kdna_aw_abandoned_carts';
    const TABLE_UNSUBSCRIBES = 'kdna_aw_unsubscribes';
    const TABLE_CONVERSIONS = 'kdna_aw_conversions';

    // Meta keys.
    const META_TRIGGER         = '_kdna_aw_trigger';
    const META_TRIGGER_OPTIONS = '_kdna_aw_trigger_options';
    const META_RULES           = '_kdna_aw_rules';
    const META_ACTIONS         = '_kdna_aw_actions';
    const META_TIMING          = '_kdna_aw_timing';
    const META_STATUS          = '_kdna_aw_status';
    const META_RUN_COUNT       = '_kdna_aw_run_count';

    // Cookie names.
    const COOKIE_GUEST   = 'kdna_aw_guest';
    const COOKIE_CART    = 'kdna_aw_cart_token';
    const COOKIE_SESSION = 'kdna_aw_session';

    private $settings;

    public function __construct() {
        $this->settings = self::get_settings();

        // Register CPT.
        add_action( 'init', [ $this, 'register_post_type' ] );

        // Admin.
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
            add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
            add_action( 'save_post_' . self::CPT, [ $this, 'save_workflow' ], 10, 2 );
            add_filter( 'manage_' . self::CPT . '_posts_columns', [ $this, 'admin_columns' ] );
            add_action( 'manage_' . self::CPT . '_posts_custom_column', [ $this, 'admin_column_content' ], 10, 2 );
            add_action( 'admin_menu', [ $this, 'add_admin_pages' ] );
        }

        // Hook triggers.
        $this->register_trigger_hooks();

        // Abandoned cart tracking.
        if ( $this->settings['abandoned_cart_enabled'] === 'yes' ) {
            add_action( 'woocommerce_cart_updated', [ $this, 'track_cart' ] );
            add_action( 'woocommerce_add_to_cart', [ $this, 'track_cart' ] );
            add_action( 'woocommerce_thankyou', [ $this, 'clear_cart_on_order' ] );
            add_action( 'wp_login', [ $this, 'link_guest_cart_to_user' ], 10, 2 );
        }

        // Marketing opt-in.
        if ( $this->settings['enable_checkout_optin'] === 'yes' ) {
            add_action( 'woocommerce_review_order_before_submit', [ $this, 'render_checkout_optin' ] );
            add_action( 'woocommerce_checkout_order_processed', [ $this, 'save_checkout_optin' ], 10, 3 );
        }

        if ( $this->settings['enable_account_signup_optin'] === 'yes' ) {
            add_action( 'woocommerce_register_form', [ $this, 'render_registration_optin' ] );
            add_action( 'woocommerce_created_customer', [ $this, 'save_registration_optin' ], 10, 3 );
        }

        // Guest email capture.
        if ( $this->settings['enable_presubmit_data_capture'] === 'yes' ) {
            add_action( 'wp_ajax_nopriv_kdna_aw_capture_email', [ $this, 'ajax_capture_guest_email' ] );
            add_action( 'wp_ajax_kdna_aw_capture_email', [ $this, 'ajax_capture_guest_email' ] );
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_capture_script' ] );
        }

        // Communication preferences.
        if ( $this->settings['enable_communication_account_tab'] === 'yes' ) {
            add_action( 'init', [ $this, 'add_communication_endpoint' ] );
            add_filter( 'woocommerce_account_menu_items', [ $this, 'add_communication_menu_item' ] );
            add_action( 'woocommerce_account_communication-preferences_endpoint', [ $this, 'render_communication_page' ] );
        }

        // Cron jobs via Action Scheduler.
        add_action( 'kdna_aw_process_queue', [ $this, 'process_queue' ] );
        add_action( 'kdna_aw_check_abandoned_carts', [ $this, 'check_abandoned_carts' ] );
        add_action( 'kdna_aw_clean_inactive_carts', [ $this, 'clean_inactive_carts' ] );
        add_action( 'kdna_aw_clean_expired_coupons', [ $this, 'clean_expired_coupons' ] );
        add_action( 'init', [ $this, 'schedule_cron_events' ] );

        // Unsubscribe endpoint.
        add_action( 'init', [ $this, 'add_unsubscribe_endpoint' ] );
        add_action( 'template_redirect', [ $this, 'handle_unsubscribe' ] );

        // Conversion tracking.
        add_action( 'woocommerce_thankyou', [ $this, 'track_conversion' ] );

        // AJAX handlers.
        add_action( 'wp_ajax_kdna_aw_cancel_queue_item', [ $this, 'ajax_cancel_queue_item' ] );
        add_action( 'wp_ajax_kdna_aw_delete_cart', [ $this, 'ajax_delete_cart' ] );

        // Shortcodes.
        add_shortcode( 'kdna_aw_communication_preferences', [ $this, 'shortcode_communication_preferences' ] );
        add_shortcode( 'kdna_aw_cart_recovery', [ $this, 'shortcode_cart_recovery' ] );

        // REST API.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    // =========================================================================
    // Settings
    // =========================================================================

    public static function get_settings() {
        return wp_parse_args(
            get_option( 'kdna_automatewoo_settings', [] ),
            self::get_default_settings()
        );
    }

    public static function get_default_settings() {
        return [
            // General.
            'enable_checkout_optin'                     => 'no',
            'enable_account_signup_optin'                => 'no',
            'optin_mode'                                => 'ask',
            'optin_checkbox_text'                       => 'Yes, I want to receive marketing emails',
            'guest_email_capture_scope'                 => 'checkout_only',
            'session_tracking_enabled'                  => 'no',
            'session_tracking_requires_cookie_consent'  => 'no',
            'session_tracking_consent_cookie_name'      => '',
            'enable_presubmit_data_capture'             => 'no',
            'enable_communication_account_tab'          => 'no',
            'communication_preferences_page_id'         => 0,
            'communication_signup_page_id'              => 0,
            'communication_page_legal_text'             => '',
            'clean_expired_coupons'                     => 'no',
            'conversion_window'                         => 90,
            'email_from_name'                           => '',
            'email_from_address'                        => '',

            // Carts.
            'abandoned_cart_enabled'                    => 'no',
            'abandoned_cart_timeout'                    => 15,
            'abandoned_cart_includes_pending_orders'    => 'no',
            'clear_inactive_carts_after'               => 60,

            // Twilio SMS.
            'twilio_enabled'                           => 'no',
            'twilio_from'                              => '',
            'twilio_auth_id'                           => '',
            'twilio_auth_token'                        => '',

            // Mailchimp.
            'mailchimp_api_key'                        => '',

            // ActiveCampaign.
            'activecampaign_api_url'                   => '',
            'activecampaign_api_key'                   => '',

            // Campaign Monitor.
            'campaign_monitor_api_key'                 => '',
            'campaign_monitor_client_id'               => '',

            // Bitly.
            'bitly_access_token'                       => '',
        ];
    }


    // =========================================================================
    // Installation
    // =========================================================================

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $tables = [];

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_GUESTS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            first_name varchar(100) DEFAULT '',
            last_name varchar(100) DEFAULT '',
            cookie_token varchar(64) DEFAULT '',
            language varchar(10) DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_active datetime DEFAULT CURRENT_TIMESTAMP,
            opted_in tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY cookie_token (cookie_token)
        ) $charset;";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_QUEUE . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            workflow_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED DEFAULT 0,
            guest_id bigint(20) UNSIGNED DEFAULT 0,
            order_id bigint(20) UNSIGNED DEFAULT 0,
            data_layer longtext,
            status varchar(20) DEFAULT 'pending',
            scheduled_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            failure_message text DEFAULT NULL,
            attempt_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY workflow_id (workflow_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY customer_id (customer_id)
        ) $charset;";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_LOGS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            workflow_id bigint(20) UNSIGNED NOT NULL,
            workflow_name varchar(255) DEFAULT '',
            customer_id bigint(20) UNSIGNED DEFAULT 0,
            guest_id bigint(20) UNSIGNED DEFAULT 0,
            order_id bigint(20) UNSIGNED DEFAULT 0,
            trigger_name varchar(100) DEFAULT '',
            actions_run text,
            status varchar(20) DEFAULT 'completed',
            tracking_key varchar(64) DEFAULT '',
            data_snapshot longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY workflow_id (workflow_id),
            KEY customer_id (customer_id),
            KEY tracking_key (tracking_key),
            KEY created_at (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_CARTS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT 0,
            guest_id bigint(20) UNSIGNED DEFAULT 0,
            email varchar(255) DEFAULT '',
            cart_token varchar(64) NOT NULL,
            cart_data longtext,
            cart_total decimal(12,2) DEFAULT 0,
            item_count int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            currency varchar(3) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            abandoned_at datetime DEFAULT NULL,
            recovered_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY cart_token (cart_token),
            KEY user_id (user_id),
            KEY guest_id (guest_id),
            KEY status (status),
            KEY email (email)
        ) $charset;";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_UNSUBSCRIBES . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT 0,
            workflow_id bigint(20) UNSIGNED DEFAULT 0,
            type varchar(20) DEFAULT 'all',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY user_id (user_id)
        ) $charset;";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . self::TABLE_CONVERSIONS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            workflow_id bigint(20) UNSIGNED NOT NULL,
            log_id bigint(20) UNSIGNED DEFAULT 0,
            order_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED DEFAULT 0,
            order_total decimal(12,2) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY workflow_id (workflow_id),
            KEY order_id (order_id)
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
                'name'               => __( 'Workflows', 'kdna-ecommerce' ),
                'singular_name'      => __( 'Workflow', 'kdna-ecommerce' ),
                'add_new'            => __( 'Add Workflow', 'kdna-ecommerce' ),
                'add_new_item'       => __( 'Add New Workflow', 'kdna-ecommerce' ),
                'edit_item'          => __( 'Edit Workflow', 'kdna-ecommerce' ),
                'new_item'           => __( 'New Workflow', 'kdna-ecommerce' ),
                'view_item'          => __( 'View Workflow', 'kdna-ecommerce' ),
                'search_items'       => __( 'Search Workflows', 'kdna-ecommerce' ),
                'not_found'          => __( 'No workflows found.', 'kdna-ecommerce' ),
                'not_found_in_trash' => __( 'No workflows in trash.', 'kdna-ecommerce' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'supports'     => [ 'title' ],
            'capability_type' => 'post',
        ] );
    }


    // =========================================================================
    // Admin Interface
    // =========================================================================

    public function enqueue_admin_assets( $hook ) {
        global $post_type;
        if ( $post_type !== self::CPT && strpos( $hook, 'kdna-aw-' ) === false ) {
            return;
        }
        wp_enqueue_style( 'kdna-automatewoo-admin', KDNA_ECOMMERCE_URL . 'modules/automatewoo/assets/automatewoo-admin.css', [], KDNA_ECOMMERCE_VERSION );
        wp_enqueue_script( 'kdna-automatewoo-admin', KDNA_ECOMMERCE_URL . 'modules/automatewoo/assets/automatewoo-admin.js', [ 'jquery', 'jquery-ui-sortable', 'wp-util' ], KDNA_ECOMMERCE_VERSION, true );

        $workflow_data = null;
        if ( isset( $_GET['post'] ) ) {
            $post_id = absint( $_GET['post'] );
            $workflow_data = [
                'trigger'         => get_post_meta( $post_id, self::META_TRIGGER, true ),
                'trigger_options' => get_post_meta( $post_id, self::META_TRIGGER_OPTIONS, true ) ?: [],
                'rules'           => get_post_meta( $post_id, self::META_RULES, true ) ?: [],
                'actions'         => get_post_meta( $post_id, self::META_ACTIONS, true ) ?: [],
                'timing'          => get_post_meta( $post_id, self::META_TIMING, true ) ?: [ 'type' => 'immediate' ],
            ];
        }

        wp_localize_script( 'kdna-automatewoo-admin', 'kdnaAW', [
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'kdna_aw_admin' ),
            'workflow'       => $workflow_data,
            'triggers'       => $this->get_registered_triggers(),
            'rules'          => $this->get_registered_rules_grouped(),
            'actions'        => $this->get_registered_actions_grouped(),
            'actionFields'   => $this->get_action_fields(),
            'compares'       => $this->get_compare_types(),
            'variables'      => $this->get_variables_grouped(),
            'emailTemplates' => $this->get_email_templates(),
            'i18n'           => [
                'or'              => __( 'OR', 'kdna-ecommerce' ),
                'rule_group'      => __( 'Rule Group', 'kdna-ecommerce' ),
                'add_rule'        => __( 'Add Rule', 'kdna-ecommerce' ),
                'select_rule'     => __( '— Select Rule —', 'kdna-ecommerce' ),
                'action'          => __( 'Action', 'kdna-ecommerce' ),
                'action_type'     => __( 'Action Type', 'kdna-ecommerce' ),
                'select_action'   => __( '— Select Action —', 'kdna-ecommerce' ),
                'insert_variable' => __( 'Insert Variable', 'kdna-ecommerce' ),
                'no_template'     => __( '— No Template —', 'kdna-ecommerce' ),
                'confirm_cancel'  => __( 'Cancel this queued event?', 'kdna-ecommerce' ),
                'confirm_delete'  => __( 'Delete this cart?', 'kdna-ecommerce' ),
            ],
        ] );
    }

    public function add_meta_boxes() {
        add_meta_box( 'kdna-aw-workflow-editor', __( 'Workflow Configuration', 'kdna-ecommerce' ), [ $this, 'render_workflow_editor' ], self::CPT, 'normal', 'high' );
        add_meta_box( 'kdna-aw-workflow-status', __( 'Status', 'kdna-ecommerce' ), [ $this, 'render_status_box' ], self::CPT, 'side', 'high' );
    }

    public function render_status_box( $post ) {
        $status = get_post_meta( $post->ID, self::META_STATUS, true ) ?: 'disabled';
        $runs   = (int) get_post_meta( $post->ID, self::META_RUN_COUNT, true );
        wp_nonce_field( 'kdna_aw_save_workflow', 'kdna_aw_nonce' );
        ?>
        <p>
            <label for="kdna_aw_status"><strong><?php esc_html_e( 'Workflow Status', 'kdna-ecommerce' ); ?></strong></label><br>
            <select name="kdna_aw_status" id="kdna_aw_status" style="width:100%;margin-top:4px;">
                <option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'kdna-ecommerce' ); ?></option>
                <option value="disabled" <?php selected( $status, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'kdna-ecommerce' ); ?></option>
            </select>
        </p>
        <p><?php printf( esc_html__( 'Total Runs: %d', 'kdna-ecommerce' ), $runs ); ?></p>
        <?php
    }

    public function render_workflow_editor( $post ) {
        $trigger = get_post_meta( $post->ID, self::META_TRIGGER, true );
        $timing  = get_post_meta( $post->ID, self::META_TIMING, true ) ?: [ 'type' => 'immediate' ];
        ?>
        <div id="kdna-aw-workflow-editor">
            <!-- Trigger Section -->
            <div class="kdna-aw-editor-section">
                <div class="kdna-aw-editor-section-header"><h3><?php esc_html_e( 'Trigger', 'kdna-ecommerce' ); ?></h3></div>
                <div class="kdna-aw-editor-section-body">
                    <select class="kdna-aw-trigger-select" name="workflow_trigger">
                        <option value=""><?php esc_html_e( '— Select Trigger —', 'kdna-ecommerce' ); ?></option>
                        <?php foreach ( $this->get_registered_triggers() as $key => $config ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $trigger, $key ); ?>><?php echo esc_html( $config['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="kdna-aw-trigger-options"></div>
                </div>
            </div>

            <!-- Rules Section -->
            <div class="kdna-aw-editor-section">
                <div class="kdna-aw-editor-section-header">
                    <h3><?php esc_html_e( 'Rules', 'kdna-ecommerce' ); ?></h3>
                    <button type="button" class="button kdna-aw-add-rule-group"><?php esc_html_e( 'Add Rule Group (OR)', 'kdna-ecommerce' ); ?></button>
                </div>
                <div class="kdna-aw-editor-section-body">
                    <div class="kdna-aw-rules-wrap"></div>
                </div>
            </div>

            <!-- Actions Section -->
            <div class="kdna-aw-editor-section">
                <div class="kdna-aw-editor-section-header">
                    <h3><?php esc_html_e( 'Actions', 'kdna-ecommerce' ); ?></h3>
                    <button type="button" class="button kdna-aw-add-action"><?php esc_html_e( 'Add Action', 'kdna-ecommerce' ); ?></button>
                </div>
                <div class="kdna-aw-editor-section-body">
                    <div class="kdna-aw-actions-wrap"></div>
                </div>
            </div>

            <!-- Timing Section -->
            <div class="kdna-aw-editor-section">
                <div class="kdna-aw-editor-section-header"><h3><?php esc_html_e( 'Timing', 'kdna-ecommerce' ); ?></h3></div>
                <div class="kdna-aw-editor-section-body">
                    <div class="kdna-aw-timing-row">
                        <select class="kdna-aw-timing-type" name="workflow_timing[type]">
                            <option value="immediate" <?php selected( $timing['type'] ?? '', 'immediate' ); ?>><?php esc_html_e( 'Run immediately', 'kdna-ecommerce' ); ?></option>
                            <option value="delayed" <?php selected( $timing['type'] ?? '', 'delayed' ); ?>><?php esc_html_e( 'Delayed', 'kdna-ecommerce' ); ?></option>
                            <option value="scheduled" <?php selected( $timing['type'] ?? '', 'scheduled' ); ?>><?php esc_html_e( 'Scheduled', 'kdna-ecommerce' ); ?></option>
                        </select>
                        <span class="kdna-aw-timing-delay-wrap" <?php echo ( $timing['type'] ?? '' ) !== 'delayed' ? 'style="display:none;"' : ''; ?>>
                            <input type="number" class="kdna-aw-timing-delay" name="workflow_timing[delay]" value="<?php echo esc_attr( $timing['delay'] ?? '1' ); ?>" min="1" style="width:80px;" />
                            <select class="kdna-aw-timing-unit" name="workflow_timing[unit]">
                                <option value="minutes" <?php selected( $timing['unit'] ?? '', 'minutes' ); ?>><?php esc_html_e( 'Minutes', 'kdna-ecommerce' ); ?></option>
                                <option value="hours" <?php selected( $timing['unit'] ?? '', 'hours' ); ?>><?php esc_html_e( 'Hours', 'kdna-ecommerce' ); ?></option>
                                <option value="days" <?php selected( $timing['unit'] ?? '', 'days' ); ?>><?php esc_html_e( 'Days', 'kdna-ecommerce' ); ?></option>
                                <option value="weeks" <?php selected( $timing['unit'] ?? '', 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'kdna-ecommerce' ); ?></option>
                            </select>
                        </span>
                    </div>
                    <div class="kdna-aw-timing-scheduled <?php echo ( $timing['type'] ?? '' ) === 'scheduled' ? 'visible' : ''; ?>">
                        <label><?php esc_html_e( 'Time:', 'kdna-ecommerce' ); ?></label>
                        <input type="time" name="workflow_timing[scheduled_time]" value="<?php echo esc_attr( $timing['scheduled_time'] ?? '09:00' ); ?>" />
                        <label><?php esc_html_e( 'Day:', 'kdna-ecommerce' ); ?></label>
                        <select name="workflow_timing[scheduled_day]">
                            <option value="" <?php selected( $timing['scheduled_day'] ?? '', '' ); ?>><?php esc_html_e( 'Every day', 'kdna-ecommerce' ); ?></option>
                            <?php for ( $d = 0; $d < 7; $d++ ) : ?>
                                <option value="<?php echo $d; ?>" <?php selected( $timing['scheduled_day'] ?? '', (string) $d ); ?>><?php echo esc_html( date_i18n( 'l', strtotime( "Sunday +{$d} days" ) ) ); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function save_workflow( $post_id, $post ) {
        if ( ! isset( $_POST['kdna_aw_nonce'] ) || ! wp_verify_nonce( $_POST['kdna_aw_nonce'], 'kdna_aw_save_workflow' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        update_post_meta( $post_id, self::META_STATUS, sanitize_text_field( $_POST['kdna_aw_status'] ?? 'disabled' ) );
        update_post_meta( $post_id, self::META_TRIGGER, sanitize_text_field( $_POST['workflow_trigger'] ?? '' ) );
        update_post_meta( $post_id, self::META_TRIGGER_OPTIONS, array_map( 'sanitize_text_field', $_POST['workflow_trigger_options'] ?? [] ) );
        update_post_meta( $post_id, self::META_TIMING, $this->sanitize_timing( $_POST['workflow_timing'] ?? [] ) );

        // Rules (nested array).
        $rules = [];
        if ( ! empty( $_POST['workflow_rules'] ) && is_array( $_POST['workflow_rules'] ) ) {
            foreach ( $_POST['workflow_rules'] as $group ) {
                $group_rules = [];
                if ( is_array( $group ) ) {
                    foreach ( $group as $rule ) {
                        if ( ! empty( $rule['name'] ) ) {
                            $group_rules[] = [
                                'name'    => sanitize_text_field( $rule['name'] ),
                                'compare' => sanitize_text_field( $rule['compare'] ?? 'is' ),
                                'value'   => sanitize_text_field( $rule['value'] ?? '' ),
                            ];
                        }
                    }
                }
                if ( ! empty( $group_rules ) ) {
                    $rules[] = $group_rules;
                }
            }
        }
        update_post_meta( $post_id, self::META_RULES, $rules );

        // Actions.
        $actions = [];
        if ( ! empty( $_POST['workflow_actions'] ) && is_array( $_POST['workflow_actions'] ) ) {
            foreach ( $_POST['workflow_actions'] as $action ) {
                if ( ! empty( $action['type'] ) ) {
                    $sanitized = [ 'type' => sanitize_text_field( $action['type'] ) ];
                    unset( $action['type'] );
                    foreach ( $action as $k => $v ) {
                        $sanitized[ sanitize_key( $k ) ] = wp_kses_post( $v );
                    }
                    $actions[] = $sanitized;
                }
            }
        }
        update_post_meta( $post_id, self::META_ACTIONS, $actions );
    }

    private function sanitize_timing( $timing ) {
        return [
            'type'           => sanitize_text_field( $timing['type'] ?? 'immediate' ),
            'delay'          => absint( $timing['delay'] ?? 1 ),
            'unit'           => sanitize_text_field( $timing['unit'] ?? 'hours' ),
            'scheduled_time' => sanitize_text_field( $timing['scheduled_time'] ?? '09:00' ),
            'scheduled_day'  => sanitize_text_field( $timing['scheduled_day'] ?? '' ),
        ];
    }

    public function admin_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['workflow_status']  = __( 'Status', 'kdna-ecommerce' );
                $new['workflow_trigger'] = __( 'Trigger', 'kdna-ecommerce' );
                $new['workflow_runs']    = __( 'Runs', 'kdna-ecommerce' );
            }
        }
        unset( $new['date'] );
        return $new;
    }

    public function admin_column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'workflow_status':
                $status = get_post_meta( $post_id, self::META_STATUS, true ) ?: 'disabled';
                echo '<span class="kdna-aw-status-badge ' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
                break;
            case 'workflow_trigger':
                $trigger  = get_post_meta( $post_id, self::META_TRIGGER, true );
                $triggers = $this->get_registered_triggers();
                echo esc_html( $triggers[ $trigger ]['label'] ?? $trigger );
                break;
            case 'workflow_runs':
                echo (int) get_post_meta( $post_id, self::META_RUN_COUNT, true );
                break;
        }
    }

    public function add_admin_pages() {
        $parent = 'edit.php?post_type=' . self::CPT;
        add_submenu_page( 'kdna-ecommerce', __( 'Workflows', 'kdna-ecommerce' ), __( 'Workflows', 'kdna-ecommerce' ), 'manage_woocommerce', 'edit.php?post_type=' . self::CPT );
        add_submenu_page( 'kdna-ecommerce', __( 'AW Queue', 'kdna-ecommerce' ), __( 'AW Queue', 'kdna-ecommerce' ), 'manage_woocommerce', 'kdna-aw-queue', [ $this, 'render_queue_page' ] );
        add_submenu_page( 'kdna-ecommerce', __( 'AW Logs', 'kdna-ecommerce' ), __( 'AW Logs', 'kdna-ecommerce' ), 'manage_woocommerce', 'kdna-aw-logs', [ $this, 'render_logs_page' ] );
        add_submenu_page( 'kdna-ecommerce', __( 'Abandoned Carts', 'kdna-ecommerce' ), __( 'Abandoned Carts', 'kdna-ecommerce' ), 'manage_woocommerce', 'kdna-aw-carts', [ $this, 'render_carts_page' ] );
    }


    // =========================================================================
    // Triggers Registration
    // =========================================================================

    private function get_registered_triggers() {
        return [
            // Order triggers.
            'order_created'           => [ 'label' => __( 'Order Created', 'kdna-ecommerce' ), 'hook' => 'woocommerce_new_order', 'data_items' => [ 'order', 'customer' ] ],
            'order_status_changed'    => [ 'label' => __( 'Order Status Changed', 'kdna-ecommerce' ), 'hook' => 'woocommerce_order_status_changed', 'data_items' => [ 'order', 'customer' ], 'options' => [
                [ 'name' => 'from_status', 'label' => __( 'From Status', 'kdna-ecommerce' ), 'type' => 'multiselect', 'choices' => $this->get_order_status_choices() ],
                [ 'name' => 'to_status', 'label' => __( 'To Status', 'kdna-ecommerce' ), 'type' => 'multiselect', 'choices' => $this->get_order_status_choices() ],
            ] ],
            'order_completed'         => [ 'label' => __( 'Order Completed', 'kdna-ecommerce' ), 'hook' => 'woocommerce_order_status_completed', 'data_items' => [ 'order', 'customer' ] ],
            'order_paid'              => [ 'label' => __( 'Order Paid', 'kdna-ecommerce' ), 'hook' => 'woocommerce_payment_complete', 'data_items' => [ 'order', 'customer' ] ],
            'order_pending'           => [ 'label' => __( 'Order Pending', 'kdna-ecommerce' ), 'hook' => 'woocommerce_order_status_pending', 'data_items' => [ 'order', 'customer' ] ],
            'order_processing'        => [ 'label' => __( 'Order Processing', 'kdna-ecommerce' ), 'hook' => 'woocommerce_order_status_processing', 'data_items' => [ 'order', 'customer' ] ],
            'order_on_hold'           => [ 'label' => __( 'Order On Hold', 'kdna-ecommerce' ), 'hook' => 'woocommerce_order_status_on-hold', 'data_items' => [ 'order', 'customer' ] ],
            'order_cancelled'         => [ 'label' => __( 'Order Cancelled', 'kdna-ecommerce' ), 'hook' => 'woocommerce_order_status_cancelled', 'data_items' => [ 'order', 'customer' ] ],
            'order_refunded'          => [ 'label' => __( 'Order Refunded', 'kdna-ecommerce' ), 'hook' => 'woocommerce_order_status_refunded', 'data_items' => [ 'order', 'customer' ] ],
            'order_note_added'        => [ 'label' => __( 'Order Note Added', 'kdna-ecommerce' ), 'hook' => 'woocommerce_new_customer_note', 'data_items' => [ 'order', 'customer' ] ],

            // Customer triggers.
            'user_registered'         => [ 'label' => __( 'User Registered', 'kdna-ecommerce' ), 'hook' => 'user_register', 'data_items' => [ 'customer' ] ],
            'customer_opted_in'       => [ 'label' => __( 'Customer Opted In', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_customer_opted_in', 'data_items' => [ 'customer' ] ],
            'customer_opted_out'      => [ 'label' => __( 'Customer Opted Out', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_customer_opted_out', 'data_items' => [ 'customer' ] ],
            'customer_win_back'       => [ 'label' => __( 'Customer Win-Back', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_customer_win_back', 'data_items' => [ 'customer' ], 'options' => [
                [ 'name' => 'days_since_purchase', 'label' => __( 'Days Since Last Purchase', 'kdna-ecommerce' ), 'type' => 'number' ],
            ] ],
            'customer_before_saved_card_expiry' => [ 'label' => __( 'Before Saved Card Expiry', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_card_expiry', 'data_items' => [ 'customer' ], 'options' => [
                [ 'name' => 'days_before', 'label' => __( 'Days Before Expiry', 'kdna-ecommerce' ), 'type' => 'number' ],
            ] ],
            'customer_account_updated'=> [ 'label' => __( 'Customer Account Updated', 'kdna-ecommerce' ), 'hook' => 'profile_update', 'data_items' => [ 'customer' ] ],
            'customer_review_posted'  => [ 'label' => __( 'Customer Review Posted', 'kdna-ecommerce' ), 'hook' => 'comment_post', 'data_items' => [ 'customer', 'review' ] ],
            'customer_purchases_above_value' => [ 'label' => __( 'Customer Total Purchases Above Value', 'kdna-ecommerce' ), 'hook' => 'woocommerce_payment_complete', 'data_items' => [ 'customer', 'order' ], 'options' => [
                [ 'name' => 'threshold', 'label' => __( 'Threshold Amount', 'kdna-ecommerce' ), 'type' => 'number' ],
            ] ],

            // Cart triggers.
            'cart_abandoned'          => [ 'label' => __( 'Cart Abandoned', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_cart_abandoned', 'data_items' => [ 'customer', 'cart' ] ],
            'cart_updated'            => [ 'label' => __( 'Cart Updated', 'kdna-ecommerce' ), 'hook' => 'woocommerce_cart_updated', 'data_items' => [ 'customer', 'cart' ] ],
            'cart_item_added'         => [ 'label' => __( 'Cart Item Added', 'kdna-ecommerce' ), 'hook' => 'woocommerce_add_to_cart', 'data_items' => [ 'customer', 'cart', 'product' ] ],

            // Subscription triggers.
            'subscription_created'    => [ 'label' => __( 'Subscription Created', 'kdna-ecommerce' ), 'hook' => 'woocommerce_subscription_status_active', 'data_items' => [ 'customer', 'subscription' ] ],
            'subscription_status_changed' => [ 'label' => __( 'Subscription Status Changed', 'kdna-ecommerce' ), 'hook' => 'woocommerce_subscription_status_changed', 'data_items' => [ 'customer', 'subscription' ], 'options' => [
                [ 'name' => 'from_status', 'label' => __( 'From Status', 'kdna-ecommerce' ), 'type' => 'text' ],
                [ 'name' => 'to_status', 'label' => __( 'To Status', 'kdna-ecommerce' ), 'type' => 'text' ],
            ] ],
            'subscription_renewal_payment' => [ 'label' => __( 'Subscription Renewal Payment', 'kdna-ecommerce' ), 'hook' => 'woocommerce_subscription_renewal_payment_complete', 'data_items' => [ 'customer', 'subscription', 'order' ] ],
            'subscription_renewal_failed'  => [ 'label' => __( 'Subscription Renewal Failed', 'kdna-ecommerce' ), 'hook' => 'woocommerce_subscription_renewal_payment_failed', 'data_items' => [ 'customer', 'subscription' ] ],
            'subscription_before_renewal'  => [ 'label' => __( 'Before Subscription Renewal', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_before_renewal', 'data_items' => [ 'customer', 'subscription' ], 'options' => [
                [ 'name' => 'days_before', 'label' => __( 'Days Before', 'kdna-ecommerce' ), 'type' => 'number' ],
            ] ],
            'subscription_before_end' => [ 'label' => __( 'Before Subscription End', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_before_sub_end', 'data_items' => [ 'customer', 'subscription' ] ],
            'subscription_trial_end'  => [ 'label' => __( 'Subscription Trial End', 'kdna-ecommerce' ), 'hook' => 'woocommerce_subscription_trial_end', 'data_items' => [ 'customer', 'subscription' ] ],

            // Wishlist triggers.
            'wishlist_item_added'     => [ 'label' => __( 'Wishlist Item Added', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_wishlist_item_added', 'data_items' => [ 'customer', 'product' ] ],
            'wishlist_item_on_sale'   => [ 'label' => __( 'Wishlist Item On Sale', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_wishlist_on_sale', 'data_items' => [ 'customer', 'product' ] ],
            'wishlist_reminder'       => [ 'label' => __( 'Wishlist Reminder', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_wishlist_reminder', 'data_items' => [ 'customer' ] ],

            // Membership triggers.
            'membership_created'      => [ 'label' => __( 'Membership Created', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_membership_created', 'data_items' => [ 'customer', 'membership' ] ],
            'membership_status_changed' => [ 'label' => __( 'Membership Status Changed', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_membership_status_changed', 'data_items' => [ 'customer', 'membership' ] ],
            'membership_before_end'   => [ 'label' => __( 'Before Membership End', 'kdna-ecommerce' ), 'hook' => 'kdna_aw_before_membership_end', 'data_items' => [ 'customer', 'membership' ] ],
        ];
    }

    private function get_order_status_choices() {
        $statuses = wc_get_order_statuses();
        $choices  = [];
        foreach ( $statuses as $key => $label ) {
            $choices[] = [ 'value' => str_replace( 'wc-', '', $key ), 'label' => $label ];
        }
        return $choices;
    }

    private function register_trigger_hooks() {
        $triggers  = $this->get_registered_triggers();
        $workflows = $this->get_active_workflows();

        foreach ( $workflows as $workflow_id ) {
            $trigger = get_post_meta( $workflow_id, self::META_TRIGGER, true );
            if ( empty( $trigger ) || ! isset( $triggers[ $trigger ] ) ) {
                continue;
            }

            $config = $triggers[ $trigger ];
            $hook   = $config['hook'];

            // Skip custom hooks that are fired by cron or internal logic.
            if ( strpos( $hook, 'kdna_aw_' ) === 0 ) {
                continue;
            }

            // Register the hook dynamically. The callback extracts data layer and processes.
            add_action( $hook, function () use ( $workflow_id, $trigger, $config ) {
                $args       = func_get_args();
                $data_layer = $this->build_data_layer( $trigger, $config, $args );
                $this->maybe_run_workflow( $workflow_id, $data_layer );
            }, 99, 5 );
        }
    }

    private function get_active_workflows() {
        $query = new \WP_Query( [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => self::META_STATUS,
            'meta_value'     => 'active',
            'no_found_rows'  => true,
        ] );
        return $query->posts;
    }


    // =========================================================================
    // Workflow Engine
    // =========================================================================

    private function build_data_layer( $trigger, $config, $args ) {
        $data = [ 'trigger' => $trigger ];

        switch ( $trigger ) {
            case 'order_created':
            case 'order_completed':
            case 'order_paid':
            case 'order_pending':
            case 'order_processing':
            case 'order_on_hold':
            case 'order_cancelled':
            case 'order_refunded':
                $order_id = is_numeric( $args[0] ) ? $args[0] : 0;
                if ( $order_id ) {
                    $order = wc_get_order( $order_id );
                    if ( $order ) {
                        $data['order']    = $order;
                        $data['customer'] = $this->get_customer_from_order( $order );
                    }
                }
                break;

            case 'order_status_changed':
                $order_id    = $args[0] ?? 0;
                $from_status = $args[1] ?? '';
                $to_status   = $args[2] ?? '';
                $order       = wc_get_order( $order_id );
                if ( $order ) {
                    $data['order']       = $order;
                    $data['customer']    = $this->get_customer_from_order( $order );
                    $data['from_status'] = $from_status;
                    $data['to_status']   = $to_status;
                }
                break;

            case 'order_note_added':
                $note_data = $args[0] ?? [];
                if ( ! empty( $note_data['order_id'] ) ) {
                    $order = wc_get_order( $note_data['order_id'] );
                    if ( $order ) {
                        $data['order']    = $order;
                        $data['customer'] = $this->get_customer_from_order( $order );
                    }
                }
                break;

            case 'user_registered':
            case 'customer_account_updated':
                $user_id = $args[0] ?? 0;
                if ( $user_id ) {
                    $data['customer'] = new \WC_Customer( $user_id );
                }
                break;

            case 'customer_review_posted':
                $comment_id = $args[0] ?? 0;
                $comment    = get_comment( $comment_id );
                if ( $comment && $comment->comment_type === 'review' ) {
                    $data['review']   = $comment;
                    $data['customer'] = $comment->user_id ? new \WC_Customer( $comment->user_id ) : null;
                    $product_id       = $comment->comment_post_ID;
                    $data['product']  = wc_get_product( $product_id );
                }
                break;

            case 'cart_item_added':
                $cart_item_key = $args[0] ?? '';
                $product_id    = $args[1] ?? 0;
                $data['product']  = wc_get_product( $product_id );
                $data['cart']     = WC()->cart;
                $data['customer'] = $this->get_current_customer();
                break;

            case 'subscription_created':
            case 'subscription_renewal_payment':
            case 'subscription_renewal_failed':
            case 'subscription_trial_end':
                $subscription = $args[0] ?? null;
                if ( is_object( $subscription ) ) {
                    $data['subscription'] = $subscription;
                    $data['customer']     = new \WC_Customer( $subscription->get_customer_id() );
                }
                break;

            default:
                // For custom triggers pass all args.
                $data['args'] = $args;
                if ( is_user_logged_in() ) {
                    $data['customer'] = $this->get_current_customer();
                }
                break;
        }

        return $data;
    }

    private function get_customer_from_order( $order ) {
        $user_id = $order->get_customer_id();
        if ( $user_id ) {
            return new \WC_Customer( $user_id );
        }
        return (object) [
            'email'      => $order->get_billing_email(),
            'first_name' => $order->get_billing_first_name(),
            'last_name'  => $order->get_billing_last_name(),
            'is_guest'   => true,
        ];
    }

    private function get_current_customer() {
        if ( is_user_logged_in() ) {
            return new \WC_Customer( get_current_user_id() );
        }
        return null;
    }

    public function maybe_run_workflow( $workflow_id, $data_layer ) {
        // Check trigger-specific options.
        $trigger         = get_post_meta( $workflow_id, self::META_TRIGGER, true );
        $trigger_options = get_post_meta( $workflow_id, self::META_TRIGGER_OPTIONS, true ) ?: [];

        if ( ! $this->validate_trigger_options( $trigger, $trigger_options, $data_layer ) ) {
            return;
        }

        // Evaluate rules.
        $rules = get_post_meta( $workflow_id, self::META_RULES, true ) ?: [];
        if ( ! empty( $rules ) && ! $this->evaluate_rules( $rules, $data_layer ) ) {
            return;
        }

        // Check unsubscribe.
        $email = $this->get_customer_email( $data_layer );
        if ( $email && $this->is_unsubscribed( $email, $workflow_id ) ) {
            return;
        }

        // Check timing.
        $timing = get_post_meta( $workflow_id, self::META_TIMING, true ) ?: [ 'type' => 'immediate' ];

        if ( $timing['type'] === 'immediate' ) {
            $this->process_workflow( $workflow_id, $data_layer );
        } else {
            $this->add_to_queue( $workflow_id, $data_layer, $timing );
        }
    }

    private function validate_trigger_options( $trigger, $options, $data_layer ) {
        if ( $trigger === 'order_status_changed' ) {
            $from = $options['from_status'] ?? [];
            $to   = $options['to_status'] ?? [];
            if ( ! empty( $from ) && ! in_array( $data_layer['from_status'] ?? '', (array) $from, true ) ) {
                return false;
            }
            if ( ! empty( $to ) && ! in_array( $data_layer['to_status'] ?? '', (array) $to, true ) ) {
                return false;
            }
        }

        if ( $trigger === 'customer_purchases_above_value' ) {
            $threshold = (float) ( $options['threshold'] ?? 0 );
            $customer  = $data_layer['customer'] ?? null;
            if ( $customer && method_exists( $customer, 'get_total_spent' ) ) {
                if ( (float) $customer->get_total_spent() < $threshold ) {
                    return false;
                }
            }
        }

        return true;
    }

    public function process_workflow( $workflow_id, $data_layer ) {
        $actions = get_post_meta( $workflow_id, self::META_ACTIONS, true ) ?: [];
        if ( empty( $actions ) ) {
            return;
        }

        $actions_run = [];
        $tracking_key = wp_generate_password( 32, false );

        foreach ( $actions as $action_config ) {
            $action_type = $action_config['type'] ?? '';
            if ( empty( $action_type ) ) {
                continue;
            }

            $result = $this->execute_action( $action_type, $action_config, $data_layer, $tracking_key );
            $actions_run[] = [
                'type'   => $action_type,
                'result' => $result ? 'success' : 'failed',
            ];
        }

        // Update run count.
        $count = (int) get_post_meta( $workflow_id, self::META_RUN_COUNT, true );
        update_post_meta( $workflow_id, self::META_RUN_COUNT, $count + 1 );

        // Log execution.
        $this->log_execution( $workflow_id, $data_layer, $actions_run, $tracking_key );
    }

    // =========================================================================
    // Rules Evaluation
    // =========================================================================

    private function get_registered_rules_grouped() {
        return [
            __( 'Order', 'kdna-ecommerce' ) => [
                'order_total'           => __( 'Order Total', 'kdna-ecommerce' ),
                'order_item_count'      => __( 'Order Item Count', 'kdna-ecommerce' ),
                'order_status'          => __( 'Order Status', 'kdna-ecommerce' ),
                'order_has_coupon'      => __( 'Order Has Coupon', 'kdna-ecommerce' ),
                'order_is_first'        => __( 'Is First Order', 'kdna-ecommerce' ),
                'order_payment_method'  => __( 'Payment Method', 'kdna-ecommerce' ),
                'order_billing_country' => __( 'Billing Country', 'kdna-ecommerce' ),
                'order_shipping_method' => __( 'Shipping Method', 'kdna-ecommerce' ),
                'order_meta'            => __( 'Order Meta', 'kdna-ecommerce' ),
            ],
            __( 'Customer', 'kdna-ecommerce' ) => [
                'customer_email'             => __( 'Customer Email', 'kdna-ecommerce' ),
                'customer_role'              => __( 'Customer Role', 'kdna-ecommerce' ),
                'customer_total_spent'       => __( 'Total Spent', 'kdna-ecommerce' ),
                'customer_order_count'       => __( 'Order Count', 'kdna-ecommerce' ),
                'customer_last_order_date'   => __( 'Last Order Date', 'kdna-ecommerce' ),
                'customer_has_active_subscription' => __( 'Has Active Subscription', 'kdna-ecommerce' ),
                'customer_meta'              => __( 'Customer Meta', 'kdna-ecommerce' ),
                'customer_is_guest'          => __( 'Is Guest', 'kdna-ecommerce' ),
                'customer_tags'              => __( 'Customer Tags', 'kdna-ecommerce' ),
                'customer_opted_in'          => __( 'Opted In to Marketing', 'kdna-ecommerce' ),
                'customer_city'              => __( 'Billing City', 'kdna-ecommerce' ),
                'customer_state'             => __( 'Billing State', 'kdna-ecommerce' ),
                'customer_country'           => __( 'Billing Country', 'kdna-ecommerce' ),
                'customer_postcode'          => __( 'Billing Postcode', 'kdna-ecommerce' ),
                'customer_purchase_count_product' => __( 'Purchases of Product', 'kdna-ecommerce' ),
            ],
            __( 'Cart', 'kdna-ecommerce' ) => [
                'cart_total'        => __( 'Cart Total', 'kdna-ecommerce' ),
                'cart_item_count'   => __( 'Cart Item Count', 'kdna-ecommerce' ),
                'cart_has_product'  => __( 'Cart Has Product', 'kdna-ecommerce' ),
                'cart_has_category' => __( 'Cart Has Category', 'kdna-ecommerce' ),
                'cart_coupons'      => __( 'Cart Coupons', 'kdna-ecommerce' ),
            ],
            __( 'Product', 'kdna-ecommerce' ) => [
                'product_type'         => __( 'Product Type', 'kdna-ecommerce' ),
                'product_categories'   => __( 'Product Categories', 'kdna-ecommerce' ),
                'product_tags'         => __( 'Product Tags', 'kdna-ecommerce' ),
                'product_price'        => __( 'Product Price', 'kdna-ecommerce' ),
                'product_stock_status' => __( 'Stock Status', 'kdna-ecommerce' ),
                'product_meta'         => __( 'Product Meta', 'kdna-ecommerce' ),
            ],
            __( 'Subscription', 'kdna-ecommerce' ) => [
                'subscription_status'         => __( 'Subscription Status', 'kdna-ecommerce' ),
                'subscription_trial'          => __( 'Has Trial', 'kdna-ecommerce' ),
                'subscription_payment_method' => __( 'Payment Method', 'kdna-ecommerce' ),
            ],
        ];
    }

    private function get_compare_types() {
        return [
            'is'           => __( 'is', 'kdna-ecommerce' ),
            'is_not'       => __( 'is not', 'kdna-ecommerce' ),
            'greater_than' => __( 'greater than', 'kdna-ecommerce' ),
            'less_than'    => __( 'less than', 'kdna-ecommerce' ),
            'contains'     => __( 'contains', 'kdna-ecommerce' ),
            'not_contains' => __( 'does not contain', 'kdna-ecommerce' ),
            'starts_with'  => __( 'starts with', 'kdna-ecommerce' ),
            'ends_with'    => __( 'ends with', 'kdna-ecommerce' ),
            'is_empty'     => __( 'is empty', 'kdna-ecommerce' ),
            'is_not_empty' => __( 'is not empty', 'kdna-ecommerce' ),
            'matches_any'  => __( 'matches any of', 'kdna-ecommerce' ),
            'matches_none' => __( 'matches none of', 'kdna-ecommerce' ),
        ];
    }

    private function evaluate_rules( $rule_groups, $data_layer ) {
        // Rule groups are OR-ed. Within a group, rules are AND-ed.
        foreach ( $rule_groups as $group ) {
            $group_pass = true;
            foreach ( $group as $rule ) {
                if ( ! $this->evaluate_single_rule( $rule, $data_layer ) ) {
                    $group_pass = false;
                    break;
                }
            }
            if ( $group_pass ) {
                return true;
            }
        }
        return false;
    }

    private function evaluate_single_rule( $rule, $data_layer ) {
        $name    = $rule['name'] ?? '';
        $compare = $rule['compare'] ?? 'is';
        $value   = $rule['value'] ?? '';

        $actual = $this->get_rule_value( $name, $data_layer );

        return $this->compare_values( $actual, $compare, $value );
    }

    private function get_rule_value( $name, $data_layer ) {
        $order    = $data_layer['order'] ?? null;
        $customer = $data_layer['customer'] ?? null;
        $cart     = $data_layer['cart'] ?? null;
        $product  = $data_layer['product'] ?? null;

        switch ( $name ) {
            case 'order_total':
                return $order ? (float) $order->get_total() : 0;
            case 'order_item_count':
                return $order ? $order->get_item_count() : 0;
            case 'order_status':
                return $order ? $order->get_status() : '';
            case 'order_has_coupon':
                return $order ? implode( ',', $order->get_coupon_codes() ) : '';
            case 'order_is_first':
                if ( $customer && method_exists( $customer, 'get_order_count' ) ) {
                    return $customer->get_order_count() <= 1 ? 'yes' : 'no';
                }
                return 'no';
            case 'order_payment_method':
                return $order ? $order->get_payment_method() : '';
            case 'order_billing_country':
                return $order ? $order->get_billing_country() : '';
            case 'order_shipping_method':
                if ( $order ) {
                    $methods = $order->get_shipping_methods();
                    return ! empty( $methods ) ? current( $methods )->get_method_id() : '';
                }
                return '';
            case 'customer_email':
                return $customer ? ( method_exists( $customer, 'get_email' ) ? $customer->get_email() : ( $customer->email ?? '' ) ) : '';
            case 'customer_role':
                if ( $customer && method_exists( $customer, 'get_role' ) ) {
                    return $customer->get_role();
                }
                return '';
            case 'customer_total_spent':
                return $customer && method_exists( $customer, 'get_total_spent' ) ? (float) $customer->get_total_spent() : 0;
            case 'customer_order_count':
                return $customer && method_exists( $customer, 'get_order_count' ) ? $customer->get_order_count() : 0;
            case 'customer_is_guest':
                return ( $customer && isset( $customer->is_guest ) && $customer->is_guest ) ? 'yes' : 'no';
            case 'customer_country':
                return $customer && method_exists( $customer, 'get_billing_country' ) ? $customer->get_billing_country() : '';
            case 'customer_state':
                return $customer && method_exists( $customer, 'get_billing_state' ) ? $customer->get_billing_state() : '';
            case 'customer_city':
                return $customer && method_exists( $customer, 'get_billing_city' ) ? $customer->get_billing_city() : '';
            case 'customer_postcode':
                return $customer && method_exists( $customer, 'get_billing_postcode' ) ? $customer->get_billing_postcode() : '';
            case 'cart_total':
                return $cart && method_exists( $cart, 'get_total' ) ? (float) $cart->get_total( 'edit' ) : 0;
            case 'cart_item_count':
                return $cart && method_exists( $cart, 'get_cart_contents_count' ) ? $cart->get_cart_contents_count() : 0;
            case 'product_price':
                return $product ? (float) $product->get_price() : 0;
            case 'product_stock_status':
                return $product ? $product->get_stock_status() : '';
            case 'product_type':
                return $product ? $product->get_type() : '';
            default:
                return '';
        }
    }

    private function compare_values( $actual, $compare, $expected ) {
        switch ( $compare ) {
            case 'is':
                return (string) $actual === (string) $expected;
            case 'is_not':
                return (string) $actual !== (string) $expected;
            case 'greater_than':
                return (float) $actual > (float) $expected;
            case 'less_than':
                return (float) $actual < (float) $expected;
            case 'contains':
                return stripos( (string) $actual, (string) $expected ) !== false;
            case 'not_contains':
                return stripos( (string) $actual, (string) $expected ) === false;
            case 'starts_with':
                return strpos( (string) $actual, (string) $expected ) === 0;
            case 'ends_with':
                return substr( (string) $actual, -strlen( $expected ) ) === (string) $expected;
            case 'is_empty':
                return empty( $actual );
            case 'is_not_empty':
                return ! empty( $actual );
            case 'matches_any':
                $options = array_map( 'trim', explode( ',', $expected ) );
                return in_array( (string) $actual, $options, true );
            case 'matches_none':
                $options = array_map( 'trim', explode( ',', $expected ) );
                return ! in_array( (string) $actual, $options, true );
            default:
                return false;
        }
    }


    // =========================================================================
    // Actions
    // =========================================================================

    private function get_registered_actions_grouped() {
        return [
            __( 'Email', 'kdna-ecommerce' ) => [
                'send_email'      => __( 'Send Email', 'kdna-ecommerce' ),
                'send_html_email' => __( 'Send HTML Email (Template)', 'kdna-ecommerce' ),
            ],
            __( 'SMS', 'kdna-ecommerce' ) => [
                'send_sms' => __( 'Send SMS (Twilio)', 'kdna-ecommerce' ),
            ],
            __( 'WooCommerce', 'kdna-ecommerce' ) => [
                'change_order_status' => __( 'Change Order Status', 'kdna-ecommerce' ),
                'add_order_note'      => __( 'Add Order Note', 'kdna-ecommerce' ),
                'generate_coupon'     => __( 'Generate Coupon', 'kdna-ecommerce' ),
                'add_to_cart'         => __( 'Add Product to Cart', 'kdna-ecommerce' ),
                'remove_from_cart'    => __( 'Remove Product from Cart', 'kdna-ecommerce' ),
            ],
            __( 'Customer', 'kdna-ecommerce' ) => [
                'update_customer_meta'  => __( 'Update Customer Meta', 'kdna-ecommerce' ),
                'add_customer_tag'      => __( 'Add Customer Tag', 'kdna-ecommerce' ),
                'remove_customer_tag'   => __( 'Remove Customer Tag', 'kdna-ecommerce' ),
                'change_membership_plan'=> __( 'Change Membership Plan', 'kdna-ecommerce' ),
                'add_points'            => __( 'Add Reward Points', 'kdna-ecommerce' ),
                'remove_points'         => __( 'Remove Reward Points', 'kdna-ecommerce' ),
            ],
            __( 'Subscription', 'kdna-ecommerce' ) => [
                'change_subscription_status' => __( 'Change Subscription Status', 'kdna-ecommerce' ),
                'update_subscription_meta'   => __( 'Update Subscription Meta', 'kdna-ecommerce' ),
            ],
            __( 'Integrations', 'kdna-ecommerce' ) => [
                'add_to_mailchimp_list'       => __( 'Add to Mailchimp List', 'kdna-ecommerce' ),
                'remove_from_mailchimp_list'  => __( 'Remove from Mailchimp List', 'kdna-ecommerce' ),
                'add_to_activecampaign_list'  => __( 'Add to ActiveCampaign List', 'kdna-ecommerce' ),
                'add_to_campaign_monitor_list'=> __( 'Add to Campaign Monitor List', 'kdna-ecommerce' ),
                'update_contact_field'        => __( 'Update Contact Field', 'kdna-ecommerce' ),
            ],
            __( 'Other', 'kdna-ecommerce' ) => [
                'custom_function'        => __( 'Run Custom Function', 'kdna-ecommerce' ),
                'change_workflow_status'  => __( 'Enable/Disable Another Workflow', 'kdna-ecommerce' ),
                'clear_queued_events'    => __( 'Clear Queued Events', 'kdna-ecommerce' ),
            ],
        ];
    }

    private function get_action_fields() {
        return [
            'send_email' => [
                [ 'name' => 'to', 'label' => __( 'To', 'kdna-ecommerce' ), 'type' => 'text', 'default' => '{{ customer.email }}' ],
                [ 'name' => 'subject', 'label' => __( 'Subject', 'kdna-ecommerce' ), 'type' => 'text' ],
                [ 'name' => 'heading', 'label' => __( 'Heading', 'kdna-ecommerce' ), 'type' => 'text' ],
                [ 'name' => 'body', 'label' => __( 'Email Body', 'kdna-ecommerce' ), 'type' => 'wysiwyg' ],
                [ 'name' => 'preheader', 'label' => __( 'Preheader', 'kdna-ecommerce' ), 'type' => 'text' ],
            ],
            'send_html_email' => [
                [ 'name' => 'to', 'label' => __( 'To', 'kdna-ecommerce' ), 'type' => 'text', 'default' => '{{ customer.email }}' ],
                [ 'name' => 'subject', 'label' => __( 'Subject', 'kdna-ecommerce' ), 'type' => 'text' ],
                [ 'name' => 'template_id', 'label' => __( 'Email Template', 'kdna-ecommerce' ), 'type' => 'template_select' ],
            ],
            'send_sms' => [
                [ 'name' => 'to', 'label' => __( 'To Phone Number', 'kdna-ecommerce' ), 'type' => 'text', 'default' => '{{ customer.phone }}' ],
                [ 'name' => 'message', 'label' => __( 'Message', 'kdna-ecommerce' ), 'type' => 'textarea' ],
            ],
            'change_order_status' => [
                [ 'name' => 'new_status', 'label' => __( 'New Status', 'kdna-ecommerce' ), 'type' => 'select', 'choices' => $this->get_order_status_choices() ],
            ],
            'add_order_note' => [
                [ 'name' => 'note', 'label' => __( 'Note', 'kdna-ecommerce' ), 'type' => 'textarea' ],
                [ 'name' => 'is_customer_note', 'label' => __( 'Customer Note?', 'kdna-ecommerce' ), 'type' => 'select', 'choices' => [
                    [ 'value' => 'no', 'label' => __( 'No (Private)', 'kdna-ecommerce' ) ],
                    [ 'value' => 'yes', 'label' => __( 'Yes (Customer)', 'kdna-ecommerce' ) ],
                ] ],
            ],
            'generate_coupon' => [
                [ 'name' => 'coupon_type', 'label' => __( 'Discount Type', 'kdna-ecommerce' ), 'type' => 'select', 'choices' => [
                    [ 'value' => 'percent', 'label' => __( 'Percentage', 'kdna-ecommerce' ) ],
                    [ 'value' => 'fixed_cart', 'label' => __( 'Fixed Cart', 'kdna-ecommerce' ) ],
                    [ 'value' => 'fixed_product', 'label' => __( 'Fixed Product', 'kdna-ecommerce' ) ],
                ] ],
                [ 'name' => 'coupon_amount', 'label' => __( 'Amount', 'kdna-ecommerce' ), 'type' => 'number' ],
                [ 'name' => 'coupon_prefix', 'label' => __( 'Code Prefix', 'kdna-ecommerce' ), 'type' => 'text', 'default' => 'AW-' ],
                [ 'name' => 'coupon_expiry_days', 'label' => __( 'Expires After (days)', 'kdna-ecommerce' ), 'type' => 'number', 'default' => '30' ],
                [ 'name' => 'coupon_usage_limit', 'label' => __( 'Usage Limit', 'kdna-ecommerce' ), 'type' => 'number', 'default' => '1' ],
            ],
            'update_customer_meta' => [
                [ 'name' => 'meta_key', 'label' => __( 'Meta Key', 'kdna-ecommerce' ), 'type' => 'text' ],
                [ 'name' => 'meta_value', 'label' => __( 'Meta Value', 'kdna-ecommerce' ), 'type' => 'text' ],
            ],
            'add_customer_tag' => [
                [ 'name' => 'tag', 'label' => __( 'Tag', 'kdna-ecommerce' ), 'type' => 'text' ],
            ],
            'remove_customer_tag' => [
                [ 'name' => 'tag', 'label' => __( 'Tag', 'kdna-ecommerce' ), 'type' => 'text' ],
            ],
            'add_points' => [
                [ 'name' => 'points', 'label' => __( 'Points', 'kdna-ecommerce' ), 'type' => 'number' ],
                [ 'name' => 'reason', 'label' => __( 'Reason', 'kdna-ecommerce' ), 'type' => 'text', 'default' => 'Workflow bonus' ],
            ],
            'remove_points' => [
                [ 'name' => 'points', 'label' => __( 'Points', 'kdna-ecommerce' ), 'type' => 'number' ],
                [ 'name' => 'reason', 'label' => __( 'Reason', 'kdna-ecommerce' ), 'type' => 'text' ],
            ],
            'add_to_mailchimp_list' => [
                [ 'name' => 'list_id', 'label' => __( 'List/Audience ID', 'kdna-ecommerce' ), 'type' => 'text' ],
            ],
            'remove_from_mailchimp_list' => [
                [ 'name' => 'list_id', 'label' => __( 'List/Audience ID', 'kdna-ecommerce' ), 'type' => 'text' ],
            ],
            'add_to_activecampaign_list' => [
                [ 'name' => 'list_id', 'label' => __( 'List ID', 'kdna-ecommerce' ), 'type' => 'text' ],
            ],
            'add_to_campaign_monitor_list' => [
                [ 'name' => 'list_id', 'label' => __( 'List ID', 'kdna-ecommerce' ), 'type' => 'text' ],
            ],
            'custom_function' => [
                [ 'name' => 'function_name', 'label' => __( 'Function Name', 'kdna-ecommerce' ), 'type' => 'text' ],
            ],
            'change_workflow_status' => [
                [ 'name' => 'target_workflow', 'label' => __( 'Workflow ID', 'kdna-ecommerce' ), 'type' => 'number' ],
                [ 'name' => 'new_status', 'label' => __( 'Status', 'kdna-ecommerce' ), 'type' => 'select', 'choices' => [
                    [ 'value' => 'active', 'label' => __( 'Active', 'kdna-ecommerce' ) ],
                    [ 'value' => 'disabled', 'label' => __( 'Disabled', 'kdna-ecommerce' ) ],
                ] ],
            ],
            'add_to_cart' => [
                [ 'name' => 'product_id', 'label' => __( 'Product ID', 'kdna-ecommerce' ), 'type' => 'number' ],
                [ 'name' => 'quantity', 'label' => __( 'Quantity', 'kdna-ecommerce' ), 'type' => 'number', 'default' => '1' ],
            ],
            'change_subscription_status' => [
                [ 'name' => 'new_status', 'label' => __( 'New Status', 'kdna-ecommerce' ), 'type' => 'select', 'choices' => [
                    [ 'value' => 'active', 'label' => __( 'Active', 'kdna-ecommerce' ) ],
                    [ 'value' => 'on-hold', 'label' => __( 'On Hold', 'kdna-ecommerce' ) ],
                    [ 'value' => 'cancelled', 'label' => __( 'Cancelled', 'kdna-ecommerce' ) ],
                ] ],
            ],
            'update_subscription_meta' => [
                [ 'name' => 'meta_key', 'label' => __( 'Meta Key', 'kdna-ecommerce' ), 'type' => 'text' ],
                [ 'name' => 'meta_value', 'label' => __( 'Meta Value', 'kdna-ecommerce' ), 'type' => 'text' ],
            ],
            'clear_queued_events' => [],
        ];
    }

    private function execute_action( $action_type, $config, $data_layer, $tracking_key ) {
        switch ( $action_type ) {
            case 'send_email':
                return $this->action_send_email( $config, $data_layer, $tracking_key );

            case 'send_html_email':
                return $this->action_send_html_email( $config, $data_layer, $tracking_key );

            case 'send_sms':
                return $this->action_send_sms( $config, $data_layer );

            case 'change_order_status':
                $order = $data_layer['order'] ?? null;
                if ( $order ) {
                    $order->update_status( sanitize_text_field( $config['new_status'] ?? '' ), __( 'Status changed by AutomateWoo workflow.', 'kdna-ecommerce' ) );
                    return true;
                }
                return false;

            case 'add_order_note':
                $order = $data_layer['order'] ?? null;
                if ( $order ) {
                    $note    = $this->replace_variables( $config['note'] ?? '', $data_layer );
                    $is_cust = ( $config['is_customer_note'] ?? 'no' ) === 'yes';
                    $order->add_order_note( $note, $is_cust ? 1 : 0 );
                    return true;
                }
                return false;

            case 'generate_coupon':
                return $this->action_generate_coupon( $config, $data_layer );

            case 'update_customer_meta':
                $customer = $data_layer['customer'] ?? null;
                if ( $customer && method_exists( $customer, 'get_id' ) && $customer->get_id() ) {
                    $value = $this->replace_variables( $config['meta_value'] ?? '', $data_layer );
                    update_user_meta( $customer->get_id(), sanitize_key( $config['meta_key'] ?? '' ), $value );
                    return true;
                }
                return false;

            case 'add_customer_tag':
            case 'remove_customer_tag':
                $customer = $data_layer['customer'] ?? null;
                if ( $customer && method_exists( $customer, 'get_id' ) && $customer->get_id() ) {
                    $tag     = sanitize_text_field( $config['tag'] ?? '' );
                    $tags    = get_user_meta( $customer->get_id(), '_kdna_aw_tags', true ) ?: [];
                    if ( $action_type === 'add_customer_tag' ) {
                        $tags[] = $tag;
                        $tags   = array_unique( $tags );
                    } else {
                        $tags = array_diff( $tags, [ $tag ] );
                    }
                    update_user_meta( $customer->get_id(), '_kdna_aw_tags', array_values( $tags ) );
                    return true;
                }
                return false;

            case 'add_points':
            case 'remove_points':
                $customer = $data_layer['customer'] ?? null;
                if ( $customer && method_exists( $customer, 'get_id' ) && $customer->get_id() && function_exists( 'kdna_ecommerce' ) ) {
                    $points_module = kdna_ecommerce()->get_module( 'points_rewards' );
                    if ( $points_module ) {
                        $pts    = absint( $config['points'] ?? 0 );
                        $reason = $config['reason'] ?? '';
                        if ( $action_type === 'add_points' ) {
                            $points_module->add_points( $customer->get_id(), $pts, 'workflow', $reason );
                        } else {
                            $points_module->deduct_points( $customer->get_id(), $pts, 'workflow', $reason );
                        }
                        return true;
                    }
                }
                return false;

            case 'add_to_mailchimp_list':
                return $this->action_mailchimp_add( $config, $data_layer );

            case 'remove_from_mailchimp_list':
                return $this->action_mailchimp_remove( $config, $data_layer );

            case 'add_to_activecampaign_list':
                return $this->action_activecampaign_add( $config, $data_layer );

            case 'add_to_campaign_monitor_list':
                return $this->action_campaign_monitor_add( $config, $data_layer );

            case 'custom_function':
                $fn = $config['function_name'] ?? '';
                if ( $fn && is_callable( $fn ) ) {
                    call_user_func( $fn, $data_layer );
                    return true;
                }
                return false;

            case 'change_workflow_status':
                $target = absint( $config['target_workflow'] ?? 0 );
                $status = sanitize_text_field( $config['new_status'] ?? 'disabled' );
                if ( $target && get_post_type( $target ) === self::CPT ) {
                    update_post_meta( $target, self::META_STATUS, $status );
                    return true;
                }
                return false;

            case 'clear_queued_events':
                global $wpdb;
                $workflow_id = $data_layer['workflow_id'] ?? 0;
                $email       = $this->get_customer_email( $data_layer );
                if ( $email ) {
                    $wpdb->query( $wpdb->prepare(
                        "DELETE q FROM {$wpdb->prefix}" . self::TABLE_QUEUE . " q
                         INNER JOIN {$wpdb->prefix}" . self::TABLE_GUESTS . " g ON q.guest_id = g.id
                         WHERE q.status = 'pending' AND g.email = %s",
                        $email
                    ) );
                }
                return true;

            default:
                do_action( 'kdna_aw_execute_action_' . $action_type, $config, $data_layer );
                return true;
        }
    }


    // =========================================================================
    // Email & SMS Sending
    // =========================================================================

    private function action_send_email( $config, $data_layer, $tracking_key ) {
        $to      = $this->replace_variables( $config['to'] ?? '', $data_layer );
        $subject = $this->replace_variables( $config['subject'] ?? '', $data_layer );
        $heading = $this->replace_variables( $config['heading'] ?? '', $data_layer );
        $body    = $this->replace_variables( $config['body'] ?? '', $data_layer );

        if ( empty( $to ) || ! is_email( $to ) ) {
            return false;
        }

        // Use WooCommerce email wrapper.
        $mailer  = WC()->mailer();
        $wrapped = $mailer->wrap_message( $heading, wpautop( $body ) );

        // Add tracking pixel.
        $pixel_url = add_query_arg( [ 'kdna_aw_track' => 'open', 'key' => $tracking_key ], home_url( '/' ) );
        $wrapped  .= '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" style="display:none;" />';

        $from_name    = $this->settings['email_from_name'] ?: get_option( 'woocommerce_email_from_name' );
        $from_address = $this->settings['email_from_address'] ?: get_option( 'woocommerce_email_from_address' );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_address . '>',
            'List-Unsubscribe: <' . $this->get_unsubscribe_url( $to ) . '>',
        ];

        return wp_mail( $to, $subject, $wrapped, $headers );
    }

    private function action_send_html_email( $config, $data_layer, $tracking_key ) {
        $to          = $this->replace_variables( $config['to'] ?? '', $data_layer );
        $subject     = $this->replace_variables( $config['subject'] ?? '', $data_layer );
        $template_id = absint( $config['template_id'] ?? 0 );

        if ( empty( $to ) || ! is_email( $to ) || ! $template_id ) {
            return false;
        }

        // Get template HTML from email builder.
        if ( class_exists( 'KDNA_Email_Builder' ) ) {
            $html = KDNA_Email_Builder::compile_template( $template_id );
        } else {
            return false;
        }

        if ( empty( $html ) ) {
            return false;
        }

        // Replace variables in HTML.
        $html = $this->replace_variables( $html, $data_layer );

        // Add tracking pixel.
        $pixel_url = add_query_arg( [ 'kdna_aw_track' => 'open', 'key' => $tracking_key ], home_url( '/' ) );
        $html = str_replace( '</body>', '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" style="display:none;" /></body>', $html );

        $from_name    = $this->settings['email_from_name'] ?: get_option( 'woocommerce_email_from_name' );
        $from_address = $this->settings['email_from_address'] ?: get_option( 'woocommerce_email_from_address' );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_address . '>',
            'List-Unsubscribe: <' . $this->get_unsubscribe_url( $to ) . '>',
        ];

        return wp_mail( $to, $subject, $html, $headers );
    }

    private function action_send_sms( $config, $data_layer ) {
        if ( $this->settings['twilio_enabled'] !== 'yes' ) {
            return false;
        }

        $to      = $this->replace_variables( $config['to'] ?? '', $data_layer );
        $message = $this->replace_variables( $config['message'] ?? '', $data_layer );

        if ( empty( $to ) || empty( $message ) ) {
            return false;
        }

        $account_sid = $this->settings['twilio_auth_id'];
        $auth_token  = $this->settings['twilio_auth_token'];
        $from        = $this->settings['twilio_from'];

        if ( empty( $account_sid ) || empty( $auth_token ) || empty( $from ) ) {
            return false;
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $account_sid . '/Messages.json';

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
            ],
            'body' => [
                'To'   => $to,
                'From' => $from,
                'Body' => $message,
            ],
        ] );

        return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 201;
    }

    // =========================================================================
    // Coupon Generation
    // =========================================================================

    private function action_generate_coupon( $config, $data_layer ) {
        $prefix      = sanitize_text_field( $config['coupon_prefix'] ?? 'AW-' );
        $type        = sanitize_text_field( $config['coupon_type'] ?? 'percent' );
        $amount      = floatval( $config['coupon_amount'] ?? 0 );
        $expiry_days = absint( $config['coupon_expiry_days'] ?? 30 );
        $usage_limit = absint( $config['coupon_usage_limit'] ?? 1 );

        $code = $prefix . strtoupper( wp_generate_password( 8, false ) );

        $coupon = new \WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( $type );
        $coupon->set_amount( $amount );
        $coupon->set_usage_limit( $usage_limit );
        $coupon->set_individual_use( true );

        if ( $expiry_days > 0 ) {
            $coupon->set_date_expires( strtotime( '+' . $expiry_days . ' days' ) );
        }

        // Restrict to customer email if available.
        $email = $this->get_customer_email( $data_layer );
        if ( $email ) {
            $coupon->set_email_restrictions( [ $email ] );
        }

        $coupon->save();

        // Store in data layer for variable replacement.
        $data_layer['coupon'] = $coupon;

        return $coupon->get_id() > 0;
    }

    // =========================================================================
    // Integration Actions
    // =========================================================================

    private function action_mailchimp_add( $config, $data_layer ) {
        $api_key = $this->settings['mailchimp_api_key'];
        if ( empty( $api_key ) ) return false;

        $email   = $this->get_customer_email( $data_layer );
        $list_id = sanitize_text_field( $config['list_id'] ?? '' );
        if ( ! $email || ! $list_id ) return false;

        $dc  = substr( $api_key, strpos( $api_key, '-' ) + 1 );
        $url = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . $list_id . '/members';

        $customer = $data_layer['customer'] ?? null;
        $body     = [
            'email_address' => $email,
            'status'        => 'subscribed',
        ];
        if ( $customer && method_exists( $customer, 'get_first_name' ) ) {
            $body['merge_fields'] = [
                'FNAME' => $customer->get_first_name(),
                'LNAME' => $customer->get_last_name(),
            ];
        }

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'apikey ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        $code = wp_remote_retrieve_response_code( $response );
        return $code === 200 || $code === 201;
    }

    private function action_mailchimp_remove( $config, $data_layer ) {
        $api_key = $this->settings['mailchimp_api_key'];
        if ( empty( $api_key ) ) return false;

        $email   = $this->get_customer_email( $data_layer );
        $list_id = sanitize_text_field( $config['list_id'] ?? '' );
        if ( ! $email || ! $list_id ) return false;

        $dc   = substr( $api_key, strpos( $api_key, '-' ) + 1 );
        $hash = md5( strtolower( $email ) );
        $url  = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . $list_id . '/members/' . $hash;

        $response = wp_remote_request( $url, [
            'method'  => 'PATCH',
            'headers' => [
                'Authorization' => 'apikey ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [ 'status' => 'unsubscribed' ] ),
        ] );

        return wp_remote_retrieve_response_code( $response ) === 200;
    }

    private function action_activecampaign_add( $config, $data_layer ) {
        $api_url = $this->settings['activecampaign_api_url'];
        $api_key = $this->settings['activecampaign_api_key'];
        if ( empty( $api_url ) || empty( $api_key ) ) return false;

        $email   = $this->get_customer_email( $data_layer );
        $list_id = sanitize_text_field( $config['list_id'] ?? '' );
        if ( ! $email || ! $list_id ) return false;

        $customer = $data_layer['customer'] ?? null;

        // Create/update contact.
        $contact_response = wp_remote_post( rtrim( $api_url, '/' ) . '/api/3/contacts', [
            'headers' => [ 'Api-Token' => $api_key, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [ 'contact' => [
                'email'     => $email,
                'firstName' => $customer && method_exists( $customer, 'get_first_name' ) ? $customer->get_first_name() : '',
                'lastName'  => $customer && method_exists( $customer, 'get_last_name' ) ? $customer->get_last_name() : '',
            ] ] ),
        ] );

        $body = json_decode( wp_remote_retrieve_body( $contact_response ), true );
        $contact_id = $body['contact']['id'] ?? null;
        if ( ! $contact_id ) return false;

        // Add to list.
        wp_remote_post( rtrim( $api_url, '/' ) . '/api/3/contactLists', [
            'headers' => [ 'Api-Token' => $api_key, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [ 'contactList' => [
                'list'    => $list_id,
                'contact' => $contact_id,
                'status'  => 1,
            ] ] ),
        ] );

        return true;
    }

    private function action_campaign_monitor_add( $config, $data_layer ) {
        $api_key = $this->settings['campaign_monitor_api_key'];
        if ( empty( $api_key ) ) return false;

        $email   = $this->get_customer_email( $data_layer );
        $list_id = sanitize_text_field( $config['list_id'] ?? '' );
        if ( ! $email || ! $list_id ) return false;

        $customer = $data_layer['customer'] ?? null;
        $name     = '';
        if ( $customer && method_exists( $customer, 'get_first_name' ) ) {
            $name = trim( $customer->get_first_name() . ' ' . $customer->get_last_name() );
        }

        $url = 'https://api.createsend.com/api/v3.2/subscribers/' . $list_id . '.json';

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $api_key . ':x' ),
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'EmailAddress' => $email,
                'Name'         => $name,
                'Resubscribe'  => true,
            ] ),
        ] );

        $code = wp_remote_retrieve_response_code( $response );
        return $code === 200 || $code === 201;
    }


    // =========================================================================
    // Variables System
    // =========================================================================

    private function get_variables_grouped() {
        return [
            __( 'Customer', 'kdna-ecommerce' ) => [
                'customer.first_name' => __( 'First Name', 'kdna-ecommerce' ),
                'customer.last_name'  => __( 'Last Name', 'kdna-ecommerce' ),
                'customer.email'      => __( 'Email', 'kdna-ecommerce' ),
                'customer.phone'      => __( 'Phone', 'kdna-ecommerce' ),
                'customer.company'    => __( 'Company', 'kdna-ecommerce' ),
                'customer.city'       => __( 'City', 'kdna-ecommerce' ),
                'customer.state'      => __( 'State', 'kdna-ecommerce' ),
                'customer.country'    => __( 'Country', 'kdna-ecommerce' ),
                'customer.postcode'   => __( 'Postcode', 'kdna-ecommerce' ),
                'customer.order_count'=> __( 'Order Count', 'kdna-ecommerce' ),
                'customer.total_spent'=> __( 'Total Spent', 'kdna-ecommerce' ),
            ],
            __( 'Order', 'kdna-ecommerce' ) => [
                'order.number'       => __( 'Order Number', 'kdna-ecommerce' ),
                'order.total'        => __( 'Order Total', 'kdna-ecommerce' ),
                'order.subtotal'     => __( 'Subtotal', 'kdna-ecommerce' ),
                'order.date'         => __( 'Order Date', 'kdna-ecommerce' ),
                'order.status'       => __( 'Order Status', 'kdna-ecommerce' ),
                'order.items'        => __( 'Order Items', 'kdna-ecommerce' ),
                'order.payment_method' => __( 'Payment Method', 'kdna-ecommerce' ),
                'order.shipping_method'=> __( 'Shipping Method', 'kdna-ecommerce' ),
                'order.billing_address'=> __( 'Billing Address', 'kdna-ecommerce' ),
                'order.shipping_address'=> __( 'Shipping Address', 'kdna-ecommerce' ),
                'order.view_url'     => __( 'View Order URL', 'kdna-ecommerce' ),
            ],
            __( 'Cart', 'kdna-ecommerce' ) => [
                'cart.items'     => __( 'Cart Items', 'kdna-ecommerce' ),
                'cart.total'     => __( 'Cart Total', 'kdna-ecommerce' ),
                'cart.count'     => __( 'Item Count', 'kdna-ecommerce' ),
            ],
            __( 'Coupon', 'kdna-ecommerce' ) => [
                'coupon.code'   => __( 'Coupon Code', 'kdna-ecommerce' ),
                'coupon.amount' => __( 'Coupon Amount', 'kdna-ecommerce' ),
                'coupon.expiry' => __( 'Expiry Date', 'kdna-ecommerce' ),
            ],
            __( 'Shop', 'kdna-ecommerce' ) => [
                'shop.name'     => __( 'Shop Name', 'kdna-ecommerce' ),
                'shop.url'      => __( 'Shop URL', 'kdna-ecommerce' ),
                'shop.admin_email' => __( 'Admin Email', 'kdna-ecommerce' ),
            ],
            __( 'Links', 'kdna-ecommerce' ) => [
                'unsubscribe_url'   => __( 'Unsubscribe URL', 'kdna-ecommerce' ),
                'cart_recovery_url' => __( 'Cart Recovery URL', 'kdna-ecommerce' ),
            ],
        ];
    }

    public function replace_variables( $text, $data_layer ) {
        $customer = $data_layer['customer'] ?? null;
        $order    = $data_layer['order'] ?? null;
        $cart     = $data_layer['cart'] ?? null;
        $coupon   = $data_layer['coupon'] ?? null;
        $email    = $this->get_customer_email( $data_layer );

        $replacements = [
            // Customer.
            '{{ customer.first_name }}' => $customer ? ( method_exists( $customer, 'get_first_name' ) ? $customer->get_first_name() : ( $customer->first_name ?? '' ) ) : '',
            '{{ customer.last_name }}'  => $customer ? ( method_exists( $customer, 'get_last_name' ) ? $customer->get_last_name() : ( $customer->last_name ?? '' ) ) : '',
            '{{ customer.email }}'      => $email ?: '',
            '{{ customer.phone }}'      => $customer && method_exists( $customer, 'get_billing_phone' ) ? $customer->get_billing_phone() : '',
            '{{ customer.company }}'    => $customer && method_exists( $customer, 'get_billing_company' ) ? $customer->get_billing_company() : '',
            '{{ customer.city }}'       => $customer && method_exists( $customer, 'get_billing_city' ) ? $customer->get_billing_city() : '',
            '{{ customer.state }}'      => $customer && method_exists( $customer, 'get_billing_state' ) ? $customer->get_billing_state() : '',
            '{{ customer.country }}'    => $customer && method_exists( $customer, 'get_billing_country' ) ? $customer->get_billing_country() : '',
            '{{ customer.postcode }}'   => $customer && method_exists( $customer, 'get_billing_postcode' ) ? $customer->get_billing_postcode() : '',
            '{{ customer.order_count }}'=> $customer && method_exists( $customer, 'get_order_count' ) ? $customer->get_order_count() : '0',
            '{{ customer.total_spent }}'=> $customer && method_exists( $customer, 'get_total_spent' ) ? wc_price( $customer->get_total_spent() ) : wc_price( 0 ),

            // Order.
            '{{ order.number }}'         => $order ? $order->get_order_number() : '',
            '{{ order.total }}'          => $order ? $order->get_formatted_order_total() : '',
            '{{ order.subtotal }}'       => $order ? wc_price( $order->get_subtotal() ) : '',
            '{{ order.date }}'           => $order ? wc_format_datetime( $order->get_date_created() ) : '',
            '{{ order.status }}'         => $order ? wc_get_order_status_name( $order->get_status() ) : '',
            '{{ order.items }}'          => $order ? $this->format_order_items( $order ) : '',
            '{{ order.payment_method }}' => $order ? $order->get_payment_method_title() : '',
            '{{ order.shipping_method }}'=> $order ? $order->get_shipping_method() : '',
            '{{ order.billing_address }}'=> $order ? $order->get_formatted_billing_address() : '',
            '{{ order.shipping_address }}'=> $order ? $order->get_formatted_shipping_address() : '',
            '{{ order.view_url }}'       => $order ? $order->get_view_order_url() : '',

            // Cart.
            '{{ cart.items }}'  => $cart && method_exists( $cart, 'get_cart' ) ? $this->format_cart_items( $cart ) : '',
            '{{ cart.total }}'  => $cart && method_exists( $cart, 'get_total' ) ? wc_price( $cart->get_total( 'edit' ) ) : '',
            '{{ cart.count }}'  => $cart && method_exists( $cart, 'get_cart_contents_count' ) ? $cart->get_cart_contents_count() : '0',

            // Coupon.
            '{{ coupon.code }}'   => $coupon ? $coupon->get_code() : '',
            '{{ coupon.amount }}' => $coupon ? wc_price( $coupon->get_amount() ) : '',
            '{{ coupon.expiry }}' => $coupon && $coupon->get_date_expires() ? wc_format_datetime( $coupon->get_date_expires() ) : '',

            // Shop.
            '{{ shop.name }}'       => get_bloginfo( 'name' ),
            '{{ shop.url }}'        => home_url( '/' ),
            '{{ shop.admin_email }}'=> get_option( 'admin_email' ),

            // Links.
            '{{ unsubscribe_url }}'   => $email ? $this->get_unsubscribe_url( $email ) : '',
            '{{ cart_recovery_url }}' => $this->get_cart_recovery_url( $data_layer ),
        ];

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
    }

    private function format_order_items( $order ) {
        $items_html = '<table style="width:100%;border-collapse:collapse;">';
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $items_html .= '<tr>';
            $items_html .= '<td style="padding:6px;border-bottom:1px solid #eee;">';
            if ( $product ) {
                $img = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
                if ( $img ) {
                    $items_html .= '<img src="' . esc_url( $img ) . '" width="40" style="vertical-align:middle;margin-right:8px;" />';
                }
            }
            $items_html .= esc_html( $item->get_name() );
            $items_html .= '</td>';
            $items_html .= '<td style="padding:6px;border-bottom:1px solid #eee;text-align:center;">&times; ' . $item->get_quantity() . '</td>';
            $items_html .= '<td style="padding:6px;border-bottom:1px solid #eee;text-align:right;">' . wc_price( $item->get_total() ) . '</td>';
            $items_html .= '</tr>';
        }
        $items_html .= '</table>';
        return $items_html;
    }

    private function format_cart_items( $cart ) {
        $items_html = '<ul style="list-style:none;padding:0;">';
        foreach ( $cart->get_cart() as $item ) {
            $product = $item['data'];
            $items_html .= '<li style="padding:4px 0;">' . esc_html( $product->get_name() ) . ' &times; ' . $item['quantity'] . ' — ' . wc_price( $item['line_total'] ) . '</li>';
        }
        $items_html .= '</ul>';
        return $items_html;
    }

    private function get_customer_email( $data_layer ) {
        $customer = $data_layer['customer'] ?? null;
        if ( $customer ) {
            if ( method_exists( $customer, 'get_email' ) ) return $customer->get_email();
            if ( isset( $customer->email ) ) return $customer->email;
        }
        $order = $data_layer['order'] ?? null;
        if ( $order ) return $order->get_billing_email();
        return '';
    }

    private function get_email_templates() {
        $templates = [];
        $posts = get_posts( [
            'post_type'      => 'kdna_email_tpl',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ] );
        foreach ( $posts as $p ) {
            $templates[ $p->ID ] = $p->post_title;
        }
        return $templates;
    }


    // =========================================================================
    // Queue Management
    // =========================================================================

    private function add_to_queue( $workflow_id, $data_layer, $timing ) {
        global $wpdb;

        $scheduled_at = current_time( 'mysql' );
        $delay        = absint( $timing['delay'] ?? 1 );
        $unit         = $timing['unit'] ?? 'hours';

        switch ( $unit ) {
            case 'minutes': $scheduled_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delay} minutes" ) ); break;
            case 'hours':   $scheduled_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delay} hours" ) ); break;
            case 'days':    $scheduled_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delay} days" ) ); break;
            case 'weeks':   $scheduled_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delay} weeks" ) ); break;
        }

        if ( $timing['type'] === 'scheduled' ) {
            $time = $timing['scheduled_time'] ?? '09:00';
            $day  = $timing['scheduled_day'] ?? '';
            $scheduled_at = gmdate( 'Y-m-d' ) . ' ' . $time . ':00';
            if ( strtotime( $scheduled_at ) <= time() ) {
                $scheduled_at = gmdate( 'Y-m-d', strtotime( '+1 day' ) ) . ' ' . $time . ':00';
            }
        }

        $customer    = $data_layer['customer'] ?? null;
        $customer_id = $customer && method_exists( $customer, 'get_id' ) ? $customer->get_id() : 0;
        $order       = $data_layer['order'] ?? null;
        $order_id    = $order ? $order->get_id() : 0;

        // Serialize data layer (only IDs, not objects).
        $serialized_data = [
            'trigger'     => $data_layer['trigger'] ?? '',
            'customer_id' => $customer_id,
            'order_id'    => $order_id,
        ];

        $wpdb->insert( $wpdb->prefix . self::TABLE_QUEUE, [
            'workflow_id'  => $workflow_id,
            'customer_id'  => $customer_id,
            'order_id'     => $order_id,
            'data_layer'   => wp_json_encode( $serialized_data ),
            'status'       => 'pending',
            'scheduled_at' => $scheduled_at,
        ] );
    }

    public function process_queue() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_QUEUE;

        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT 50",
            current_time( 'mysql' )
        ) );

        foreach ( $items as $item ) {
            $wpdb->update( $table, [ 'status' => 'running' ], [ 'id' => $item->id ] );

            $data_layer = json_decode( $item->data_layer, true ) ?: [];

            // Rebuild data layer from IDs.
            if ( ! empty( $data_layer['order_id'] ) ) {
                $order = wc_get_order( $data_layer['order_id'] );
                if ( $order ) {
                    $data_layer['order']    = $order;
                    $data_layer['customer'] = $this->get_customer_from_order( $order );
                }
            } elseif ( ! empty( $data_layer['customer_id'] ) ) {
                $data_layer['customer'] = new \WC_Customer( $data_layer['customer_id'] );
            }

            $this->process_workflow( $item->workflow_id, $data_layer );

            $wpdb->update( $table, [
                'status'       => 'completed',
                'completed_at' => current_time( 'mysql' ),
            ], [ 'id' => $item->id ] );
        }
    }

    // =========================================================================
    // Logging
    // =========================================================================

    private function log_execution( $workflow_id, $data_layer, $actions_run, $tracking_key ) {
        global $wpdb;

        $customer    = $data_layer['customer'] ?? null;
        $customer_id = $customer && method_exists( $customer, 'get_id' ) ? $customer->get_id() : 0;
        $order       = $data_layer['order'] ?? null;

        $wpdb->insert( $wpdb->prefix . self::TABLE_LOGS, [
            'workflow_id'   => $workflow_id,
            'workflow_name' => get_the_title( $workflow_id ),
            'customer_id'   => $customer_id,
            'order_id'      => $order ? $order->get_id() : 0,
            'trigger_name'  => $data_layer['trigger'] ?? '',
            'actions_run'   => wp_json_encode( $actions_run ),
            'status'        => 'completed',
            'tracking_key'  => $tracking_key,
        ] );
    }

    // =========================================================================
    // Abandoned Cart Tracking
    // =========================================================================

    public function track_cart() {
        if ( is_admin() || ! WC()->cart || WC()->cart->is_empty() ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_CARTS;

        $user_id  = get_current_user_id();
        $token    = $this->get_or_create_cart_token();
        $email    = '';

        if ( $user_id ) {
            $customer = new \WC_Customer( $user_id );
            $email    = $customer->get_email();
        }

        $cart_data = [
            'items' => [],
            'coupons' => WC()->cart->get_applied_coupons(),
        ];
        foreach ( WC()->cart->get_cart() as $item ) {
            $product = $item['data'];
            $cart_data['items'][] = [
                'product_id'   => $item['product_id'],
                'variation_id' => $item['variation_id'] ?? 0,
                'quantity'     => $item['quantity'],
                'total'        => $item['line_total'],
                'name'         => $product->get_name(),
            ];
        }

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE cart_token = %s LIMIT 1",
            $token
        ) );

        $data = [
            'user_id'    => $user_id,
            'email'      => $email,
            'cart_token'  => $token,
            'cart_data'  => wp_json_encode( $cart_data ),
            'cart_total' => WC()->cart->get_total( 'edit' ),
            'item_count' => WC()->cart->get_cart_contents_count(),
            'status'     => 'active',
            'currency'   => get_woocommerce_currency(),
            'updated_at' => current_time( 'mysql' ),
        ];

        if ( $existing ) {
            $wpdb->update( $table, $data, [ 'id' => $existing ] );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $table, $data );
        }
    }

    private function get_or_create_cart_token() {
        if ( isset( $_COOKIE[ self::COOKIE_CART ] ) ) {
            return sanitize_text_field( $_COOKIE[ self::COOKIE_CART ] );
        }
        $token = wp_generate_password( 32, false );
        wc_setcookie( self::COOKIE_CART, $token, time() + DAY_IN_SECONDS * 30 );
        return $token;
    }

    public function clear_cart_on_order( $order_id ) {
        global $wpdb;
        $token = isset( $_COOKIE[ self::COOKIE_CART ] ) ? sanitize_text_field( $_COOKIE[ self::COOKIE_CART ] ) : '';
        if ( $token ) {
            $wpdb->update( $wpdb->prefix . self::TABLE_CARTS, [
                'status'       => 'recovered',
                'recovered_at' => current_time( 'mysql' ),
            ], [ 'cart_token' => $token ] );
        }
        // Also try by user ID.
        $order   = wc_get_order( $order_id );
        $user_id = $order ? $order->get_customer_id() : 0;
        if ( $user_id ) {
            $wpdb->update( $wpdb->prefix . self::TABLE_CARTS, [
                'status'       => 'recovered',
                'recovered_at' => current_time( 'mysql' ),
            ], [ 'user_id' => $user_id, 'status' => 'active' ] );
        }
    }

    public function link_guest_cart_to_user( $user_login, $user ) {
        global $wpdb;
        $token = isset( $_COOKIE[ self::COOKIE_CART ] ) ? sanitize_text_field( $_COOKIE[ self::COOKIE_CART ] ) : '';
        if ( $token ) {
            $wpdb->update( $wpdb->prefix . self::TABLE_CARTS, [
                'user_id' => $user->ID,
                'email'   => $user->user_email,
            ], [ 'cart_token' => $token ] );
        }
    }

    public function check_abandoned_carts() {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_CARTS;
        $timeout = absint( $this->settings['abandoned_cart_timeout'] ) ?: 15;

        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$timeout} minutes" ) );

        $abandoned = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'active' AND updated_at < %s",
            $cutoff
        ) );

        foreach ( $abandoned as $cart ) {
            $wpdb->update( $table, [
                'status'       => 'abandoned',
                'abandoned_at' => current_time( 'mysql' ),
            ], [ 'id' => $cart->id ] );

            // Fire abandoned cart trigger.
            $data_layer = [
                'trigger'  => 'cart_abandoned',
                'cart'     => $cart,
                'cart_data'=> json_decode( $cart->cart_data, true ),
            ];
            if ( $cart->user_id ) {
                $data_layer['customer'] = new \WC_Customer( $cart->user_id );
            } elseif ( $cart->email ) {
                $data_layer['customer'] = (object) [ 'email' => $cart->email, 'is_guest' => true, 'first_name' => '', 'last_name' => '' ];
            }

            do_action( 'kdna_aw_cart_abandoned', $data_layer );

            // Also check workflows with this trigger.
            $workflows = $this->get_active_workflows();
            foreach ( $workflows as $wf_id ) {
                $trigger = get_post_meta( $wf_id, self::META_TRIGGER, true );
                if ( $trigger === 'cart_abandoned' ) {
                    $this->maybe_run_workflow( $wf_id, $data_layer );
                }
            }
        }
    }

    public function clean_inactive_carts() {
        global $wpdb;
        $days = absint( $this->settings['clear_inactive_carts_after'] ) ?: 60;
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}" . self::TABLE_CARTS . " WHERE updated_at < %s AND status IN ('abandoned', 'recovered')",
            $cutoff
        ) );
    }

    private function get_cart_recovery_url( $data_layer ) {
        $cart = $data_layer['cart'] ?? null;
        if ( is_object( $cart ) && isset( $cart->cart_token ) ) {
            return add_query_arg( [ 'kdna_aw_recover' => $cart->cart_token ], wc_get_cart_url() );
        }
        return wc_get_cart_url();
    }


    // =========================================================================
    // Marketing Opt-in / Opt-out
    // =========================================================================

    public function render_checkout_optin() {
        $text = $this->settings['optin_checkbox_text'] ?: __( 'Yes, I want to receive marketing emails', 'kdna-ecommerce' );
        $mode = $this->settings['optin_mode']; // 'ask' or 'auto'
        if ( $mode === 'auto' ) return; // Auto opt-in, no checkbox needed.
        ?>
        <p class="form-row kdna-aw-optin-field">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="kdna_aw_optin" value="1" />
                <span><?php echo esc_html( $text ); ?></span>
            </label>
        </p>
        <?php
    }

    public function save_checkout_optin( $order_id, $posted_data, $order ) {
        $mode = $this->settings['optin_mode'];
        $opted_in = ( $mode === 'auto' ) || ! empty( $_POST['kdna_aw_optin'] );

        if ( $opted_in ) {
            $user_id = $order->get_customer_id();
            if ( $user_id ) {
                update_user_meta( $user_id, '_kdna_aw_opted_in', 'yes' );
                do_action( 'kdna_aw_customer_opted_in', [ 'customer' => new \WC_Customer( $user_id ) ] );
            }
            // Also track guests.
            $this->track_guest_optin( $order->get_billing_email() );
        }
    }

    public function render_registration_optin() {
        $text = $this->settings['optin_checkbox_text'] ?: __( 'Yes, I want to receive marketing emails', 'kdna-ecommerce' );
        ?>
        <p class="form-row">
            <label class="woocommerce-form__label checkbox">
                <input type="checkbox" name="kdna_aw_optin" value="1" />
                <span><?php echo esc_html( $text ); ?></span>
            </label>
        </p>
        <?php
    }

    public function save_registration_optin( $customer_id, $new_customer_data, $password_generated ) {
        if ( ! empty( $_POST['kdna_aw_optin'] ) || $this->settings['optin_mode'] === 'auto' ) {
            update_user_meta( $customer_id, '_kdna_aw_opted_in', 'yes' );
        }
    }

    private function track_guest_optin( $email ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_GUESTS;
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) );
        if ( $existing ) {
            $wpdb->update( $table, [ 'opted_in' => 1 ], [ 'id' => $existing ] );
        } else {
            $wpdb->insert( $table, [
                'email'    => $email,
                'opted_in' => 1,
            ] );
        }
    }

    // =========================================================================
    // Guest Email Capture
    // =========================================================================

    public function enqueue_capture_script() {
        if ( ! is_checkout() && $this->settings['guest_email_capture_scope'] === 'checkout_only' ) {
            return;
        }
        wp_enqueue_script( 'kdna-aw-capture', false, [], false, true );
        wp_add_inline_script( 'kdna-aw-capture', '
            (function(){
                var captured = false;
                document.addEventListener("change", function(e) {
                    if (captured) return;
                    var el = e.target;
                    if (el.type === "email" || el.id === "billing_email") {
                        var email = el.value;
                        if (email && email.indexOf("@") > 0) {
                            captured = true;
                            var xhr = new XMLHttpRequest();
                            xhr.open("POST", "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '");
                            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                            xhr.send("action=kdna_aw_capture_email&email=" + encodeURIComponent(email) + "&_wpnonce=' . wp_create_nonce( 'kdna_aw_capture' ) . '");
                        }
                    }
                });
            })();
        ' );
    }

    public function ajax_capture_guest_email() {
        check_ajax_referer( 'kdna_aw_capture', '_wpnonce' );
        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( ! is_email( $email ) ) {
            wp_send_json_error();
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_GUESTS;
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) );
        if ( ! $exists ) {
            $wpdb->insert( $table, [
                'email'        => $email,
                'cookie_token' => isset( $_COOKIE[ self::COOKIE_CART ] ) ? sanitize_text_field( $_COOKIE[ self::COOKIE_CART ] ) : '',
                'ip_address'   => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
            ] );
        }

        // Link to cart if exists.
        $token = isset( $_COOKIE[ self::COOKIE_CART ] ) ? sanitize_text_field( $_COOKIE[ self::COOKIE_CART ] ) : '';
        if ( $token ) {
            $wpdb->update( $wpdb->prefix . self::TABLE_CARTS, [ 'email' => $email ], [ 'cart_token' => $token ] );
        }

        wp_send_json_success();
    }

    // =========================================================================
    // Unsubscribe
    // =========================================================================

    public function add_unsubscribe_endpoint() {
        add_rewrite_endpoint( 'kdna-aw-unsubscribe', EP_ROOT );
    }

    public function handle_unsubscribe() {
        if ( ! isset( $_GET['kdna_aw_unsub'] ) ) return;

        $token = sanitize_text_field( $_GET['kdna_aw_unsub'] );
        $data  = $this->decode_unsubscribe_token( $token );
        if ( ! $data || empty( $data['email'] ) ) {
            wp_die( __( 'Invalid unsubscribe link.', 'kdna-ecommerce' ), '', [ 'response' => 400 ] );
        }

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . self::TABLE_UNSUBSCRIBES, [
            'email'       => $data['email'],
            'workflow_id' => $data['workflow_id'] ?? 0,
            'type'        => 'all',
        ] );

        // Update user meta if registered.
        $user = get_user_by( 'email', $data['email'] );
        if ( $user ) {
            update_user_meta( $user->ID, '_kdna_aw_opted_in', 'no' );
            do_action( 'kdna_aw_customer_opted_out', [ 'customer' => new \WC_Customer( $user->ID ) ] );
        }

        wp_die( __( 'You have been successfully unsubscribed.', 'kdna-ecommerce' ), __( 'Unsubscribed', 'kdna-ecommerce' ), [ 'response' => 200 ] );
    }

    private function get_unsubscribe_url( $email, $workflow_id = 0 ) {
        $token = base64_encode( wp_json_encode( [ 'email' => $email, 'workflow_id' => $workflow_id ] ) );
        return add_query_arg( 'kdna_aw_unsub', $token, home_url( '/' ) );
    }

    private function decode_unsubscribe_token( $token ) {
        $decoded = base64_decode( $token );
        if ( ! $decoded ) return null;
        return json_decode( $decoded, true );
    }

    private function is_unsubscribed( $email, $workflow_id = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_UNSUBSCRIBES;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE email = %s AND (type = 'all' OR workflow_id = %d) LIMIT 1",
            $email, $workflow_id
        ) );
    }

    // =========================================================================
    // Communication Preferences
    // =========================================================================

    public function add_communication_endpoint() {
        add_rewrite_endpoint( 'communication-preferences', EP_PAGES | EP_ROOT );
    }

    public function add_communication_menu_item( $items ) {
        $items['communication-preferences'] = __( 'Communication Preferences', 'kdna-ecommerce' );
        return $items;
    }

    public function render_communication_page() {
        $user_id  = get_current_user_id();
        $opted_in = get_user_meta( $user_id, '_kdna_aw_opted_in', true ) === 'yes';

        if ( isset( $_POST['kdna_aw_comm_save'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'kdna_aw_comm_prefs' ) ) {
            $new_val = ! empty( $_POST['kdna_aw_optin'] ) ? 'yes' : 'no';
            update_user_meta( $user_id, '_kdna_aw_opted_in', $new_val );
            $opted_in = $new_val === 'yes';
            wc_add_notice( __( 'Preferences saved.', 'kdna-ecommerce' ), 'success' );
        }
        ?>
        <form method="post">
            <?php wp_nonce_field( 'kdna_aw_comm_prefs' ); ?>
            <p>
                <label>
                    <input type="checkbox" name="kdna_aw_optin" value="1" <?php checked( $opted_in ); ?> />
                    <?php echo esc_html( $this->settings['optin_checkbox_text'] ?: __( 'I want to receive marketing communications', 'kdna-ecommerce' ) ); ?>
                </label>
            </p>
            <?php if ( $this->settings['communication_page_legal_text'] ) : ?>
                <div class="kdna-aw-legal-text"><?php echo wp_kses_post( $this->settings['communication_page_legal_text'] ); ?></div>
            <?php endif; ?>
            <p><button type="submit" name="kdna_aw_comm_save" class="woocommerce-Button button"><?php esc_html_e( 'Save Preferences', 'kdna-ecommerce' ); ?></button></p>
        </form>
        <?php
    }

    public function shortcode_communication_preferences( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in to manage your preferences.', 'kdna-ecommerce' ) . '</p>';
        }
        ob_start();
        $this->render_communication_page();
        return ob_get_clean();
    }

    public function shortcode_cart_recovery( $atts ) {
        $token = isset( $_GET['kdna_aw_recover'] ) ? sanitize_text_field( $_GET['kdna_aw_recover'] ) : '';
        if ( ! $token ) return '';

        global $wpdb;
        $cart = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::TABLE_CARTS . " WHERE cart_token = %s LIMIT 1",
            $token
        ) );

        if ( ! $cart ) return '<p>' . esc_html__( 'This cart is no longer available.', 'kdna-ecommerce' ) . '</p>';

        $cart_data = json_decode( $cart->cart_data, true );
        if ( ! empty( $cart_data['items'] ) ) {
            WC()->cart->empty_cart();
            foreach ( $cart_data['items'] as $item ) {
                WC()->cart->add_to_cart( $item['product_id'], $item['quantity'], $item['variation_id'] ?? 0 );
            }
            if ( ! empty( $cart_data['coupons'] ) ) {
                foreach ( $cart_data['coupons'] as $coupon ) {
                    WC()->cart->apply_coupon( $coupon );
                }
            }
            $wpdb->update( $wpdb->prefix . self::TABLE_CARTS, [ 'status' => 'recovered', 'recovered_at' => current_time( 'mysql' ) ], [ 'id' => $cart->id ] );
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }

        return '';
    }

    // =========================================================================
    // Conversion Tracking
    // =========================================================================

    public function track_conversion( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $email = $order->get_billing_email();
        if ( ! $email ) return;

        global $wpdb;
        $window = absint( $this->settings['conversion_window'] ) ?: 90;
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$window} days" ) );

        // Find recent logs for this customer.
        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT l.* FROM {$wpdb->prefix}" . self::TABLE_LOGS . " l
             WHERE l.created_at >= %s AND (
                 l.customer_id = %d OR l.customer_id IN (
                     SELECT ID FROM {$wpdb->users} WHERE user_email = %s
                 )
             )",
            $cutoff, $order->get_customer_id(), $email
        ) );

        foreach ( $logs as $log ) {
            $wpdb->insert( $wpdb->prefix . self::TABLE_CONVERSIONS, [
                'workflow_id' => $log->workflow_id,
                'log_id'      => $log->id,
                'order_id'    => $order_id,
                'customer_id' => $order->get_customer_id(),
                'order_total' => $order->get_total(),
            ] );
        }
    }

    // =========================================================================
    // Cron Scheduling
    // =========================================================================

    public function schedule_cron_events() {
        if ( ! function_exists( 'as_has_scheduled_action' ) ) return;

        if ( ! as_has_scheduled_action( 'kdna_aw_process_queue' ) ) {
            as_schedule_recurring_action( time(), 60, 'kdna_aw_process_queue', [], 'kdna-automatewoo' );
        }

        if ( $this->settings['abandoned_cart_enabled'] === 'yes' && ! as_has_scheduled_action( 'kdna_aw_check_abandoned_carts' ) ) {
            as_schedule_recurring_action( time(), 300, 'kdna_aw_check_abandoned_carts', [], 'kdna-automatewoo' );
        }

        if ( ! as_has_scheduled_action( 'kdna_aw_clean_inactive_carts' ) ) {
            as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'kdna_aw_clean_inactive_carts', [], 'kdna-automatewoo' );
        }

        if ( $this->settings['clean_expired_coupons'] === 'yes' && ! as_has_scheduled_action( 'kdna_aw_clean_expired_coupons' ) ) {
            as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'kdna_aw_clean_expired_coupons', [], 'kdna-automatewoo' );
        }
    }

    public function clean_expired_coupons() {
        $coupons = get_posts( [
            'post_type'      => 'shop_coupon',
            'posts_per_page' => 100,
            'meta_query'     => [
                [ 'key' => 'date_expires', 'value' => time(), 'compare' => '<', 'type' => 'NUMERIC' ],
            ],
        ] );
        foreach ( $coupons as $coupon ) {
            wp_trash_post( $coupon->ID );
        }
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    public function ajax_cancel_queue_item() {
        check_ajax_referer( 'kdna_aw_admin', '_wpnonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );
        $wpdb->update( $wpdb->prefix . self::TABLE_QUEUE, [ 'status' => 'cancelled' ], [ 'id' => $id ] );
        wp_send_json_success();
    }

    public function ajax_delete_cart() {
        check_ajax_referer( 'kdna_aw_admin', '_wpnonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );
        $wpdb->delete( $wpdb->prefix . self::TABLE_CARTS, [ 'id' => $id ] );
        wp_send_json_success();
    }

    // =========================================================================
    // Admin Pages (Queue, Logs, Carts)
    // =========================================================================

    public function render_queue_page() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_QUEUE;
        $items = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100" );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'AutomateWoo Queue', 'kdna-ecommerce' ); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Workflow', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Scheduled', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Created', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'kdna-ecommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $item ) : ?>
                    <tr>
                        <td><?php echo esc_html( $item->id ); ?></td>
                        <td><?php echo esc_html( get_the_title( $item->workflow_id ) ); ?></td>
                        <td><span class="kdna-aw-queue-status <?php echo esc_attr( $item->status ); ?>"><?php echo esc_html( ucfirst( $item->status ) ); ?></span></td>
                        <td><?php echo esc_html( $item->scheduled_at ); ?></td>
                        <td><?php echo esc_html( $item->created_at ); ?></td>
                        <td>
                            <?php if ( $item->status === 'pending' ) : ?>
                                <a href="#" class="kdna-aw-queue-cancel" data-id="<?php echo esc_attr( $item->id ); ?>"><?php esc_html_e( 'Cancel', 'kdna-ecommerce' ); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $items ) ) : ?>
                    <tr><td colspan="6"><?php esc_html_e( 'No queued events.', 'kdna-ecommerce' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_logs_page() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LOGS;
        $logs  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100" );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'AutomateWoo Logs', 'kdna-ecommerce' ); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Workflow', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Trigger', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Customer', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Order', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'kdna-ecommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log->id ); ?></td>
                        <td><?php echo esc_html( $log->workflow_name ); ?></td>
                        <td><?php echo esc_html( $log->trigger_name ); ?></td>
                        <td><?php echo $log->customer_id ? esc_html( '#' . $log->customer_id ) : '—'; ?></td>
                        <td><?php echo $log->order_id ? '<a href="' . esc_url( get_edit_post_link( $log->order_id ) ) . '">#' . esc_html( $log->order_id ) . '</a>' : '—'; ?></td>
                        <td><span class="kdna-aw-queue-status <?php echo esc_attr( $log->status ); ?>"><?php echo esc_html( ucfirst( $log->status ) ); ?></span></td>
                        <td><?php echo esc_html( $log->created_at ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $logs ) ) : ?>
                    <tr><td colspan="7"><?php esc_html_e( 'No logs.', 'kdna-ecommerce' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_carts_page() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_CARTS;
        $carts = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT 100" );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Abandoned Carts', 'kdna-ecommerce' ); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Items', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Last Updated', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'kdna-ecommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $carts as $cart ) :
                        $items = json_decode( $cart->cart_data, true );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $cart->id ); ?></td>
                        <td><?php echo esc_html( $cart->email ?: __( 'Guest', 'kdna-ecommerce' ) ); ?></td>
                        <td class="kdna-aw-cart-contents">
                            <ul>
                                <?php if ( ! empty( $items['items'] ) ) : foreach ( $items['items'] as $item ) : ?>
                                    <li><?php echo esc_html( $item['name'] ?? 'Product #' . $item['product_id'] ); ?> &times; <?php echo esc_html( $item['quantity'] ); ?></li>
                                <?php endforeach; endif; ?>
                            </ul>
                        </td>
                        <td><?php echo wc_price( $cart->cart_total ); ?></td>
                        <td><span class="kdna-aw-queue-status <?php echo esc_attr( $cart->status ); ?>"><?php echo esc_html( ucfirst( $cart->status ) ); ?></span></td>
                        <td><?php echo esc_html( $cart->updated_at ); ?></td>
                        <td>
                            <a href="#" class="kdna-aw-cart-delete" data-id="<?php echo esc_attr( $cart->id ); ?>"><?php esc_html_e( 'Delete', 'kdna-ecommerce' ); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $carts ) ) : ?>
                    <tr><td colspan="7"><?php esc_html_e( 'No carts tracked.', 'kdna-ecommerce' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // =========================================================================
    // REST API
    // =========================================================================

    public function register_rest_routes() {
        register_rest_route( 'kdna-aw/v1', '/workflows', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_workflows' ],
            'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
        ] );
        register_rest_route( 'kdna-aw/v1', '/queue', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_queue' ],
            'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
        ] );
        register_rest_route( 'kdna-aw/v1', '/logs', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_logs' ],
            'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
        ] );
        register_rest_route( 'kdna-aw/v1', '/unsubscribe', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_unsubscribe' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function rest_get_workflows( $request ) {
        $workflows = get_posts( [
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ] );
        $data = [];
        foreach ( $workflows as $w ) {
            $data[] = [
                'id'      => $w->ID,
                'title'   => $w->post_title,
                'status'  => get_post_meta( $w->ID, self::META_STATUS, true ),
                'trigger' => get_post_meta( $w->ID, self::META_TRIGGER, true ),
                'runs'    => (int) get_post_meta( $w->ID, self::META_RUN_COUNT, true ),
            ];
        }
        return rest_ensure_response( $data );
    }

    public function rest_get_queue( $request ) {
        global $wpdb;
        $items = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}" . self::TABLE_QUEUE . " ORDER BY created_at DESC LIMIT 100" );
        return rest_ensure_response( $items );
    }

    public function rest_get_logs( $request ) {
        global $wpdb;
        $logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}" . self::TABLE_LOGS . " ORDER BY created_at DESC LIMIT 100" );
        return rest_ensure_response( $logs );
    }

    public function rest_unsubscribe( $request ) {
        $email = sanitize_email( $request->get_param( 'email' ) );
        if ( ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', __( 'Invalid email address.', 'kdna-ecommerce' ), [ 'status' => 400 ] );
        }
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . self::TABLE_UNSUBSCRIBES, [
            'email' => $email,
            'type'  => 'all',
        ] );
        return rest_ensure_response( [ 'success' => true ] );
    }
}
