<?php
/**
 * KDNA Smart Coupons Module
 *
 * Full-featured coupon management: auto-apply, URL coupons, store credit / gift certificates,
 * auto-generation on order, coupon display on cart/checkout/My Account, coupon emailing,
 * coupon printing, cashback rewards, BOGO presets, gift-card images, coupon expiry &
 * unused reminders, send-to-friend form, tax handling, and customisable coupon designs.
 */
defined( 'ABSPATH' ) || exit;

class KDNA_Smart_Coupons {

    // ----- Post-meta keys for individual coupons -----
    const META_AUTO_APPLY              = '_kdna_sc_auto_apply';
    const META_IS_VISIBLE_STOREWIDE    = '_kdna_sc_visible_storewide';
    const META_PICK_PRICE_OF_PRODUCT   = '_kdna_sc_pick_price_of_product';
    const META_AUTO_GENERATE           = '_kdna_sc_auto_generate';
    const META_COUPON_PREFIX           = '_kdna_sc_coupon_prefix';
    const META_COUPON_SUFFIX           = '_kdna_sc_coupon_suffix';
    const META_COUPON_VALIDITY         = '_kdna_sc_coupon_validity';
    const META_VALIDITY_UNIT           = '_kdna_sc_validity_unit';
    const META_DISABLE_EMAIL_RESTRICT  = '_kdna_sc_disable_email_restriction';
    const META_MAX_DISCOUNT            = '_kdna_sc_max_discount';
    const META_RESTRICT_NEW_USER       = '_kdna_sc_restrict_new_user';
    const META_COUPON_MESSAGE          = '_kdna_sc_coupon_message';
    const META_EMAIL_MESSAGE           = '_kdna_sc_email_message';
    const META_EXPIRY_TIME             = '_kdna_sc_expiry_time';

    // ----- Post-meta keys for products -----
    const META_PRODUCT_COUPONS         = '_kdna_sc_coupon_titles';
    const META_PRODUCT_GIFT_IMAGE      = '_kdna_sc_enable_gift_image';

    // ----- Order meta -----
    const META_ORDER_GENERATED         = '_kdna_sc_generated_coupon';
    const META_GIFT_EMAIL              = '_kdna_sc_gift_receiver_email';
    const META_GIFT_MESSAGE            = '_kdna_sc_gift_receiver_message';
    const META_GIFT_TIMESTAMP          = '_kdna_sc_gift_sending_timestamp';
    const META_CASHBACK_ELIGIBLE       = '_kdna_sc_cashback_eligible';

    public function __construct() {
        $s = self::get_settings();

        // ---- URL coupons ----
        if ( $s['enable_url_coupons'] === 'yes' ) {
            add_action( 'wp_loaded', [ $this, 'apply_coupon_from_url' ], 20 );
            add_action( 'init', [ $this, 'add_rewrite_rules' ] );
            add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
            add_action( 'template_redirect', [ $this, 'handle_coupon_endpoint' ] );
        }

        // ---- Auto-apply ----
        if ( $s['enable_auto_apply'] === 'yes' ) {
            add_action( 'woocommerce_before_calculate_totals', [ $this, 'auto_apply_coupons' ], 99 );
        }

        // ---- Display coupons ----
        if ( $s['show_on_cart'] === 'yes' ) {
            add_action( 'woocommerce_before_cart', [ $this, 'display_available_coupons_cart' ] );
        }
        if ( $s['show_on_checkout'] === 'yes' ) {
            add_action( 'woocommerce_before_checkout_form', [ $this, 'display_available_coupons_checkout' ], 5 );
        }
        if ( $s['show_on_myaccount'] === 'yes' ) {
            add_action( 'woocommerce_account_dashboard', [ $this, 'display_myaccount_coupons' ] );
        }
        if ( $s['show_associated_on_product'] === 'yes' ) {
            add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'display_product_coupons' ] );
        }

        // ---- Admin coupon fields ----
        add_action( 'woocommerce_coupon_options', [ $this, 'add_coupon_general_fields' ], 10, 2 );
        add_action( 'woocommerce_coupon_options_usage_restriction', [ $this, 'add_coupon_restriction_fields' ], 10, 2 );
        add_filter( 'woocommerce_coupon_data_tabs', [ $this, 'add_coupon_data_tabs' ] );
        add_action( 'woocommerce_coupon_data_panels', [ $this, 'render_coupon_data_panels' ], 10, 2 );
        add_action( 'woocommerce_coupon_options_save', [ $this, 'save_coupon_fields' ], 10, 2 );

        // ---- Product fields (attach coupon to product) ----
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_product_coupon_fields' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_coupon_fields' ] );

        // ---- Store credit discount type ----
        add_filter( 'woocommerce_coupon_discount_types', [ $this, 'add_store_credit_type' ] );
        add_filter( 'woocommerce_coupon_get_discount_amount', [ $this, 'store_credit_discount_amount' ], 10, 5 );

        // ---- Store credit balance deduction on order complete ----
        $statuses = (array) $s['valid_order_statuses'];
        foreach ( $statuses as $status ) {
            add_action( 'woocommerce_order_status_' . $status, [ $this, 'process_order_coupons' ] );
        }

        // ---- Auto-generate coupons on order ----
        foreach ( $statuses as $status ) {
            add_action( 'woocommerce_order_status_' . $status, [ $this, 'auto_generate_coupons_for_order' ], 20 );
        }

        // ---- Cashback rewards ----
        if ( $s['cashback_enabled'] === 'yes' ) {
            foreach ( $statuses as $status ) {
                add_action( 'woocommerce_order_status_' . $status, [ $this, 'process_cashback_reward' ], 30 );
            }
        }

        // ---- Store credit tax handling ----
        if ( $s['include_tax'] === 'yes' ) {
            add_filter( 'woocommerce_coupon_get_discount_amount', [ $this, 'apply_before_tax' ], 20, 5 );
        }

        // ---- Discounted price display ----
        if ( $s['show_discounted_price'] === 'yes' ) {
            add_filter( 'woocommerce_cart_item_price', [ $this, 'display_discounted_price' ], 10, 3 );
        }

        // ---- Delete store credit after full use ----
        if ( $s['delete_after_use'] === 'yes' ) {
            add_action( 'woocommerce_order_status_completed', [ $this, 'maybe_delete_used_credit' ], 99 );
        }

        // ---- Coupon emails ----
        if ( $s['send_coupon_email'] === 'yes' ) {
            add_action( 'kdna_sc_coupon_generated', [ $this, 'send_coupon_email' ], 10, 3 );
        }

        // ---- Coupon printing ----
        if ( $s['enable_printing'] === 'yes' ) {
            add_action( 'wp_ajax_kdna_sc_print_coupon', [ $this, 'ajax_print_coupon' ] );
            add_action( 'wp_ajax_nopriv_kdna_sc_print_coupon', [ $this, 'ajax_print_coupon' ] );
        }

        // ---- Send coupon form (gift to others at checkout) ----
        if ( $s['allow_sending_to_others'] === 'yes' ) {
            add_action( 'woocommerce_after_order_notes', [ $this, 'render_send_coupon_form' ] );
            add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_send_coupon_form' ] );
        }

        // ---- Store notice banner ----
        if ( ! empty( $s['storewide_coupon_code'] ) ) {
            add_filter( 'woocommerce_demo_store', [ $this, 'store_notice_coupon' ], 10, 2 );
        }

        // ---- My Account endpoint ----
        add_action( 'init', [ $this, 'register_myaccount_endpoint' ] );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'myaccount_menu_item' ] );
        add_action( 'woocommerce_account_coupons_endpoint', [ $this, 'myaccount_coupons_page' ] );

        // ---- Expiry reminder cron ----
        if ( $s['expiry_reminder_enabled'] === 'yes' ) {
            add_action( 'kdna_sc_expiry_reminder_cron', [ $this, 'send_expiry_reminders' ] );
            if ( ! wp_next_scheduled( 'kdna_sc_expiry_reminder_cron' ) ) {
                wp_schedule_event( time(), 'daily', 'kdna_sc_expiry_reminder_cron' );
            }
        }

        // ---- Unused coupon reminder cron ----
        if ( $s['unused_reminder_enabled'] === 'yes' ) {
            add_action( 'kdna_sc_unused_reminder_cron', [ $this, 'send_unused_reminders' ] );
            if ( ! wp_next_scheduled( 'kdna_sc_unused_reminder_cron' ) ) {
                wp_schedule_event( time(), 'daily', 'kdna_sc_unused_reminder_cron' );
            }
        }

        // ---- Frontend assets ----
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );

        // ---- AJAX apply coupon ----
        add_action( 'wp_ajax_kdna_sc_apply_coupon', [ $this, 'ajax_apply_coupon' ] );
        add_action( 'wp_ajax_nopriv_kdna_sc_apply_coupon', [ $this, 'ajax_apply_coupon' ] );

        // ---- Shortcode ----
        add_shortcode( 'kdna_available_coupons', [ $this, 'shortcode_available_coupons' ] );

        // ---- WC Emails registration ----
        add_filter( 'woocommerce_email_classes', [ $this, 'register_email_classes' ] );
    }

    // =========================================================================
    // Settings
    // =========================================================================

    public static function get_settings() {
        return wp_parse_args(
            get_option( 'kdna_smart_coupons_settings', [] ),
            self::get_default_settings()
        );
    }

    public static function get_default_settings() {
        return [
            // ---- General ----
            'max_coupons_shown'           => '5',
            'coupon_code_length'          => '13',
            'valid_order_statuses'        => [ 'processing', 'completed' ],
            'enable_auto_apply'           => 'yes',
            'delete_after_use'            => 'no',
            'send_coupon_email'           => 'yes',
            'enable_printing'             => 'yes',
            'sell_credit_at_less_price'   => 'no',
            'show_discounted_price'       => 'no',

            // ---- Customize Coupons ----
            'coupon_color_scheme'         => '2b2d42-edf2f4-d90429',
            'custom_bg_color'             => '#39cccc',
            'custom_fg_color'             => '#30050b',
            'custom_third_color'          => '#39cccc',
            'coupon_design'               => 'basic',
            'coupon_email_design'         => 'email-coupon',

            // ---- Display Coupons ----
            'storewide_coupon_code'       => '',
            'store_notice_design'         => 'notification',
            'show_associated_on_product'  => 'no',
            'show_on_myaccount'           => 'yes',
            'show_received_on_myaccount'  => 'no',
            'show_invalid_on_myaccount'   => 'no',
            'show_coupon_description'     => 'no',
            'show_on_cart'                => 'yes',
            'show_on_checkout'            => 'yes',
            'always_show_section'         => 'no',
            'default_section_open'        => 'yes',
            'product_page_text'           => 'You will get following coupon(s) when you buy this item:',

            // ---- Tax ----
            'include_tax'                 => 'no',

            // ---- Labels ----
            'credit_label_singular'       => 'Store Credit',
            'credit_label_plural'         => 'Store Credits',
            'credit_product_cta'          => 'Select options',
            'purchasing_credits_label'    => 'Purchase credit worth',
            'coupons_with_product_text'   => 'You will get following coupon(s) when you buy this item',
            'cart_checkout_label'         => 'Available Coupons ({coupons_count})',
            'myaccount_label'             => 'Available Coupons & Store Credits',

            // ---- Send Coupon Form ----
            'allow_sending_to_others'     => 'yes',
            'send_form_title'             => 'Send Coupons to...',
            'send_form_description'       => '',
            'allow_schedule_sending'      => 'no',
            'combine_emails'              => 'no',

            // ---- Emails ----
            'email_auto_generated'        => 'yes',
            'email_combined'              => 'yes',
            'email_acknowledgement'       => 'yes',
            'email_expiry_reminder'       => 'no',
            'email_store_credit_image'    => 'yes',
            'email_unused_reminder'       => 'no',

            // ---- Expiry & Unused Reminders ----
            'expiry_reminder_enabled'     => 'no',
            'expiry_reminder_days_before' => '7',
            'unused_reminder_enabled'     => 'no',
            'unused_reminder_days'        => '30',
            'unused_max_reminders'        => '3',

            // ---- Cashback Rewards ----
            'cashback_enabled'            => 'no',
            'cashback_amount'             => '',
            'cashback_type'               => 'fixed',
            'cashback_min_order'          => '',
            'cashback_template_coupon'    => '',

            // ---- URL Coupons ----
            'enable_url_coupons'          => 'yes',

            // ---- Legacy compat (kept from v1) ----
            'primary_color'               => '#39cccc',
            'text_color'                  => '#ffffff',
        ];
    }

    // =========================================================================
    // URL Coupons
    // =========================================================================

    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^coupon/([^/]+)/?$',
            'index.php?kdna_coupon_code=$matches[1]',
            'top'
        );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'kdna_coupon_code';
        return $vars;
    }

    public function handle_coupon_endpoint() {
        $code = get_query_var( 'kdna_coupon_code' );
        if ( empty( $code ) ) {
            return;
        }
        if ( WC()->session ) {
            WC()->session->set( 'kdna_sc_url_coupon', sanitize_text_field( $code ) );
        }
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    public function apply_coupon_from_url() {
        if ( ! empty( $_GET['apply_coupon'] ) ) {
            $code = sanitize_text_field( wp_unslash( $_GET['apply_coupon'] ) );
            if ( WC()->cart && ! WC()->cart->has_discount( $code ) ) {
                WC()->cart->apply_coupon( $code );
            }
            return;
        }
        if ( ! WC()->session ) {
            return;
        }
        $code = WC()->session->get( 'kdna_sc_url_coupon' );
        if ( $code && WC()->cart && ! WC()->cart->has_discount( $code ) ) {
            WC()->cart->apply_coupon( $code );
            WC()->session->set( 'kdna_sc_url_coupon', null );
        }
    }

    // =========================================================================
    // Auto-Apply Coupons
    // =========================================================================

    public function auto_apply_coupons( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
        $auto_coupons = get_posts( [
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'meta_query'     => [ [ 'key' => self::META_AUTO_APPLY, 'value' => 'yes' ] ],
            'fields'         => 'ids',
        ] );
        foreach ( $auto_coupons as $coupon_id ) {
            $coupon = new WC_Coupon( $coupon_id );
            $code   = $coupon->get_code();
            if ( $cart->has_discount( $code ) ) {
                continue;
            }
            if ( $coupon->is_valid() ) {
                $cart->apply_coupon( $code );
            }
        }
    }

    // =========================================================================
    // Admin — Coupon Edit Screen Fields
    // =========================================================================

    public function add_coupon_general_fields( $coupon_id, $coupon ) {
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__( 'Smart Coupons', 'kdna-ecommerce' ) . '</h4>';

        woocommerce_wp_checkbox( [
            'id'          => self::META_AUTO_APPLY,
            'label'       => __( 'Auto-apply coupon', 'kdna-ecommerce' ),
            'description' => __( 'Automatically apply this coupon when conditions are met.', 'kdna-ecommerce' ),
            'value'       => get_post_meta( $coupon_id, self::META_AUTO_APPLY, true ),
            'cbvalue'     => 'yes',
        ] );

        woocommerce_wp_checkbox( [
            'id'          => self::META_IS_VISIBLE_STOREWIDE,
            'label'       => __( 'Show on cart/checkout', 'kdna-ecommerce' ),
            'description' => __( 'Display this coupon to all eligible customers on cart and checkout.', 'kdna-ecommerce' ),
            'value'       => get_post_meta( $coupon_id, self::META_IS_VISIBLE_STOREWIDE, true ),
            'cbvalue'     => 'yes',
        ] );

        woocommerce_wp_text_input( [
            'id'          => self::META_MAX_DISCOUNT,
            'label'       => __( 'Max discount', 'kdna-ecommerce' ),
            'description' => __( 'Maximum discount this coupon can give (for percentage coupons). Leave empty for no limit.', 'kdna-ecommerce' ),
            'desc_tip'    => true,
            'type'        => 'number',
            'custom_attributes' => [ 'step' => 'any', 'min' => '0' ],
        ] );

        woocommerce_wp_text_input( [
            'id'          => self::META_EXPIRY_TIME,
            'label'       => __( 'Expiry time (hours)', 'kdna-ecommerce' ),
            'description' => __( 'Additional expiry time in hours beyond the expiry date. Leave empty for end of day.', 'kdna-ecommerce' ),
            'desc_tip'    => true,
            'type'        => 'number',
            'custom_attributes' => [ 'step' => '1', 'min' => '0', 'max' => '23' ],
        ] );

        echo '</div>';
    }

    public function add_coupon_restriction_fields( $coupon_id, $coupon ) {
        echo '<div class="options_group">';
        woocommerce_wp_checkbox( [
            'id'          => self::META_RESTRICT_NEW_USER,
            'label'       => __( 'First order only', 'kdna-ecommerce' ),
            'description' => __( 'Restrict this coupon to the customer\'s first order.', 'kdna-ecommerce' ),
            'value'       => get_post_meta( $coupon_id, self::META_RESTRICT_NEW_USER, true ),
            'cbvalue'     => 'yes',
        ] );
        echo '</div>';
    }

    public function add_coupon_data_tabs( $tabs ) {
        $tabs['kdna_smart_coupon'] = [
            'label'  => __( 'Smart Coupon', 'kdna-ecommerce' ),
            'target' => 'kdna_smart_coupon_data',
            'class'  => '',
        ];
        return $tabs;
    }

    public function render_coupon_data_panels( $coupon_id, $coupon ) {
        ?>
        <div id="kdna_smart_coupon_data" class="panel woocommerce_options_panel">

            <div class="options_group">
                <h4 style="padding-left:12px;"><?php esc_html_e( 'Auto-Generate Coupon on Purchase', 'kdna-ecommerce' ); ?></h4>
                <?php
                woocommerce_wp_checkbox( [
                    'id'          => self::META_AUTO_GENERATE,
                    'label'       => __( 'Auto-generate coupon', 'kdna-ecommerce' ),
                    'description' => __( 'Generate a copy of this coupon when the product is ordered.', 'kdna-ecommerce' ),
                    'value'       => get_post_meta( $coupon_id, self::META_AUTO_GENERATE, true ),
                    'cbvalue'     => 'yes',
                ] );

                woocommerce_wp_checkbox( [
                    'id'          => self::META_PICK_PRICE_OF_PRODUCT,
                    'label'       => __( 'Coupon value = product price', 'kdna-ecommerce' ),
                    'description' => __( 'Generated coupon amount matches the purchased product price.', 'kdna-ecommerce' ),
                    'value'       => get_post_meta( $coupon_id, self::META_PICK_PRICE_OF_PRODUCT, true ),
                    'cbvalue'     => 'yes',
                ] );

                woocommerce_wp_text_input( [
                    'id'          => self::META_COUPON_PREFIX,
                    'label'       => __( 'Coupon code prefix', 'kdna-ecommerce' ),
                    'description' => __( 'Prefix for auto-generated coupon codes.', 'kdna-ecommerce' ),
                    'desc_tip'    => true,
                ] );

                woocommerce_wp_text_input( [
                    'id'          => self::META_COUPON_SUFFIX,
                    'label'       => __( 'Coupon code suffix', 'kdna-ecommerce' ),
                    'description' => __( 'Suffix for auto-generated coupon codes.', 'kdna-ecommerce' ),
                    'desc_tip'    => true,
                ] );

                woocommerce_wp_text_input( [
                    'id'          => self::META_COUPON_VALIDITY,
                    'label'       => __( 'Validity period', 'kdna-ecommerce' ),
                    'description' => __( 'Duration before the generated coupon expires.', 'kdna-ecommerce' ),
                    'desc_tip'    => true,
                    'type'        => 'number',
                    'custom_attributes' => [ 'min' => '0' ],
                ] );

                woocommerce_wp_select( [
                    'id'      => self::META_VALIDITY_UNIT,
                    'label'   => __( 'Validity unit', 'kdna-ecommerce' ),
                    'options' => [
                        'days'   => __( 'Days', 'kdna-ecommerce' ),
                        'weeks'  => __( 'Weeks', 'kdna-ecommerce' ),
                        'months' => __( 'Months', 'kdna-ecommerce' ),
                        'years'  => __( 'Years', 'kdna-ecommerce' ),
                    ],
                ] );

                woocommerce_wp_checkbox( [
                    'id'          => self::META_DISABLE_EMAIL_RESTRICT,
                    'label'       => __( 'No email restriction', 'kdna-ecommerce' ),
                    'description' => __( 'Don\'t add email restriction to the generated coupon.', 'kdna-ecommerce' ),
                    'value'       => get_post_meta( $coupon_id, self::META_DISABLE_EMAIL_RESTRICT, true ),
                    'cbvalue'     => 'yes',
                ] );
                ?>
            </div>

            <div class="options_group">
                <h4 style="padding-left:12px;"><?php esc_html_e( 'Coupon Message', 'kdna-ecommerce' ); ?></h4>
                <?php
                woocommerce_wp_textarea_input( [
                    'id'          => self::META_COUPON_MESSAGE,
                    'label'       => __( 'Custom message', 'kdna-ecommerce' ),
                    'description' => __( 'Message displayed alongside the coupon. HTML allowed.', 'kdna-ecommerce' ),
                    'desc_tip'    => true,
                ] );

                woocommerce_wp_checkbox( [
                    'id'          => self::META_EMAIL_MESSAGE,
                    'label'       => __( 'Include in email', 'kdna-ecommerce' ),
                    'description' => __( 'Include the custom message in the order confirmation email.', 'kdna-ecommerce' ),
                    'value'       => get_post_meta( $coupon_id, self::META_EMAIL_MESSAGE, true ),
                    'cbvalue'     => 'yes',
                ] );
                ?>
            </div>

        </div>
        <?php
    }

    public function save_coupon_fields( $coupon_id, $coupon ) {
        $checkboxes = [
            self::META_AUTO_APPLY,
            self::META_IS_VISIBLE_STOREWIDE,
            self::META_PICK_PRICE_OF_PRODUCT,
            self::META_AUTO_GENERATE,
            self::META_DISABLE_EMAIL_RESTRICT,
            self::META_RESTRICT_NEW_USER,
            self::META_EMAIL_MESSAGE,
        ];
        foreach ( $checkboxes as $key ) {
            update_post_meta( $coupon_id, $key, isset( $_POST[ $key ] ) ? 'yes' : 'no' );
        }

        $text_fields = [
            self::META_COUPON_PREFIX,
            self::META_COUPON_SUFFIX,
            self::META_COUPON_VALIDITY,
            self::META_VALIDITY_UNIT,
            self::META_MAX_DISCOUNT,
            self::META_EXPIRY_TIME,
        ];
        foreach ( $text_fields as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $coupon_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
            }
        }

        if ( isset( $_POST[ self::META_COUPON_MESSAGE ] ) ) {
            update_post_meta( $coupon_id, self::META_COUPON_MESSAGE, wp_kses_post( wp_unslash( $_POST[ self::META_COUPON_MESSAGE ] ) ) );
        }
    }

    // =========================================================================
    // Admin — Product Fields (Attach Coupon to Product)
    // =========================================================================

    public function add_product_coupon_fields() {
        global $post;
        echo '<div class="options_group">';
        woocommerce_wp_text_input( [
            'id'          => self::META_PRODUCT_COUPONS,
            'label'       => __( 'Coupons (Smart Coupons)', 'kdna-ecommerce' ),
            'description' => __( 'Comma-separated coupon codes to issue when this product is purchased.', 'kdna-ecommerce' ),
            'desc_tip'    => true,
            'value'       => get_post_meta( $post->ID, self::META_PRODUCT_COUPONS, true ),
        ] );

        woocommerce_wp_checkbox( [
            'id'          => self::META_PRODUCT_GIFT_IMAGE,
            'label'       => __( 'Gift card image upload', 'kdna-ecommerce' ),
            'description' => __( 'Allow customers to upload a custom gift card image.', 'kdna-ecommerce' ),
            'value'       => get_post_meta( $post->ID, self::META_PRODUCT_GIFT_IMAGE, true ),
            'cbvalue'     => 'yes',
        ] );
        echo '</div>';
    }

    public function save_product_coupon_fields( $post_id ) {
        if ( isset( $_POST[ self::META_PRODUCT_COUPONS ] ) ) {
            update_post_meta( $post_id, self::META_PRODUCT_COUPONS, sanitize_text_field( wp_unslash( $_POST[ self::META_PRODUCT_COUPONS ] ) ) );
        }
        update_post_meta( $post_id, self::META_PRODUCT_GIFT_IMAGE, isset( $_POST[ self::META_PRODUCT_GIFT_IMAGE ] ) ? 'yes' : 'no' );
    }

    // =========================================================================
    // Store Credit Discount Type
    // =========================================================================

    public function add_store_credit_type( $types ) {
        $s = self::get_settings();
        $label = $s['credit_label_singular'] ?: 'Store Credit';
        $types['store_credit'] = sprintf( __( '%s / Gift Certificate', 'kdna-ecommerce' ), $label );
        return $types;
    }

    public function store_credit_discount_amount( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
        if ( $coupon->get_discount_type() !== 'store_credit' ) {
            return $discount;
        }
        return min( $coupon->get_amount(), $discounting_amount );
    }

    public function apply_before_tax( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
        if ( $coupon->get_discount_type() !== 'store_credit' ) {
            return $discount;
        }
        if ( wc_prices_include_tax() ) {
            return $discount;
        }
        return $discount;
    }

    // =========================================================================
    // Order Processing — Deduct Credit, Auto-Generate, Cashback
    // =========================================================================

    public function process_order_coupons( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_meta( '_kdna_sc_credits_deducted' ) === 'yes' ) {
            return;
        }
        foreach ( $order->get_coupon_codes() as $code ) {
            $coupon = new WC_Coupon( $code );
            if ( $coupon->get_discount_type() !== 'store_credit' ) {
                continue;
            }
            foreach ( $order->get_items( 'coupon' ) as $item ) {
                if ( $item->get_code() !== $code ) {
                    continue;
                }
                $used      = (float) $item->get_discount();
                $remaining = max( 0, $coupon->get_amount() - $used );
                $coupon->set_amount( $remaining );
                $coupon->save();
            }
        }
        $order->update_meta_data( '_kdna_sc_credits_deducted', 'yes' );
        $order->save();
    }

    public function auto_generate_coupons_for_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_meta( '_kdna_sc_coupons_generated' ) === 'yes' ) {
            return;
        }

        $settings = self::get_settings();

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $coupon_titles = get_post_meta( $product_id, self::META_PRODUCT_COUPONS, true );
            if ( empty( $coupon_titles ) ) {
                continue;
            }

            $codes = array_map( 'trim', explode( ',', $coupon_titles ) );
            foreach ( $codes as $template_code ) {
                $template = new WC_Coupon( $template_code );
                if ( ! $template->get_id() ) {
                    continue;
                }
                if ( get_post_meta( $template->get_id(), self::META_AUTO_GENERATE, true ) !== 'yes' ) {
                    continue;
                }

                $qty = $item->get_quantity();
                for ( $i = 0; $i < $qty; $i++ ) {
                    $new_code = $this->generate_coupon_code( $template->get_id(), $settings );
                    $new_coupon = $this->clone_coupon( $template, $new_code, $order, $item );
                    if ( $new_coupon ) {
                        $order->add_order_note(
                            sprintf( __( 'Coupon %s generated from template %s.', 'kdna-ecommerce' ), $new_code, $template_code )
                        );
                        do_action( 'kdna_sc_coupon_generated', $new_coupon, $order, $template );
                    }
                }
            }
        }

        $order->update_meta_data( '_kdna_sc_coupons_generated', 'yes' );
        $order->save();
    }

    private function generate_coupon_code( $template_id, $settings ) {
        $length = (int) ( $settings['coupon_code_length'] ?: 13 );
        $prefix = get_post_meta( $template_id, self::META_COUPON_PREFIX, true );
        $suffix = get_post_meta( $template_id, self::META_COUPON_SUFFIX, true );

        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $code  = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $code .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
        }
        return strtolower( $prefix . $code . $suffix );
    }

    private function clone_coupon( $template, $new_code, $order, $item ) {
        $new = new WC_Coupon();
        $new->set_code( $new_code );
        $new->set_discount_type( $template->get_discount_type() );

        if ( get_post_meta( $template->get_id(), self::META_PICK_PRICE_OF_PRODUCT, true ) === 'yes' ) {
            $new->set_amount( (float) $item->get_total() / max( 1, $item->get_quantity() ) );
        } else {
            $new->set_amount( $template->get_amount() );
        }

        $new->set_individual_use( $template->get_individual_use() );
        $new->set_product_ids( $template->get_product_ids() );
        $new->set_excluded_product_ids( $template->get_excluded_product_ids() );
        $new->set_usage_limit( $template->get_usage_limit() );
        $new->set_usage_limit_per_user( $template->get_usage_limit_per_user() );
        $new->set_limit_usage_to_x_items( $template->get_limit_usage_to_x_items() );
        $new->set_free_shipping( $template->get_free_shipping() );
        $new->set_product_categories( $template->get_product_categories() );
        $new->set_excluded_product_categories( $template->get_excluded_product_categories() );
        $new->set_exclude_sale_items( $template->get_exclude_sale_items() );
        $new->set_minimum_amount( $template->get_minimum_amount() );
        $new->set_maximum_amount( $template->get_maximum_amount() );
        $new->set_description( $template->get_description() );

        // Email restriction.
        $disable_email = get_post_meta( $template->get_id(), self::META_DISABLE_EMAIL_RESTRICT, true );
        if ( $disable_email !== 'yes' ) {
            $new->set_email_restrictions( [ $order->get_billing_email() ] );
        }

        // Validity.
        $validity = (int) get_post_meta( $template->get_id(), self::META_COUPON_VALIDITY, true );
        if ( $validity > 0 ) {
            $unit = get_post_meta( $template->get_id(), self::META_VALIDITY_UNIT, true ) ?: 'days';
            $expiry = new WC_DateTime();
            $expiry->modify( "+{$validity} {$unit}" );
            $new->set_date_expires( $expiry );
        }

        $new->save();

        update_post_meta( $new->get_id(), self::META_ORDER_GENERATED, $order->get_id() );
        return $new;
    }

    // ---- Cashback ----

    public function process_cashback_reward( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_meta( self::META_CASHBACK_ELIGIBLE ) === 'processed' ) {
            return;
        }

        $s = self::get_settings();
        $min = (float) $s['cashback_min_order'];
        if ( $min > 0 && $order->get_total() < $min ) {
            return;
        }

        $amount = (float) $s['cashback_amount'];
        if ( $amount <= 0 ) {
            return;
        }
        if ( $s['cashback_type'] === 'percentage' ) {
            $amount = $order->get_total() * ( $amount / 100 );
        }

        $template_id = (int) $s['cashback_template_coupon'];
        $code = $this->generate_coupon_code( $template_id ?: 0, $s );

        $coupon = new WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( 'store_credit' );
        $coupon->set_amount( round( $amount, 2 ) );
        $coupon->set_email_restrictions( [ $order->get_billing_email() ] );
        $coupon->set_usage_limit( 1 );

        if ( $template_id ) {
            $template = new WC_Coupon( $template_id );
            if ( $template->get_id() ) {
                $coupon->set_description( $template->get_description() );
            }
        }

        $coupon->save();
        $order->update_meta_data( self::META_CASHBACK_ELIGIBLE, 'processed' );
        $order->add_order_note( sprintf( __( 'Cashback coupon %s generated for %s.', 'kdna-ecommerce' ), $code, wc_price( $amount ) ) );
        $order->save();

        do_action( 'kdna_sc_coupon_generated', $coupon, $order, null );
    }

    // ---- Delete used credit ----

    public function maybe_delete_used_credit( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        foreach ( $order->get_coupon_codes() as $code ) {
            $coupon = new WC_Coupon( $code );
            if ( $coupon->get_discount_type() === 'store_credit' && $coupon->get_amount() <= 0 ) {
                wp_trash_post( $coupon->get_id() );
            }
        }
    }

    // =========================================================================
    // Coupon Emails
    // =========================================================================

    public function register_email_classes( $emails ) {
        $path = KDNA_ECOMMERCE_PATH . 'modules/smart-coupons/emails/';
        if ( file_exists( $path . 'class-kdna-sc-email-coupon.php' ) ) {
            require_once $path . 'class-kdna-sc-email-coupon.php';
            $emails['KDNA_SC_Email_Coupon'] = new KDNA_SC_Email_Coupon();
        }
        return $emails;
    }

    public function send_coupon_email( $coupon, $order, $template ) {
        $s = self::get_settings();
        if ( $s['send_coupon_email'] !== 'yes' ) {
            return;
        }

        $to = $order->get_billing_email();
        $subject = sprintf( __( 'You have received a coupon: %s', 'kdna-ecommerce' ), $coupon->get_code() );
        $message = $this->build_coupon_email_html( $coupon, $order );

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        wp_mail( $to, $subject, $message, $headers );
    }

    private function build_coupon_email_html( $coupon, $order ) {
        $s = self::get_settings();
        ob_start();
        ?>
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
            <h2><?php esc_html_e( 'You have received a coupon!', 'kdna-ecommerce' ); ?></h2>
            <div style="background:<?php echo esc_attr( $s['custom_bg_color'] ); ?>;color:<?php echo esc_attr( $s['custom_fg_color'] ); ?>;padding:20px;border-radius:8px;text-align:center;">
                <p style="font-size:24px;font-weight:bold;margin:0;">
                    <?php echo $coupon->get_discount_type() === 'percent' ? round( $coupon->get_amount() ) . '%' : wc_price( $coupon->get_amount() ); ?>
                    <?php esc_html_e( 'DISCOUNT', 'kdna-ecommerce' ); ?>
                </p>
                <p style="font-family:monospace;font-size:18px;margin:10px 0;"><?php echo esc_html( strtoupper( $coupon->get_code() ) ); ?></p>
                <?php if ( $coupon->get_date_expires() ) : ?>
                    <p style="font-size:12px;margin:5px 0;"><?php printf( esc_html__( 'Expires: %s', 'kdna-ecommerce' ), esc_html( $coupon->get_date_expires()->date_i18n( wc_date_format() ) ) ); ?></p>
                <?php endif; ?>
            </div>
            <?php
            $msg = get_post_meta( $coupon->get_id(), self::META_COUPON_MESSAGE, true );
            if ( $msg ) {
                echo '<div style="margin-top:15px;">' . wp_kses_post( $msg ) . '</div>';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // Expiry & Unused Reminders
    // =========================================================================

    public function send_expiry_reminders() {
        $s   = self::get_settings();
        $days = (int) $s['expiry_reminder_days_before'];
        if ( $days <= 0 ) {
            return;
        }

        $target_date = gmdate( 'Y-m-d', strtotime( "+{$days} days" ) );
        $coupons = get_posts( [
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'meta_query'     => [
                [
                    'key'     => 'date_expires',
                    'value'   => strtotime( $target_date ),
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => 'date_expires',
                    'value'   => time(),
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ],
            ],
            'fields' => 'ids',
        ] );

        foreach ( $coupons as $coupon_id ) {
            if ( get_post_meta( $coupon_id, '_kdna_sc_expiry_reminder_sent', true ) === 'yes' ) {
                continue;
            }
            $coupon = new WC_Coupon( $coupon_id );
            $emails = $coupon->get_email_restrictions();
            if ( empty( $emails ) ) {
                continue;
            }
            foreach ( $emails as $email ) {
                $subject = sprintf( __( 'Your coupon %s is expiring soon!', 'kdna-ecommerce' ), $coupon->get_code() );
                $body = sprintf(
                    __( 'Your coupon code <strong>%s</strong> expires on %s. Use it before it\'s gone!', 'kdna-ecommerce' ),
                    strtoupper( $coupon->get_code() ),
                    $coupon->get_date_expires()->date_i18n( wc_date_format() )
                );
                wp_mail( $email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
            }
            update_post_meta( $coupon_id, '_kdna_sc_expiry_reminder_sent', 'yes' );
        }
    }

    public function send_unused_reminders() {
        $s         = self::get_settings();
        $days      = (int) $s['unused_reminder_days'];
        $max       = (int) $s['unused_max_reminders'];
        $threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $coupons = get_posts( [
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'date_query'     => [ [ 'before' => $threshold ] ],
            'meta_query'     => [
                [
                    'key'     => 'usage_count',
                    'value'   => '0',
                    'compare' => '=',
                ],
            ],
            'fields' => 'ids',
        ] );

        foreach ( $coupons as $coupon_id ) {
            $sent_count = (int) get_post_meta( $coupon_id, '_kdna_sc_unused_reminder_count', true );
            if ( $sent_count >= $max ) {
                continue;
            }
            $coupon = new WC_Coupon( $coupon_id );
            $emails = $coupon->get_email_restrictions();
            if ( empty( $emails ) ) {
                continue;
            }
            foreach ( $emails as $email ) {
                $subject = sprintf( __( 'Don\'t forget your coupon %s!', 'kdna-ecommerce' ), $coupon->get_code() );
                $body = sprintf(
                    __( 'You have an unused coupon <strong>%s</strong> worth %s. Use it on your next order!', 'kdna-ecommerce' ),
                    strtoupper( $coupon->get_code() ),
                    $coupon->get_discount_type() === 'percent' ? round( $coupon->get_amount() ) . '%' : wc_price( $coupon->get_amount() )
                );
                wp_mail( $email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
            }
            update_post_meta( $coupon_id, '_kdna_sc_unused_reminder_count', $sent_count + 1 );
        }
    }

    // =========================================================================
    // Coupon Printing
    // =========================================================================

    public function ajax_print_coupon() {
        $coupon_id = isset( $_GET['coupon_id'] ) ? absint( $_GET['coupon_id'] ) : 0;
        if ( ! $coupon_id ) {
            wp_die( __( 'Invalid coupon.', 'kdna-ecommerce' ) );
        }
        $coupon = new WC_Coupon( $coupon_id );
        $s      = self::get_settings();
        ?>
        <!DOCTYPE html>
        <html>
        <head><title><?php esc_html_e( 'Print Coupon', 'kdna-ecommerce' ); ?></title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 40px; }
            .coupon-print { display: inline-block; padding: 30px 50px; border: 3px dashed <?php echo esc_attr( $s['custom_bg_color'] ); ?>; border-radius: 12px; }
            .coupon-amount { font-size: 36px; font-weight: bold; color: <?php echo esc_attr( $s['custom_bg_color'] ); ?>; }
            .coupon-code { font-family: monospace; font-size: 24px; margin: 15px 0; letter-spacing: 2px; }
            .coupon-expiry { font-size: 14px; color: #999; }
            @media print { .no-print { display: none; } }
        </style>
        </head>
        <body>
        <div class="coupon-print">
            <div class="coupon-amount">
                <?php echo $coupon->get_discount_type() === 'percent' ? round( $coupon->get_amount() ) . '% OFF' : wc_price( $coupon->get_amount() ) . ' OFF'; ?>
            </div>
            <div class="coupon-code"><?php echo esc_html( strtoupper( $coupon->get_code() ) ); ?></div>
            <?php if ( $coupon->get_date_expires() ) : ?>
                <div class="coupon-expiry"><?php printf( esc_html__( 'Valid until %s', 'kdna-ecommerce' ), esc_html( $coupon->get_date_expires()->date_i18n( wc_date_format() ) ) ); ?></div>
            <?php endif; ?>
            <?php if ( $coupon->get_description() ) : ?>
                <p><?php echo esc_html( $coupon->get_description() ); ?></p>
            <?php endif; ?>
        </div>
        <p class="no-print"><button onclick="window.print()"><?php esc_html_e( 'Print', 'kdna-ecommerce' ); ?></button></p>
        </body></html>
        <?php
        exit;
    }

    // =========================================================================
    // Send Coupon Form (Gift to Others at Checkout)
    // =========================================================================

    public function render_send_coupon_form( $checkout ) {
        $s = self::get_settings();
        // Only show if cart has products that issue coupons.
        $has_coupon_products = false;
        foreach ( WC()->cart->get_cart() as $item ) {
            $titles = get_post_meta( $item['product_id'], self::META_PRODUCT_COUPONS, true );
            if ( ! empty( $titles ) ) {
                $has_coupon_products = true;
                break;
            }
        }
        if ( ! $has_coupon_products ) {
            return;
        }
        ?>
        <div id="kdna-sc-send-coupon-form" class="kdna-sc-send-form">
            <h3><?php echo esc_html( $s['send_form_title'] ); ?></h3>
            <?php if ( $s['send_form_description'] ) : ?>
                <p><?php echo esc_html( $s['send_form_description'] ); ?></p>
            <?php endif; ?>
            <p class="form-row form-row-wide">
                <label for="kdna_sc_gift_email"><?php esc_html_e( 'Recipient Email', 'kdna-ecommerce' ); ?></label>
                <input type="email" name="kdna_sc_gift_email" id="kdna_sc_gift_email" class="input-text" placeholder="<?php esc_attr_e( 'Enter email address', 'kdna-ecommerce' ); ?>">
            </p>
            <p class="form-row form-row-wide">
                <label for="kdna_sc_gift_message"><?php esc_html_e( 'Personal Message (optional)', 'kdna-ecommerce' ); ?></label>
                <textarea name="kdna_sc_gift_message" id="kdna_sc_gift_message" class="input-text" rows="3" placeholder="<?php esc_attr_e( 'Add a personal message...', 'kdna-ecommerce' ); ?>"></textarea>
            </p>
            <?php if ( $s['allow_schedule_sending'] === 'yes' ) : ?>
                <p class="form-row form-row-wide">
                    <label for="kdna_sc_gift_date"><?php esc_html_e( 'Send Date (optional)', 'kdna-ecommerce' ); ?></label>
                    <input type="datetime-local" name="kdna_sc_gift_date" id="kdna_sc_gift_date" class="input-text">
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function save_send_coupon_form( $order_id ) {
        if ( ! empty( $_POST['kdna_sc_gift_email'] ) ) {
            $order = wc_get_order( $order_id );
            $order->update_meta_data( self::META_GIFT_EMAIL, sanitize_email( wp_unslash( $_POST['kdna_sc_gift_email'] ) ) );
            if ( ! empty( $_POST['kdna_sc_gift_message'] ) ) {
                $order->update_meta_data( self::META_GIFT_MESSAGE, sanitize_textarea_field( wp_unslash( $_POST['kdna_sc_gift_message'] ) ) );
            }
            if ( ! empty( $_POST['kdna_sc_gift_date'] ) ) {
                $order->update_meta_data( self::META_GIFT_TIMESTAMP, sanitize_text_field( wp_unslash( $_POST['kdna_sc_gift_date'] ) ) );
            }
            $order->save();
        }
    }

    // =========================================================================
    // Store Notice Banner
    // =========================================================================

    public function store_notice_coupon( $notice, $raw_notice ) {
        $s    = self::get_settings();
        $code = $s['storewide_coupon_code'];
        if ( empty( $code ) ) {
            return $notice;
        }
        $coupon = new WC_Coupon( $code );
        if ( ! $coupon->get_id() ) {
            return $notice;
        }
        $discount = $coupon->get_discount_type() === 'percent'
            ? round( $coupon->get_amount() ) . '% off'
            : wc_price( $coupon->get_amount() ) . ' off';
        $link = home_url( '/?apply_coupon=' . $code );
        return '<a href="' . esc_url( $link ) . '">' . sprintf( __( 'Use code <strong>%1$s</strong> for %2$s!', 'kdna-ecommerce' ), strtoupper( $code ), $discount ) . '</a>';
    }

    // =========================================================================
    // My Account — Coupons Endpoint
    // =========================================================================

    public function register_myaccount_endpoint() {
        add_rewrite_endpoint( 'coupons', EP_ROOT | EP_PAGES );
    }

    public function myaccount_menu_item( $items ) {
        $s = self::get_settings();
        if ( $s['show_on_myaccount'] !== 'yes' ) {
            return $items;
        }
        $new_items = [];
        foreach ( $items as $key => $label ) {
            $new_items[ $key ] = $label;
            if ( $key === 'orders' ) {
                $new_items['coupons'] = $s['credit_label_plural'] ?: __( 'Coupons', 'kdna-ecommerce' );
            }
        }
        return $new_items;
    }

    public function myaccount_coupons_page() {
        $s       = self::get_settings();
        $coupons = $this->get_available_coupons();
        $design  = $s['coupon_design'];
        $bg      = $s['custom_bg_color'];
        $fg      = $s['custom_fg_color'];

        echo '<h3>' . esc_html( $s['myaccount_label'] ) . '</h3>';

        if ( empty( $coupons ) ) {
            echo '<p>' . esc_html__( 'No coupons available.', 'kdna-ecommerce' ) . '</p>';
            return;
        }

        echo '<div class="kdna-sc-available-coupons kdna-sc-myaccount">';
        echo '<div class="kdna-sc-coupon-grid kdna-sc-design-' . esc_attr( $design ) . '">';
        foreach ( $coupons as $coupon ) {
            $this->render_coupon_card( $coupon, $design, $bg, $fg, false );
        }
        echo '</div></div>';

        // Show invalid/used coupons if setting enabled.
        if ( $s['show_invalid_on_myaccount'] === 'yes' ) {
            $this->display_invalid_coupons();
        }
    }

    private function display_invalid_coupons() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }
        $user  = get_userdata( $user_id );
        $email = $user ? $user->user_email : '';

        $expired = get_posts( [
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'meta_query'     => [
                [
                    'key'     => 'customer_email',
                    'value'   => $email,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'date_expires',
                    'value'   => time(),
                    'compare' => '<',
                    'type'    => 'NUMERIC',
                ],
            ],
            'fields' => 'ids',
        ] );

        if ( empty( $expired ) ) {
            return;
        }

        echo '<h4 style="margin-top:20px;opacity:0.6;">' . esc_html__( 'Expired / Used Coupons', 'kdna-ecommerce' ) . '</h4>';
        echo '<div class="kdna-sc-coupon-grid" style="opacity:0.5;">';
        foreach ( $expired as $id ) {
            $coupon = new WC_Coupon( $id );
            $this->render_coupon_card( $coupon, 'minimal', '#999', '#333', false );
        }
        echo '</div>';
    }

    // =========================================================================
    // Display Available Coupons
    // =========================================================================

    public function display_available_coupons_cart() {
        $this->display_available_coupons( 'cart' );
    }

    public function display_available_coupons_checkout() {
        $this->display_available_coupons( 'checkout' );
    }

    public function display_available_coupons( $context = 'cart' ) {
        $s       = self::get_settings();
        $coupons = $this->get_available_coupons();

        if ( empty( $coupons ) && $s['always_show_section'] !== 'yes' ) {
            return;
        }

        $design = $s['coupon_design'];
        $bg     = $s['custom_bg_color'];
        $fg     = $s['custom_fg_color'];
        $label  = $s['cart_checkout_label'];
        $label  = str_replace( '{coupons_count}', count( $coupons ), $label );
        $open   = $s['default_section_open'] === 'yes' ? ' open' : '';

        echo '<div class="kdna-sc-available-coupons">';
        echo '<details' . $open . '>';
        echo '<summary class="kdna-sc-heading">' . esc_html( $label ) . '</summary>';
        if ( ! empty( $coupons ) ) {
            echo '<div class="kdna-sc-coupon-grid kdna-sc-design-' . esc_attr( $design ) . '">';
            foreach ( $coupons as $coupon ) {
                $this->render_coupon_card( $coupon, $design, $bg, $fg );
            }
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'No coupons available.', 'kdna-ecommerce' ) . '</p>';
        }
        echo '</details></div>';
    }

    public function display_myaccount_coupons() {
        $s       = self::get_settings();
        $coupons = $this->get_available_coupons();
        if ( empty( $coupons ) ) {
            return;
        }
        $design = $s['coupon_design'];
        $bg     = $s['custom_bg_color'];
        $fg     = $s['custom_fg_color'];

        echo '<div class="kdna-sc-available-coupons kdna-sc-myaccount">';
        echo '<h3 class="kdna-sc-heading">' . esc_html( $s['myaccount_label'] ) . '</h3>';
        echo '<div class="kdna-sc-coupon-grid kdna-sc-design-' . esc_attr( $design ) . '">';
        foreach ( $coupons as $coupon ) {
            $this->render_coupon_card( $coupon, $design, $bg, $fg, false );
        }
        echo '</div></div>';
    }

    public function display_product_coupons() {
        global $product;
        if ( ! $product ) {
            return;
        }
        $titles = get_post_meta( $product->get_id(), self::META_PRODUCT_COUPONS, true );
        if ( empty( $titles ) ) {
            return;
        }
        $s     = self::get_settings();
        $codes = array_map( 'trim', explode( ',', $titles ) );

        echo '<div class="kdna-sc-product-coupons">';
        echo '<p class="kdna-sc-product-text">' . esc_html( $s['coupons_with_product_text'] ) . '</p>';
        echo '<div class="kdna-sc-coupon-grid kdna-sc-design-' . esc_attr( $s['coupon_design'] ) . '">';
        foreach ( $codes as $code ) {
            $coupon = new WC_Coupon( $code );
            if ( $coupon->get_id() ) {
                $this->render_coupon_card( $coupon, $s['coupon_design'], $s['custom_bg_color'], $s['custom_fg_color'], false );
            }
        }
        echo '</div></div>';
    }

    // ---- Discounted Price Display ----

    public function display_discounted_price( $price, $cart_item, $cart_item_key ) {
        $applied = WC()->cart->get_applied_coupons();
        if ( empty( $applied ) ) {
            return $price;
        }
        $product = $cart_item['data'];
        $original = (float) $product->get_price();
        $discounted = $original;

        foreach ( $applied as $code ) {
            $coupon = new WC_Coupon( $code );
            if ( $coupon->get_discount_type() === 'percent' ) {
                $discounted -= $original * ( $coupon->get_amount() / 100 );
            } elseif ( $coupon->get_discount_type() === 'fixed_product' ) {
                $discounted -= $coupon->get_amount();
            }
        }

        $discounted = max( 0, $discounted );
        if ( $discounted < $original ) {
            return '<del>' . wc_price( $original ) . '</del> <ins>' . wc_price( $discounted ) . '</ins>';
        }
        return $price;
    }

    // =========================================================================
    // Render Coupon Card
    // =========================================================================

    public function render_coupon_card( $coupon, $design = 'basic', $bg = '#39cccc', $fg = '#30050b', $show_apply = true ) {
        $s           = self::get_settings();
        $code        = $coupon->get_code();
        $amount      = $coupon->get_amount();
        $type        = $coupon->get_discount_type();
        $description = $coupon->get_description();
        $expiry      = $coupon->get_date_expires();

        switch ( $type ) {
            case 'percent':
                $display = round( $amount ) . '%';
                $label   = __( 'OFF', 'kdna-ecommerce' );
                break;
            case 'store_credit':
                $display = wc_price( $amount );
                $label   = $s['credit_label_singular'] ?: __( 'CREDIT', 'kdna-ecommerce' );
                break;
            default:
                $display = wc_price( $amount );
                $label   = __( 'OFF', 'kdna-ecommerce' );
                break;
        }

        $nonce      = wp_create_nonce( 'kdna_sc_apply_' . $code );
        $is_applied = WC()->cart && WC()->cart->has_discount( $code );
        $show_desc  = $s['show_coupon_description'] === 'yes';
        $message    = get_post_meta( $coupon->get_id(), self::META_COUPON_MESSAGE, true );
        ?>
        <div class="kdna-sc-coupon-card <?php echo $is_applied ? 'kdna-sc-applied' : ''; ?>" style="--kdna-sc-bg:<?php echo esc_attr( $bg ); ?>;--kdna-sc-fg:<?php echo esc_attr( $fg ); ?>;--kdna-sc-third:<?php echo esc_attr( $s['custom_third_color'] ); ?>;" data-code="<?php echo esc_attr( $code ); ?>">
            <div class="kdna-sc-coupon-amount">
                <span class="kdna-sc-discount"><?php echo $display; ?></span>
                <span class="kdna-sc-label"><?php echo esc_html( $label ); ?></span>
            </div>
            <div class="kdna-sc-coupon-details">
                <span class="kdna-sc-code"><?php echo esc_html( strtoupper( $code ) ); ?></span>
                <?php if ( $show_desc && $description ) : ?>
                    <span class="kdna-sc-desc"><?php echo esc_html( $description ); ?></span>
                <?php endif; ?>
                <?php if ( $message ) : ?>
                    <span class="kdna-sc-message"><?php echo wp_kses_post( $message ); ?></span>
                <?php endif; ?>
                <?php if ( $expiry ) : ?>
                    <span class="kdna-sc-expiry"><?php printf( esc_html__( 'Expires: %s', 'kdna-ecommerce' ), esc_html( $expiry->date_i18n( wc_date_format() ) ) ); ?></span>
                <?php endif; ?>
            </div>
            <div class="kdna-sc-coupon-actions">
                <?php if ( $show_apply && ! $is_applied ) : ?>
                    <button type="button" class="kdna-sc-apply-btn" data-coupon="<?php echo esc_attr( $code ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        <?php esc_html_e( 'Apply', 'kdna-ecommerce' ); ?>
                    </button>
                <?php elseif ( $is_applied ) : ?>
                    <span class="kdna-sc-applied-badge"><?php esc_html_e( 'Applied', 'kdna-ecommerce' ); ?></span>
                <?php endif; ?>
                <?php if ( $s['enable_printing'] === 'yes' ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=kdna_sc_print_coupon&coupon_id=' . $coupon->get_id() ) ); ?>" class="kdna-sc-print-btn" target="_blank" title="<?php esc_attr_e( 'Print', 'kdna-ecommerce' ); ?>">&#x1f5b6;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // Get Available Coupons
    // =========================================================================

    public function get_available_coupons() {
        $s          = self::get_settings();
        $max        = (int) $s['max_coupons_shown'];
        if ( $max <= 0 ) {
            return [];
        }
        $user_email = '';
        $user_id    = get_current_user_id();
        if ( $user_id ) {
            $user       = get_userdata( $user_id );
            $user_email = $user ? $user->user_email : '';
        }

        // Get storewide-visible coupons + user-specific coupons.
        $args = [
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => $max * 2,
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => self::META_IS_VISIBLE_STOREWIDE, 'value' => 'yes' ],
                [ 'key' => 'customer_email', 'compare' => 'NOT EXISTS' ],
                [ 'key' => 'customer_email', 'value' => '', 'compare' => '=' ],
            ],
        ];

        $posts   = get_posts( $args );
        $coupons = [];

        foreach ( $posts as $post ) {
            $coupon = new WC_Coupon( $post->ID );

            // Email restrictions.
            $restrictions = $coupon->get_email_restrictions();
            if ( ! empty( $restrictions ) ) {
                if ( ! $user_email || ! in_array( strtolower( $user_email ), array_map( 'strtolower', $restrictions ), true ) ) {
                    continue;
                }
            }

            // New user restriction.
            if ( get_post_meta( $coupon->get_id(), self::META_RESTRICT_NEW_USER, true ) === 'yes' ) {
                if ( $user_id && wc_get_customer_order_count( $user_id ) > 0 ) {
                    continue;
                }
            }

            // Usage limits.
            if ( $coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
                continue;
            }
            if ( $user_id && $coupon->get_usage_limit_per_user() > 0 ) {
                $data_store = WC_Data_Store::load( 'coupon' );
                if ( $data_store->get_usage_by_user_id( $coupon, $user_id ) >= $coupon->get_usage_limit_per_user() ) {
                    continue;
                }
            }

            // Expiry.
            $expiry = $coupon->get_date_expires();
            if ( $expiry && $expiry->getTimestamp() < time() ) {
                continue;
            }

            $coupons[] = $coupon;
            if ( count( $coupons ) >= $max ) {
                break;
            }
        }

        return $coupons;
    }

    // =========================================================================
    // AJAX Apply Coupon
    // =========================================================================

    public function ajax_apply_coupon() {
        $code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
        if ( empty( $code ) || ! wp_verify_nonce( $_POST['nonce'] ?? '', 'kdna_sc_apply_' . $code ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'kdna-ecommerce' ) ] );
        }
        if ( WC()->cart->has_discount( $code ) ) {
            wp_send_json_error( [ 'message' => __( 'Coupon already applied.', 'kdna-ecommerce' ) ] );
        }
        $result = WC()->cart->apply_coupon( $code );
        if ( $result ) {
            wp_send_json_success( [ 'message' => __( 'Coupon applied successfully!', 'kdna-ecommerce' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Coupon could not be applied.', 'kdna-ecommerce' ) ] );
        }
    }

    // =========================================================================
    // Shortcode
    // =========================================================================

    public function shortcode_available_coupons( $atts ) {
        $atts = shortcode_atts( [
            'design'  => '',
            'color'   => '',
            'text'    => '',
            'heading' => __( 'Available Coupons', 'kdna-ecommerce' ),
        ], $atts );

        $coupons = $this->get_available_coupons();
        if ( empty( $coupons ) ) {
            return '';
        }

        $s       = self::get_settings();
        $design  = $atts['design'] ?: $s['coupon_design'];
        $bg      = $atts['color'] ?: $s['custom_bg_color'];
        $fg      = $atts['text'] ?: $s['custom_fg_color'];

        ob_start();
        echo '<div class="kdna-sc-available-coupons">';
        if ( $atts['heading'] ) {
            echo '<h3 class="kdna-sc-heading">' . esc_html( $atts['heading'] ) . '</h3>';
        }
        echo '<div class="kdna-sc-coupon-grid kdna-sc-design-' . esc_attr( $design ) . '">';
        foreach ( $coupons as $coupon ) {
            $this->render_coupon_card( $coupon, $design, $bg, $fg );
        }
        echo '</div></div>';
        return ob_get_clean();
    }

    // =========================================================================
    // Frontend Assets
    // =========================================================================

    public function enqueue_styles() {
        if ( ! is_cart() && ! is_checkout() && ! is_account_page() && ! is_product() ) {
            return;
        }

        wp_enqueue_style(
            'kdna-smart-coupons',
            KDNA_ECOMMERCE_URL . 'modules/smart-coupons/assets/smart-coupons.css',
            [],
            KDNA_ECOMMERCE_VERSION
        );

        wp_enqueue_script(
            'kdna-smart-coupons',
            KDNA_ECOMMERCE_URL . 'modules/smart-coupons/assets/smart-coupons.js',
            [ 'jquery' ],
            KDNA_ECOMMERCE_VERSION,
            true
        );

        wp_localize_script( 'kdna-smart-coupons', 'kdna_sc', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'applying' => __( 'Applying...', 'kdna-ecommerce' ),
            'applied'  => __( 'Applied', 'kdna-ecommerce' ),
            'error'    => __( 'Error', 'kdna-ecommerce' ),
        ] );
    }
}
