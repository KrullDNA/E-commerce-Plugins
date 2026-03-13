<?php
/**
 * KDNA Smart Coupons Module
 *
 * Adds smart coupon features: auto-apply coupons, URL-based coupon application,
 * available coupon display on cart/checkout/My Account, store credit support,
 * and coupon styling.
 */
defined( 'ABSPATH' ) || exit;

class KDNA_Smart_Coupons {

    const META_AUTO_APPLY  = '_kdna_sc_auto_apply';
    const META_COUPON_STYLE = '_kdna_sc_coupon_style';

    public function __construct() {
        $settings = self::get_settings();

        // URL coupon application.
        if ( ( $settings['enable_url_coupons'] ?? 'yes' ) === 'yes' ) {
            add_action( 'wp_loaded', [ $this, 'apply_coupon_from_url' ], 20 );
            add_action( 'init', [ $this, 'add_rewrite_rules' ] );
            add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
            add_action( 'template_redirect', [ $this, 'handle_coupon_endpoint' ] );
        }

        // Auto-apply coupons.
        if ( ( $settings['enable_auto_apply'] ?? 'yes' ) === 'yes' ) {
            add_action( 'woocommerce_before_calculate_totals', [ $this, 'auto_apply_coupons' ], 99 );
        }

        // Display available coupons.
        if ( ( $settings['show_on_cart'] ?? 'yes' ) === 'yes' ) {
            add_action( 'woocommerce_before_cart', [ $this, 'display_available_coupons' ] );
        }
        if ( ( $settings['show_on_checkout'] ?? 'yes' ) === 'yes' ) {
            add_action( 'woocommerce_before_checkout_form', [ $this, 'display_available_coupons' ], 5 );
        }
        if ( ( $settings['show_on_myaccount'] ?? 'yes' ) === 'yes' ) {
            add_action( 'woocommerce_account_dashboard', [ $this, 'display_myaccount_coupons' ] );
        }

        // Admin coupon fields.
        add_action( 'woocommerce_coupon_options', [ $this, 'add_coupon_fields' ], 10, 2 );
        add_action( 'woocommerce_coupon_options_save', [ $this, 'save_coupon_fields' ], 10, 2 );

        // Frontend styles.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );

        // Store credit discount type.
        add_filter( 'woocommerce_coupon_discount_types', [ $this, 'add_store_credit_type' ] );
        add_filter( 'woocommerce_coupon_get_discount_amount', [ $this, 'store_credit_discount_amount' ], 10, 5 );
        add_action( 'woocommerce_order_status_completed', [ $this, 'deduct_store_credit' ] );

        // One-click apply via AJAX.
        add_action( 'wp_ajax_kdna_sc_apply_coupon', [ $this, 'ajax_apply_coupon' ] );
        add_action( 'wp_ajax_nopriv_kdna_sc_apply_coupon', [ $this, 'ajax_apply_coupon' ] );

        // Shortcode.
        add_shortcode( 'kdna_available_coupons', [ $this, 'shortcode_available_coupons' ] );
    }

    /**
     * Get module settings with defaults.
     */
    public static function get_settings() {
        return wp_parse_args( get_option( 'kdna_smart_coupons_settings', [] ), self::get_default_settings() );
    }

    public static function get_default_settings() {
        return [
            'show_on_cart'       => 'yes',
            'show_on_checkout'   => 'yes',
            'show_on_myaccount'  => 'yes',
            'enable_url_coupons' => 'yes',
            'enable_auto_apply'  => 'yes',
            'coupon_design'      => 'flat',
            'primary_color'      => '#39cccc',
            'text_color'         => '#ffffff',
            'max_coupons_shown'  => '10',
        ];
    }

    // -------------------------------------------------------------------------
    // URL Coupons
    // -------------------------------------------------------------------------

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

        WC()->session->set( 'kdna_sc_url_coupon', sanitize_text_field( $code ) );

        $redirect = wc_get_cart_url();
        wp_safe_redirect( $redirect );
        exit;
    }

    public function apply_coupon_from_url() {
        // Query parameter: ?apply_coupon=CODE
        if ( ! empty( $_GET['apply_coupon'] ) ) {
            $code = sanitize_text_field( wp_unslash( $_GET['apply_coupon'] ) );
            if ( WC()->cart && ! WC()->cart->has_discount( $code ) ) {
                WC()->cart->apply_coupon( $code );
            }
            return;
        }

        // Session-based (from rewrite endpoint).
        if ( ! WC()->session ) {
            return;
        }
        $code = WC()->session->get( 'kdna_sc_url_coupon' );
        if ( $code && WC()->cart && ! WC()->cart->has_discount( $code ) ) {
            WC()->cart->apply_coupon( $code );
            WC()->session->set( 'kdna_sc_url_coupon', null );
        }
    }

    // -------------------------------------------------------------------------
    // Auto-Apply Coupons
    // -------------------------------------------------------------------------

    public function auto_apply_coupons( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $auto_coupons = get_posts( [
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'meta_query'     => [
                [
                    'key'   => self::META_AUTO_APPLY,
                    'value' => 'yes',
                ],
            ],
            'fields' => 'ids',
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

    // -------------------------------------------------------------------------
    // Admin Coupon Fields
    // -------------------------------------------------------------------------

    public function add_coupon_fields( $coupon_id, $coupon ) {
        woocommerce_wp_checkbox( [
            'id'          => self::META_AUTO_APPLY,
            'label'       => __( 'Auto-apply coupon', 'kdna-ecommerce' ),
            'description' => __( 'Automatically apply this coupon when conditions are met.', 'kdna-ecommerce' ),
            'value'       => get_post_meta( $coupon_id, self::META_AUTO_APPLY, true ),
            'cbvalue'     => 'yes',
        ] );
    }

    public function save_coupon_fields( $coupon_id, $coupon ) {
        $auto_apply = isset( $_POST[ self::META_AUTO_APPLY ] ) ? 'yes' : 'no';
        update_post_meta( $coupon_id, self::META_AUTO_APPLY, $auto_apply );
    }

    // -------------------------------------------------------------------------
    // Store Credit Discount Type
    // -------------------------------------------------------------------------

    public function add_store_credit_type( $types ) {
        $types['store_credit'] = __( 'Store Credit / Gift Certificate', 'kdna-ecommerce' );
        return $types;
    }

    public function store_credit_discount_amount( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
        if ( $coupon->get_discount_type() !== 'store_credit' ) {
            return $discount;
        }
        $credit = $coupon->get_amount();
        return min( $credit, $discounting_amount );
    }

    public function deduct_store_credit( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        foreach ( $order->get_coupon_codes() as $code ) {
            $coupon = new WC_Coupon( $code );
            if ( $coupon->get_discount_type() === 'store_credit' ) {
                $used = $order->get_discount_total();
                $remaining = max( 0, $coupon->get_amount() - $used );
                $coupon->set_amount( $remaining );
                $coupon->save();
            }
        }
    }

    // -------------------------------------------------------------------------
    // Display Available Coupons
    // -------------------------------------------------------------------------

    public function display_available_coupons() {
        $coupons = $this->get_available_coupons();
        if ( empty( $coupons ) ) {
            return;
        }

        $settings = self::get_settings();
        $design   = $settings['coupon_design'];
        $primary  = $settings['primary_color'];
        $text     = $settings['text_color'];

        echo '<div class="kdna-sc-available-coupons">';
        echo '<h3 class="kdna-sc-heading">' . esc_html__( 'Available Coupons', 'kdna-ecommerce' ) . '</h3>';
        echo '<div class="kdna-sc-coupon-grid kdna-sc-design-' . esc_attr( $design ) . '">';

        foreach ( $coupons as $coupon ) {
            $this->render_coupon_card( $coupon, $design, $primary, $text );
        }

        echo '</div></div>';
    }

    public function display_myaccount_coupons() {
        $coupons = $this->get_available_coupons();
        if ( empty( $coupons ) ) {
            return;
        }

        $settings = self::get_settings();
        $design   = $settings['coupon_design'];
        $primary  = $settings['primary_color'];
        $text     = $settings['text_color'];

        echo '<div class="kdna-sc-available-coupons kdna-sc-myaccount">';
        echo '<h3 class="kdna-sc-heading">' . esc_html__( 'Your Coupons', 'kdna-ecommerce' ) . '</h3>';
        echo '<div class="kdna-sc-coupon-grid kdna-sc-design-' . esc_attr( $design ) . '">';

        foreach ( $coupons as $coupon ) {
            $this->render_coupon_card( $coupon, $design, $primary, $text, false );
        }

        echo '</div></div>';
    }

    public function render_coupon_card( $coupon, $design = 'flat', $primary = '#39cccc', $text = '#ffffff', $show_apply = true ) {
        $code        = $coupon->get_code();
        $amount      = $coupon->get_amount();
        $type        = $coupon->get_discount_type();
        $description = $coupon->get_description();
        $expiry      = $coupon->get_date_expires();

        // Format the discount display.
        switch ( $type ) {
            case 'percent':
                $display = round( $amount ) . '%';
                $label   = __( 'OFF', 'kdna-ecommerce' );
                break;
            case 'store_credit':
                $display = wc_price( $amount );
                $label   = __( 'CREDIT', 'kdna-ecommerce' );
                break;
            case 'fixed_cart':
                $display = wc_price( $amount );
                $label   = __( 'OFF', 'kdna-ecommerce' );
                break;
            case 'fixed_product':
                $display = wc_price( $amount );
                $label   = __( 'OFF', 'kdna-ecommerce' );
                break;
            default:
                $display = wc_price( $amount );
                $label   = __( 'OFF', 'kdna-ecommerce' );
                break;
        }

        $nonce     = wp_create_nonce( 'kdna_sc_apply_' . $code );
        $is_applied = WC()->cart && WC()->cart->has_discount( $code );
        ?>
        <div class="kdna-sc-coupon-card <?php echo $is_applied ? 'kdna-sc-applied' : ''; ?>" style="--kdna-sc-primary:<?php echo esc_attr( $primary ); ?>;--kdna-sc-text:<?php echo esc_attr( $text ); ?>;" data-code="<?php echo esc_attr( $code ); ?>">
            <div class="kdna-sc-coupon-amount">
                <span class="kdna-sc-discount"><?php echo $display; ?></span>
                <span class="kdna-sc-label"><?php echo esc_html( $label ); ?></span>
            </div>
            <div class="kdna-sc-coupon-details">
                <span class="kdna-sc-code"><?php echo esc_html( strtoupper( $code ) ); ?></span>
                <?php if ( $description ) : ?>
                    <span class="kdna-sc-desc"><?php echo esc_html( $description ); ?></span>
                <?php endif; ?>
                <?php if ( $expiry ) : ?>
                    <span class="kdna-sc-expiry"><?php
                        /* translators: %s: expiry date */
                        printf( esc_html__( 'Expires: %s', 'kdna-ecommerce' ), esc_html( $expiry->date_i18n( wc_date_format() ) ) );
                    ?></span>
                <?php endif; ?>
            </div>
            <?php if ( $show_apply && ! $is_applied ) : ?>
                <button type="button" class="kdna-sc-apply-btn" data-coupon="<?php echo esc_attr( $code ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    <?php esc_html_e( 'Apply', 'kdna-ecommerce' ); ?>
                </button>
            <?php elseif ( $is_applied ) : ?>
                <span class="kdna-sc-applied-badge"><?php esc_html_e( 'Applied', 'kdna-ecommerce' ); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get coupons available to the current user.
     */
    public function get_available_coupons() {
        $settings    = self::get_settings();
        $max         = (int) ( $settings['max_coupons_shown'] ?? 10 );
        $user_email  = '';
        $user_id     = get_current_user_id();

        if ( $user_id ) {
            $user       = get_userdata( $user_id );
            $user_email = $user ? $user->user_email : '';
        }

        $args = [
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => $max,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'customer_email',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'customer_email',
                    'value'   => '',
                    'compare' => '=',
                ],
            ],
        ];

        $coupon_posts = get_posts( $args );
        $coupons      = [];

        foreach ( $coupon_posts as $post ) {
            $coupon = new WC_Coupon( $post->ID );

            // Check email restrictions.
            $email_restrictions = $coupon->get_email_restrictions();
            if ( ! empty( $email_restrictions ) ) {
                if ( ! $user_email || ! in_array( strtolower( $user_email ), array_map( 'strtolower', $email_restrictions ), true ) ) {
                    continue;
                }
            }

            // Check usage limits.
            if ( $coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
                continue;
            }
            if ( $user_id && $coupon->get_usage_limit_per_user() > 0 ) {
                $data_store = WC_Data_Store::load( 'coupon' );
                $usage      = $data_store->get_usage_by_user_id( $coupon, $user_id );
                if ( $usage >= $coupon->get_usage_limit_per_user() ) {
                    continue;
                }
            }

            // Check expiry.
            $expiry = $coupon->get_date_expires();
            if ( $expiry && $expiry->getTimestamp() < time() ) {
                continue;
            }

            $coupons[] = $coupon;
        }

        return $coupons;
    }

    // -------------------------------------------------------------------------
    // AJAX Apply Coupon
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Shortcode
    // -------------------------------------------------------------------------

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

        $settings = self::get_settings();
        $design   = $atts['design'] ?: $settings['coupon_design'];
        $primary  = $atts['color'] ?: $settings['primary_color'];
        $text_clr = $atts['text'] ?: $settings['text_color'];

        ob_start();
        echo '<div class="kdna-sc-available-coupons">';
        if ( $atts['heading'] ) {
            echo '<h3 class="kdna-sc-heading">' . esc_html( $atts['heading'] ) . '</h3>';
        }
        echo '<div class="kdna-sc-coupon-grid kdna-sc-design-' . esc_attr( $design ) . '">';
        foreach ( $coupons as $coupon ) {
            $this->render_coupon_card( $coupon, $design, $primary, $text_clr );
        }
        echo '</div></div>';
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Frontend Assets
    // -------------------------------------------------------------------------

    public function enqueue_styles() {
        if ( ! is_cart() && ! is_checkout() && ! is_account_page() ) {
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
