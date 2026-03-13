<?php
defined( 'ABSPATH' ) || exit;

class KDNA_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function add_menu() {
        add_menu_page(
            __( 'E-commerce Suite', 'kdna-ecommerce' ),
            __( 'E-commerce Suite', 'kdna-ecommerce' ),
            'manage_woocommerce',
            'kdna-ecommerce',
            [ $this, 'render_settings_page' ],
            'dashicons-store',
            56
        );
    }

    public function register_settings() {
        register_setting( 'kdna_ecommerce_settings', 'kdna_ecommerce_modules', [
            'sanitize_callback' => [ $this, 'sanitize_modules' ],
        ]);

        // Points & Rewards settings
        register_setting( 'kdna_ecommerce_points', 'kdna_points_settings', [
            'sanitize_callback' => [ $this, 'sanitize_array' ],
        ]);

        // Reviews settings
        register_setting( 'kdna_ecommerce_reviews', 'kdna_reviews_settings', [
            'sanitize_callback' => [ $this, 'sanitize_array' ],
        ]);

        // Related Products settings
        register_setting( 'kdna_ecommerce_related', 'kdna_related_settings', [
            'sanitize_callback' => [ $this, 'sanitize_array' ],
        ]);

        // Sequential Orders settings
        register_setting( 'kdna_ecommerce_sequential', 'kdna_sequential_settings', [
            'sanitize_callback' => [ $this, 'sanitize_array' ],
        ]);

        // Tax Invoice settings
        register_setting( 'kdna_ecommerce_invoice', 'kdna_invoice_settings', [
            'sanitize_callback' => [ $this, 'sanitize_invoice_settings' ],
        ]);

        // Smart Coupons settings
        register_setting( 'kdna_ecommerce_smart_coupons', 'kdna_smart_coupons_settings', [
            'sanitize_callback' => [ $this, 'sanitize_smart_coupons_settings' ],
        ]);
    }

    public function sanitize_invoice_settings( $input ) {
        if ( ! is_array( $input ) ) {
            return [];
        }
        $output = [];
        $output['logo_id']      = absint( $input['logo_id'] ?? 0 );
        $output['accent_color'] = sanitize_hex_color( $input['accent_color'] ?? '#C8E600' ) ?: '#C8E600';
        $output['footer_text']  = wp_kses_post( $input['footer_text'] ?? '' );
        return $output;
    }

    public function sanitize_modules( $input ) {
        $modules = [ 'points_rewards', 'reviews', 'related_products', 'sequential_orders', 'australia_post', 'shipment_tracking', 'tax_invoice', 'smart_coupons' ];
        $output = [];
        foreach ( $modules as $module ) {
            $output[ $module ] = isset( $input[ $module ] ) ? 'yes' : 'no';
        }
        return $output;
    }

    public function sanitize_smart_coupons_settings( $input ) {
        if ( ! is_array( $input ) ) {
            return [];
        }
        require_once KDNA_ECOMMERCE_PATH . 'modules/smart-coupons/class-kdna-smart-coupons.php';
        $defaults = KDNA_Smart_Coupons::get_default_settings();
        $output   = [];

        // Checkboxes → yes/no.
        $checkboxes = [
            'enable_auto_apply', 'delete_after_use', 'send_coupon_email', 'enable_printing',
            'sell_credit_at_less_price', 'show_discounted_price', 'show_associated_on_product',
            'show_on_myaccount', 'show_received_on_myaccount', 'show_invalid_on_myaccount',
            'show_coupon_description', 'show_on_cart', 'show_on_checkout', 'always_show_section',
            'default_section_open', 'include_tax', 'allow_sending_to_others', 'allow_schedule_sending',
            'combine_emails', 'email_auto_generated', 'email_combined', 'email_acknowledgement',
            'email_expiry_reminder', 'email_store_credit_image', 'email_unused_reminder',
            'expiry_reminder_enabled', 'unused_reminder_enabled', 'cashback_enabled',
            'enable_url_coupons',
        ];
        foreach ( $checkboxes as $field ) {
            $output[ $field ] = isset( $input[ $field ] ) ? 'yes' : 'no';
        }

        // Numbers.
        $numbers = [
            'max_coupons_shown', 'coupon_code_length', 'expiry_reminder_days_before',
            'unused_reminder_days', 'unused_max_reminders',
        ];
        foreach ( $numbers as $field ) {
            $output[ $field ] = absint( $input[ $field ] ?? $defaults[ $field ] );
        }

        // Text fields.
        $texts = [
            'coupon_design', 'coupon_email_design', 'coupon_color_scheme',
            'store_notice_design', 'storewide_coupon_code',
            'product_page_text', 'credit_label_singular', 'credit_label_plural',
            'credit_product_cta', 'purchasing_credits_label', 'coupons_with_product_text',
            'cart_checkout_label', 'myaccount_label', 'send_form_title', 'send_form_description',
            'cashback_amount', 'cashback_type', 'cashback_min_order', 'cashback_template_coupon',
        ];
        foreach ( $texts as $field ) {
            $output[ $field ] = sanitize_text_field( $input[ $field ] ?? $defaults[ $field ] ?? '' );
        }

        // Colours.
        $colours = [ 'custom_bg_color', 'custom_fg_color', 'custom_third_color', 'primary_color', 'text_color' ];
        foreach ( $colours as $field ) {
            $output[ $field ] = sanitize_hex_color( $input[ $field ] ?? $defaults[ $field ] ?? '' ) ?: ( $defaults[ $field ] ?? '' );
        }

        // Array fields.
        if ( isset( $input['valid_order_statuses'] ) && is_array( $input['valid_order_statuses'] ) ) {
            $output['valid_order_statuses'] = array_map( 'sanitize_text_field', $input['valid_order_statuses'] );
        } else {
            $output['valid_order_statuses'] = $defaults['valid_order_statuses'];
        }

        return $output;
    }

    public function sanitize_array( $input ) {
        if ( ! is_array( $input ) ) {
            return [];
        }

        // Ensure checkbox fields default to 'no' when unchecked.
        $checkbox_fields = [ 'partial_redemption', 'delete_data_on_uninstall' ];
        foreach ( $checkbox_fields as $field ) {
            if ( ! isset( $input[ $field ] ) ) {
                $input[ $field ] = 'no';
            }
        }

        // Fields that may contain HTML (messages with <strong>, <h4>, etc.).
        $html_fields = [
            'product_message', 'variable_product_message', 'cart_message',
            'redeem_message', 'thankyou_message',
        ];

        // Review checkbox fields that default to 'no' when unchecked.
        $review_checkboxes = [
            'admins_can_reply', 'moderation', 'enable_reviews', 'verified_owner_label',
            'verified_owners_only', 'enable_star_rating', 'star_rating_required',
            'enable_photos', 'enable_videos', 'enable_voting', 'enable_flagging',
            'enable_qualifiers',
        ];
        foreach ( $review_checkboxes as $field ) {
            if ( ! isset( $input[ $field ] ) ) {
                $input[ $field ] = 'no';
            }
        }

        $output = [];
        foreach ( $input as $key => $value ) {
            if ( in_array( $key, $html_fields, true ) ) {
                $output[ $key ] = wp_kses_post( $value );
            } elseif ( is_array( $value ) ) {
                $output[ $key ] = array_map( 'sanitize_text_field', $value );
            } else {
                $output[ $key ] = sanitize_text_field( $value );
            }
        }
        return $output;
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_kdna-ecommerce' !== $hook ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_style( 'kdna-admin', KDNA_ECOMMERCE_URL . 'admin/css/admin.css', [], KDNA_ECOMMERCE_VERSION );
        wp_enqueue_script( 'kdna-admin', KDNA_ECOMMERCE_URL . 'admin/js/admin.js', [ 'jquery', 'wp-color-picker' ], KDNA_ECOMMERCE_VERSION, true );
        wp_localize_script( 'kdna-admin', 'kdna_admin', [
            'ajax_url'            => admin_url( 'admin-ajax.php' ),
            'nonce'               => wp_create_nonce( 'kdna-admin-nonce' ),
            'confirm_apply_points'=> __( 'Are you sure? This will award points to all qualifying previous orders.', 'kdna-ecommerce' ),
        ]);
    }

    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        $modules = get_option( 'kdna_ecommerce_modules', [] );
        ?>
        <div class="wrap kdna-ecommerce-wrap">
            <h1><?php esc_html_e( 'E-commerce Suite', 'kdna-ecommerce' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=kdna-ecommerce&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'General', 'kdna-ecommerce' ); ?>
                </a>
                <a href="?page=kdna-ecommerce&tab=points" class="nav-tab <?php echo $active_tab === 'points' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Points & Rewards', 'kdna-ecommerce' ); ?>
                </a>
                <a href="?page=kdna-ecommerce&tab=reviews" class="nav-tab <?php echo $active_tab === 'reviews' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Reviews', 'kdna-ecommerce' ); ?>
                </a>
                <a href="?page=kdna-ecommerce&tab=related" class="nav-tab <?php echo $active_tab === 'related' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Related Products', 'kdna-ecommerce' ); ?>
                </a>
                <a href="?page=kdna-ecommerce&tab=sequential" class="nav-tab <?php echo $active_tab === 'sequential' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Sequential Orders', 'kdna-ecommerce' ); ?>
                </a>
                <a href="?page=kdna-ecommerce&tab=australia_post" class="nav-tab <?php echo $active_tab === 'australia_post' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Australia Post', 'kdna-ecommerce' ); ?>
                </a>
                <a href="?page=kdna-ecommerce&tab=tracking" class="nav-tab <?php echo $active_tab === 'tracking' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Shipment Tracking', 'kdna-ecommerce' ); ?>
                </a>
                <a href="?page=kdna-ecommerce&tab=invoice" class="nav-tab <?php echo $active_tab === 'invoice' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Tax Invoice', 'kdna-ecommerce' ); ?>
                </a>
                <a href="?page=kdna-ecommerce&tab=smart_coupons" class="nav-tab <?php echo $active_tab === 'smart_coupons' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Smart Coupons', 'kdna-ecommerce' ); ?>
                </a>
            </nav>

            <div class="kdna-tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'points':
                        $this->render_points_tab( $modules );
                        break;
                    case 'reviews':
                        $this->render_reviews_tab( $modules );
                        break;
                    case 'related':
                        $this->render_related_tab( $modules );
                        break;
                    case 'sequential':
                        $this->render_sequential_tab( $modules );
                        break;
                    case 'australia_post':
                        $this->render_australia_post_tab( $modules );
                        break;
                    case 'tracking':
                        $this->render_tracking_tab( $modules );
                        break;
                    case 'invoice':
                        $this->render_invoice_tab( $modules );
                        break;
                    case 'smart_coupons':
                        $this->render_smart_coupons_tab( $modules );
                        break;
                    default:
                        $this->render_general_tab( $modules );
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_general_tab( $modules ) {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'kdna_ecommerce_settings' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Points & Rewards', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_ecommerce_modules[points_rewards]" value="1" <?php checked( $modules['points_rewards'] ?? 'no', 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Reward customers with points for purchases and actions, redeemable for discounts.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Product Reviews', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_ecommerce_modules[reviews]" value="1" <?php checked( $modules['reviews'] ?? 'no', 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Enhanced product reviews with photos, videos, voting, and review qualifiers.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Related Products', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_ecommerce_modules[related_products]" value="1" <?php checked( $modules['related_products'] ?? 'no', 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Manually select related products per product, displayed via Elementor widget.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Sequential Order Numbers', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_ecommerce_modules[sequential_orders]" value="1" <?php checked( $modules['sequential_orders'] ?? 'no', 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Custom sequential order numbers with prefix, suffix, and padding options.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Australia Post Shipping', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_ecommerce_modules[australia_post]" value="1" <?php checked( $modules['australia_post'] ?? 'no', 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Live Australia Post shipping rates via their API. Configure in WooCommerce > Settings > Shipping.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Shipment Tracking', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_ecommerce_modules[shipment_tracking]" value="1" <?php checked( $modules['shipment_tracking'] ?? 'no', 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Add tracking numbers to orders, displayed on the order page and in customer emails.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Tax Invoice PDF', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_ecommerce_modules[tax_invoice]" value="1" <?php checked( $modules['tax_invoice'] ?? 'no', 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Generate tax invoice PDFs attached to completed order emails and downloadable from My Account.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Smart Coupons', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_ecommerce_modules[smart_coupons]" value="1" <?php checked( $modules['smart_coupons'] ?? 'no', 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Auto-apply coupons, URL-based coupon sharing, store credits, and display available coupons on cart, checkout, and My Account.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function render_points_tab( $modules ) {
        $settings = get_option( 'kdna_points_settings', [] );
        $defaults = KDNA_Points_Rewards::get_default_settings();
        $settings = wp_parse_args( $settings, $defaults );
        $active = ( $modules['points_rewards'] ?? 'no' ) === 'yes';
        $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'kdna_ecommerce_points' ); ?>

            <?php if ( ! $active ) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'This module is currently disabled. Enable it in the General tab.', 'kdna-ecommerce' ); ?></p></div>
            <?php endif; ?>

            <!-- Points Settings -->
            <h2><?php esc_html_e( 'Points Settings', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th>
                        <label for="earn_points"><?php esc_html_e( 'Earn Points Conversion Rate', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Set the number of points awarded per monetary unit spent.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="number" id="earn_points" name="kdna_points_settings[earn_points]" value="<?php echo esc_attr( $settings['earn_points'] ); ?>" class="small-text" min="0" step="any">
                        <span><?php esc_html_e( 'Points', 'kdna-ecommerce' ); ?></span>
                        <span class="kdna-separator">=</span>
                        <span><?php echo esc_html( $currency ); ?></span>
                        <input type="number" id="earn_monetary" name="kdna_points_settings[earn_monetary]" value="<?php echo esc_attr( $settings['earn_monetary'] ); ?>" class="small-text" min="0" step="any">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="rounding_mode"><?php esc_html_e( 'Earn Points Rounding Mode', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'How fractional points are rounded when earning.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <select id="rounding_mode" name="kdna_points_settings[rounding_mode]">
                            <option value="round" <?php selected( $settings['rounding_mode'], 'round' ); ?>><?php esc_html_e( 'Round to nearest integer', 'kdna-ecommerce' ); ?></option>
                            <option value="floor" <?php selected( $settings['rounding_mode'], 'floor' ); ?>><?php esc_html_e( 'Always round down', 'kdna-ecommerce' ); ?></option>
                            <option value="ceil" <?php selected( $settings['rounding_mode'], 'ceil' ); ?>><?php esc_html_e( 'Always round up', 'kdna-ecommerce' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="redeem_points"><?php esc_html_e( 'Redemption Conversion Rate', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Set the number of points required per monetary unit of discount.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="number" id="redeem_points" name="kdna_points_settings[redeem_points]" value="<?php echo esc_attr( $settings['redeem_points'] ); ?>" class="small-text" min="0" step="any">
                        <span><?php esc_html_e( 'Points', 'kdna-ecommerce' ); ?></span>
                        <span class="kdna-separator">=</span>
                        <span><?php echo esc_html( $currency ); ?></span>
                        <input type="number" id="redeem_monetary" name="kdna_points_settings[redeem_monetary]" value="<?php echo esc_attr( $settings['redeem_monetary'] ); ?>" class="small-text" min="0" step="any">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label><?php esc_html_e( 'Partial Redemption', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'When enabled, customers can choose how many points to redeem instead of using all available points.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="kdna_points_settings[partial_redemption]" value="yes" <?php checked( $settings['partial_redemption'], 'yes' ); ?>>
                            <?php esc_html_e( 'Enable partial redemption', 'kdna-ecommerce' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Lets users enter how many points they wish to redeem during cart/checkout.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="min_discount"><?php esc_html_e( 'Minimum Points Discount', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'The minimum discount amount that can be redeemed. Leave blank for no minimum.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="text" id="min_discount" name="kdna_points_settings[min_discount]" value="<?php echo esc_attr( $settings['min_discount'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter amount', 'kdna-ecommerce' ); ?>">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="max_discount"><?php esc_html_e( 'Maximum Points Discount', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Maximum discount from points per order. Use a number for a fixed amount, or append % for a percentage of the cart total. Leave blank for no limit.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="text" id="max_discount" name="kdna_points_settings[max_discount]" value="<?php echo esc_attr( $settings['max_discount'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter amount or percentage value', 'kdna-ecommerce' ); ?>">
                        <p class="description"><?php esc_html_e( 'Enter a fixed amount (e.g. 50) or a percentage (e.g. 50%). Leave blank for no limit.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="max_product_discount"><?php esc_html_e( 'Maximum Product Points Discount', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Maximum discount per product from points. Use a number for a fixed amount, or append % for a percentage of the product price. Leave blank for no limit.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="text" id="max_product_discount" name="kdna_points_settings[max_product_discount]" value="<?php echo esc_attr( $settings['max_product_discount'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter amount or percentage value', 'kdna-ecommerce' ); ?>">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="tax_setting"><?php esc_html_e( 'Tax Setting', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Choose whether points are calculated on prices before or after tax.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <select id="tax_setting" name="kdna_points_settings[tax_setting]">
                            <option value="inclusive" <?php selected( $settings['tax_setting'], 'inclusive' ); ?>><?php esc_html_e( 'Apply points to price inclusive of taxes.', 'kdna-ecommerce' ); ?></option>
                            <option value="exclusive" <?php selected( $settings['tax_setting'], 'exclusive' ); ?>><?php esc_html_e( 'Apply points to price exclusive of taxes.', 'kdna-ecommerce' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="points_label_singular"><?php esc_html_e( 'Points Label', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'The singular and plural label for points shown to customers.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="text" id="points_label_singular" name="kdna_points_settings[points_label_singular]" value="<?php echo esc_attr( $settings['points_label_singular'] ); ?>" class="small-text">
                        <input type="text" id="points_label_plural" name="kdna_points_settings[points_label_plural]" value="<?php echo esc_attr( $settings['points_label_plural'] ); ?>" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="expiry_period"><?php esc_html_e( 'Points Expire After', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Set when points expire. Leave blank for no expiry.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="number" id="expiry_period" name="kdna_points_settings[expiry_period]" value="<?php echo esc_attr( $settings['expiry_period'] ); ?>" class="small-text" min="0">
                        <select name="kdna_points_settings[expiry_unit]">
                            <option value="months" <?php selected( $settings['expiry_unit'], 'months' ); ?>><?php esc_html_e( 'Months', 'kdna-ecommerce' ); ?></option>
                            <option value="days" <?php selected( $settings['expiry_unit'], 'days' ); ?>><?php esc_html_e( 'Days', 'kdna-ecommerce' ); ?></option>
                            <option value="years" <?php selected( $settings['expiry_unit'], 'years' ); ?>><?php esc_html_e( 'Years', 'kdna-ecommerce' ); ?></option>
                        </select>
                        <br>
                        <label style="margin-top:8px;display:inline-block;">
                            <?php esc_html_e( 'Only apply to points earned since', 'kdna-ecommerce' ); ?> - <em><?php esc_html_e( 'Optional', 'kdna-ecommerce' ); ?></em>
                            <input type="date" name="kdna_points_settings[expiry_since_date]" value="<?php echo esc_attr( $settings['expiry_since_date'] ); ?>" placeholder="YYYY-MM-DD">
                        </label>
                        <p class="description"><?php esc_html_e( 'Leave blank to apply to all points.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Product / Cart / Checkout Messages -->
            <h2><?php esc_html_e( 'Product / Cart / Checkout Messages', 'kdna-ecommerce' ); ?></h2>
            <p class="description">
                <?php
                printf(
                    /* translators: %1$s and %2$s are placeholder tokens */
                    esc_html__( 'Adjust the message by using %1$s and %2$s to represent the points earned / available for redemption and the label.', 'kdna-ecommerce' ),
                    '<code>{points}</code>',
                    '<code>{points_label}</code>'
                );
                ?>
            </p>
            <table class="form-table">
                <tr>
                    <th>
                        <label for="product_message"><?php esc_html_e( 'Single Product Page Message', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Displayed on the single product page. Use {points} and {points_label}.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <textarea id="product_message" name="kdna_points_settings[product_message]" rows="2" class="large-text"><?php echo esc_textarea( $settings['product_message'] ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="variable_product_message"><?php esc_html_e( 'Variable Product Page Message', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Displayed on variable product pages. Use {points} and {points_label}.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <textarea id="variable_product_message" name="kdna_points_settings[variable_product_message]" rows="2" class="large-text"><?php echo esc_textarea( $settings['variable_product_message'] ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="cart_message"><?php esc_html_e( 'Earn Points Cart/Checkout Page Message', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Shown on the cart and checkout pages. Use {points} and {points_label}.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <textarea id="cart_message" name="kdna_points_settings[cart_message]" rows="2" class="large-text"><?php echo esc_textarea( $settings['cart_message'] ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="redeem_message"><?php esc_html_e( 'Redeem Points Cart/Checkout Page Message', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Shown when points are available to redeem. Use {points}, {points_label}, {points_value}.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <textarea id="redeem_message" name="kdna_points_settings[redeem_message]" rows="2" class="large-text"><?php echo esc_textarea( $settings['redeem_message'] ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="thankyou_message"><?php esc_html_e( 'Thank You / Order Received Page Message', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Shown on the order confirmation page. Use {points}, {points_label}, {total_points}, {total_points_label}.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <textarea id="thankyou_message" name="kdna_points_settings[thankyou_message]" rows="3" class="large-text"><?php echo esc_textarea( $settings['thankyou_message'] ); ?></textarea>
                    </td>
                </tr>
            </table>

            <!-- Points Earned for Actions -->
            <h2><?php esc_html_e( 'Points Earned for Actions', 'kdna-ecommerce' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Customers can also earn points for actions like creating an account or writing a product review. You can enter the amount of points the customer will receive for each action.', 'kdna-ecommerce' ); ?></p>
            <table class="form-table">
                <tr>
                    <th>
                        <label for="earn_account_signup"><?php esc_html_e( 'Points earned for account signup', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Points awarded when a new account is created.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="number" id="earn_account_signup" name="kdna_points_settings[earn_account_signup]" value="<?php echo esc_attr( $settings['earn_account_signup'] ); ?>" class="small-text" min="0">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="earn_review"><?php esc_html_e( 'Points earned for writing a review', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Points awarded for the first review on a product.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="number" id="earn_review" name="kdna_points_settings[earn_review]" value="<?php echo esc_attr( $settings['earn_review'] ); ?>" class="small-text" min="0">
                    </td>
                </tr>
            </table>

            <!-- Actions -->
            <h2><?php esc_html_e( 'Actions', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th>
                        <label><?php esc_html_e( 'Apply Points to Previous Orders', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Award points for previously completed orders that were placed before the plugin was active.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <button type="button" class="button" id="kdna-apply-previous-orders"><?php esc_html_e( 'Apply Points', 'kdna-ecommerce' ); ?></button>
                        <span id="kdna-apply-previous-spinner" class="spinner" style="float:none;"></span>
                        <span id="kdna-apply-previous-result"></span>
                        <br>
                        <label style="margin-top:8px;display:inline-block;">
                            <?php esc_html_e( 'Since', 'kdna-ecommerce' ); ?> - <em><?php esc_html_e( 'Optional: Leave blank to apply to all orders', 'kdna-ecommerce' ); ?></em>
                            <br>
                            <input type="date" id="kdna-apply-previous-since" value="" placeholder="YYYY-MM-DD">
                        </label>
                    </td>
                </tr>
            </table>

            <!-- Advanced Settings -->
            <h2><?php esc_html_e( 'Advanced Settings', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Delete plugin data', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kdna_points_settings[delete_data_on_uninstall]" value="yes" <?php checked( $settings['delete_data_on_uninstall'], 'yes' ); ?>>
                            <?php esc_html_e( 'Delete plugin data when plugin is deleted', 'kdna-ecommerce' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( "This includes plugin settings, users' points, points log, point settings for products, point set categories, and point set actions.", 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function render_reviews_tab( $modules ) {
        $settings = get_option( 'kdna_reviews_settings', [] );
        $defaults = [
            'contribution_types'         => 'all',
            'specific_types'             => [],
            'admins_can_reply'           => 'no',
            'admin_badge'                => 'Admin',
            'sorting_order'              => 'most_helpful',
            'min_word_count'             => '',
            'max_word_count'             => '',
            'publication_threshold'      => '1',
            'flagged_handling'           => 'keep_published',
            'moderation'                 => 'yes',
            'enable_reviews'             => 'yes',
            'verified_owner_label'       => 'yes',
            'verified_owners_only'       => 'yes',
            'enable_star_rating'         => 'yes',
            'star_rating_required'       => 'yes',
            'enable_photos'              => 'yes',
            'enable_videos'              => 'yes',
            'enable_voting'              => 'yes',
            'enable_flagging'            => 'yes',
            'enable_qualifiers'          => 'no',
            'qualifier_labels'           => '',
            'max_attachments'            => '5',
            'max_file_size'              => '5',
        ];
        $settings = wp_parse_args( $settings, $defaults );
        $active = ( $modules['reviews'] ?? 'no' ) === 'yes';
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'kdna_ecommerce_reviews' ); ?>

            <?php if ( ! $active ) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'This module is currently disabled. Enable it in the General tab.', 'kdna-ecommerce' ); ?></p></div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Reviews', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th>
                        <label for="contribution_types"><?php esc_html_e( 'Contributions types', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Choose which contribution types are enabled.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <select id="contribution_types" name="kdna_reviews_settings[contribution_types]">
                            <option value="all" <?php selected( $settings['contribution_types'], 'all' ); ?>><?php esc_html_e( 'Enable all contribution types', 'kdna-ecommerce' ); ?></option>
                            <option value="specific" <?php selected( $settings['contribution_types'], 'specific' ); ?>><?php esc_html_e( 'Enable specific contribution types only', 'kdna-ecommerce' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="kdna-specific-types-row" style="<?php echo $settings['contribution_types'] !== 'specific' ? 'display:none;' : ''; ?>">
                    <th><label><?php esc_html_e( 'Specific contribution types', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <?php
                        $types = [ 'review' => 'Reviews', 'question' => 'Questions', 'photo' => 'Photos', 'video' => 'Videos' ];
                        $specific = (array) ( $settings['specific_types'] ?? [] );
                        foreach ( $types as $val => $label ) : ?>
                        <label style="display:block;margin-bottom:4px;">
                            <input type="checkbox" name="kdna_reviews_settings[specific_types][]" value="<?php echo esc_attr( $val ); ?>" <?php checked( in_array( $val, $specific, true ) ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Admins can always reply', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kdna_reviews_settings[admins_can_reply]" value="yes" <?php checked( $settings['admins_can_reply'], 'yes' ); ?>>
                            <?php esc_html_e( 'Allow administrators and shop managers to leave replies to contributions', 'kdna-ecommerce' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="admin_badge"><?php esc_html_e( 'Admin badges', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Text shown next to admin/shop manager names on their contributions.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="text" id="admin_badge" name="kdna_reviews_settings[admin_badge]" value="<?php echo esc_attr( $settings['admin_badge'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Leave blank to disable badges.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="sorting_order"><?php esc_html_e( 'Sorting order', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Default sort order for reviews on the frontend.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <select id="sorting_order" name="kdna_reviews_settings[sorting_order]">
                            <option value="most_helpful" <?php selected( $settings['sorting_order'], 'most_helpful' ); ?>><?php esc_html_e( 'Most helpful first', 'kdna-ecommerce' ); ?></option>
                            <option value="newest" <?php selected( $settings['sorting_order'], 'newest' ); ?>><?php esc_html_e( 'Newest first', 'kdna-ecommerce' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="min_word_count"><?php esc_html_e( 'Minimum word count', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Minimum number of words required for a review. Leave blank for no minimum.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="number" id="min_word_count" name="kdna_reviews_settings[min_word_count]" value="<?php echo esc_attr( $settings['min_word_count'] ); ?>" class="regular-text" min="0">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="max_word_count"><?php esc_html_e( 'Maximum word count', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Maximum number of words allowed for a review. Leave blank for no maximum.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="number" id="max_word_count" name="kdna_reviews_settings[max_word_count]" value="<?php echo esc_attr( $settings['max_word_count'] ); ?>" class="regular-text" min="0">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="publication_threshold"><?php esc_html_e( 'Threshold for publication', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Minimum number of contributions needed before they are publicly displayed.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="number" id="publication_threshold" name="kdna_reviews_settings[publication_threshold]" value="<?php echo esc_attr( $settings['publication_threshold'] ); ?>" class="regular-text" min="1">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="flagged_handling"><?php esc_html_e( 'Flagged contributions', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'What happens when a contribution is flagged.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <select id="flagged_handling" name="kdna_reviews_settings[flagged_handling]">
                            <option value="keep_published" <?php selected( $settings['flagged_handling'], 'keep_published' ); ?>><?php esc_html_e( 'Keep published', 'kdna-ecommerce' ); ?></option>
                            <option value="pending_customer" <?php selected( $settings['flagged_handling'], 'pending_customer' ); ?>><?php esc_html_e( 'Set to pending if flagged by customer', 'kdna-ecommerce' ); ?></option>
                            <option value="pending_anyone" <?php selected( $settings['flagged_handling'], 'pending_anyone' ); ?>><?php esc_html_e( 'Set to pending if flagged by anyone', 'kdna-ecommerce' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Moderation', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kdna_reviews_settings[moderation]" value="yes" <?php checked( $settings['moderation'], 'yes' ); ?>>
                            <?php esc_html_e( 'Contributions must be manually approved', 'kdna-ecommerce' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Enable reviews', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label style="display:block;margin-bottom:4px;">
                            <input type="checkbox" name="kdna_reviews_settings[enable_reviews]" value="yes" <?php checked( $settings['enable_reviews'], 'yes' ); ?>>
                            <?php esc_html_e( 'Enable product reviews', 'kdna-ecommerce' ); ?>
                        </label>
                        <label style="display:block;margin-bottom:4px;">
                            <input type="checkbox" name="kdna_reviews_settings[verified_owner_label]" value="yes" <?php checked( $settings['verified_owner_label'], 'yes' ); ?>>
                            <?php esc_html_e( 'Show "verified owner" label on customer reviews', 'kdna-ecommerce' ); ?>
                        </label>
                        <label style="display:block;">
                            <input type="checkbox" name="kdna_reviews_settings[verified_owners_only]" value="yes" <?php checked( $settings['verified_owners_only'], 'yes' ); ?>>
                            <?php esc_html_e( 'Reviews can only be left by "verified owners"', 'kdna-ecommerce' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Product ratings', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label style="display:block;margin-bottom:4px;">
                            <input type="checkbox" name="kdna_reviews_settings[enable_star_rating]" value="yes" <?php checked( $settings['enable_star_rating'], 'yes' ); ?>>
                            <?php esc_html_e( 'Enable star rating on reviews', 'kdna-ecommerce' ); ?>
                        </label>
                        <label style="display:block;">
                            <input type="checkbox" name="kdna_reviews_settings[star_rating_required]" value="yes" <?php checked( $settings['star_rating_required'], 'yes' ); ?>>
                            <?php esc_html_e( 'Star ratings should be required, not optional', 'kdna-ecommerce' ); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Review Features', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Photo Uploads', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_reviews_settings[enable_photos]" value="yes" <?php checked( $settings['enable_photos'], 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Video Uploads', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_reviews_settings[enable_videos]" value="yes" <?php checked( $settings['enable_videos'], 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Review Voting', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_reviews_settings[enable_voting]" value="yes" <?php checked( $settings['enable_voting'], 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Review Flagging', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_reviews_settings[enable_flagging]" value="yes" <?php checked( $settings['enable_flagging'], 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Review Qualifiers', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_reviews_settings[enable_qualifiers]" value="yes" <?php checked( $settings['enable_qualifiers'], 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Add extra rating criteria (e.g., Quality, Value, etc.)', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="qualifier_labels"><?php esc_html_e( 'Qualifier Labels', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" id="qualifier_labels" name="kdna_reviews_settings[qualifier_labels]" value="<?php echo esc_attr( $settings['qualifier_labels'] ); ?>" class="large-text">
                        <p class="description"><?php esc_html_e( 'Comma-separated labels, e.g.: Quality, Value, Shipping', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Upload Settings', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="max_attachments"><?php esc_html_e( 'Max Attachments per Review', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="number" id="max_attachments" name="kdna_reviews_settings[max_attachments]" value="<?php echo esc_attr( $settings['max_attachments'] ); ?>" min="1" max="20"></td>
                </tr>
                <tr>
                    <th><label for="max_file_size"><?php esc_html_e( 'Max File Size (MB)', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="number" id="max_file_size" name="kdna_reviews_settings[max_file_size]" value="<?php echo esc_attr( $settings['max_file_size'] ); ?>" min="1" max="50"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <script>
        jQuery(function($) {
            $('#contribution_types').on('change', function() {
                $('#kdna-specific-types-row').toggle($(this).val() === 'specific');
            });
        });
        </script>
        <?php
    }

    private function render_related_tab( $modules ) {
        $settings = get_option( 'kdna_related_settings', [] );
        $defaults = [
            'section_title'   => 'Related Products',
            'products_count'  => '4',
        ];
        $settings = wp_parse_args( $settings, $defaults );
        $active = ( $modules['related_products'] ?? 'no' ) === 'yes';
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'kdna_ecommerce_related' ); ?>

            <?php if ( ! $active ) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'This module is currently disabled. Enable it in the General tab.', 'kdna-ecommerce' ); ?></p></div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Related Products Settings', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="section_title"><?php esc_html_e( 'Section Title', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="text" id="section_title" name="kdna_related_settings[section_title]" value="<?php echo esc_attr( $settings['section_title'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="products_count"><?php esc_html_e( 'Number of Products to Show', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="number" id="products_count" name="kdna_related_settings[products_count]" value="<?php echo esc_attr( $settings['products_count'] ); ?>" min="1" max="20"></td>
                </tr>
            </table>
            <p class="description"><?php esc_html_e( 'Related products are selected per product in the Product Data > Linked Products tab, similar to Upsells and Cross-sells.', 'kdna-ecommerce' ); ?></p>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function render_sequential_tab( $modules ) {
        $settings = get_option( 'kdna_sequential_settings', [] );
        $defaults = [
            'start_number'      => '1',
            'prefix'            => '',
            'suffix'            => '',
            'skip_free_orders'  => 'no',
            'free_prefix'       => 'FREE-',
            'free_start_number' => '1',
        ];
        $settings = wp_parse_args( $settings, $defaults );
        $active = ( $modules['sequential_orders'] ?? 'no' ) === 'yes';
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'kdna_ecommerce_sequential' ); ?>

            <?php if ( ! $active ) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'This module is currently disabled. Enable it in the General tab.', 'kdna-ecommerce' ); ?></p></div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Order Number Format', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="start_number"><?php esc_html_e( 'Starting Number', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" id="start_number" name="kdna_sequential_settings[start_number]" value="<?php echo esc_attr( $settings['start_number'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Leading zeros set minimum padding (e.g., "00100" = 5-digit numbers starting at 100). Supports date patterns: {DD}, {MM}, {YY}, {YYYY}', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="prefix"><?php esc_html_e( 'Order Number Prefix', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" id="prefix" name="kdna_sequential_settings[prefix]" value="<?php echo esc_attr( $settings['prefix'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Supports date patterns: {DD}, {MM}, {YY}, {YYYY}, {H}, {HH}, {N}, {S}', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="suffix"><?php esc_html_e( 'Order Number Suffix', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="text" id="suffix" name="kdna_sequential_settings[suffix]" value="<?php echo esc_attr( $settings['suffix'] ); ?>" class="regular-text"></td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Free Orders', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Separate Free Order Numbers', 'kdna-ecommerce' ); ?></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="kdna_sequential_settings[skip_free_orders]" value="yes" <?php checked( $settings['skip_free_orders'], 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="free_prefix"><?php esc_html_e( 'Free Order Prefix', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="text" id="free_prefix" name="kdna_sequential_settings[free_prefix]" value="<?php echo esc_attr( $settings['free_prefix'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="free_start_number"><?php esc_html_e( 'Free Order Starting Number', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="text" id="free_start_number" name="kdna_sequential_settings[free_start_number]" value="<?php echo esc_attr( $settings['free_start_number'] ); ?>" class="regular-text"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function render_australia_post_tab( $modules ) {
        $is_active = ( $modules['australia_post'] ?? 'no' ) === 'yes';
        ?>
        <?php if ( ! $is_active ) : ?>
            <div class="notice notice-warning inline" style="margin-top:15px;">
                <p><?php esc_html_e( 'The Australia Post module is currently disabled. Enable it in the General tab.', 'kdna-ecommerce' ); ?></p>
            </div>
        <?php endif; ?>

        <div class="kdna-module-info" style="margin-top:15px;">
            <h2><?php esc_html_e( 'Australia Post Shipping', 'kdna-ecommerce' ); ?></h2>
            <p><?php esc_html_e( 'This module adds an Australia Post shipping method to WooCommerce. Once enabled, configure it via:', 'kdna-ecommerce' ); ?></p>
            <ol>
                <li><?php
                    printf(
                        wp_kses(
                            __( 'Go to <strong>WooCommerce &gt; Settings &gt; Shipping</strong> and edit a shipping zone (or add a new one).', 'kdna-ecommerce' ),
                            array( 'strong' => array() )
                        )
                    );
                ?></li>
                <li><?php
                    printf(
                        wp_kses(
                            __( 'Click <strong>Add shipping method</strong> and select <strong>Australia Post</strong>.', 'kdna-ecommerce' ),
                            array( 'strong' => array() )
                        )
                    );
                ?></li>
                <li><?php esc_html_e( 'Configure your origin postcode, packing method, enabled services, and other options.', 'kdna-ecommerce' ); ?></li>
            </ol>

            <h3><?php esc_html_e( 'Global API Settings', 'kdna-ecommerce' ); ?></h3>
            <p><?php
                printf(
                    wp_kses(
                        __( 'The global API key and debug settings are configured in the shipping method\'s global settings at <strong>WooCommerce &gt; Settings &gt; Shipping &gt; Australia Post</strong> (click the method name at the top of the Shipping tab).', 'kdna-ecommerce' ),
                        array( 'strong' => array() )
                    )
                );
            ?></p>

            <h3><?php esc_html_e( 'Features', 'kdna-ecommerce' ); ?></h3>
            <ul style="list-style:disc; padding-left:20px;">
                <li><?php esc_html_e( 'Live rates from the Australia Post Postage Assessment Calculator API', 'kdna-ecommerce' ); ?></li>
                <li><?php esc_html_e( 'Domestic services: Regular/Parcel Post, Express Post, Courier Post', 'kdna-ecommerce' ); ?></li>
                <li><?php esc_html_e( 'International services: Standard, Express, Courier, Economy Air, Economy Sea', 'kdna-ecommerce' ); ?></li>
                <li><?php esc_html_e( 'Three packing methods: per-item, weight-based, or box packing', 'kdna-ecommerce' ); ?></li>
                <li><?php esc_html_e( 'Custom box sizes with inner/outer dimensions', 'kdna-ecommerce' ); ?></li>
                <li><?php esc_html_e( 'Default satchel sizes included', 'kdna-ecommerce' ); ?></li>
                <li><?php esc_html_e( 'Extra Cover and Signature on Delivery options', 'kdna-ecommerce' ); ?></li>
                <li><?php esc_html_e( 'Per-service price adjustments (flat or percentage)', 'kdna-ecommerce' ); ?></li>
                <li><?php esc_html_e( 'Tax-exclusive rate calculation option', 'kdna-ecommerce' ); ?></li>
                <li><?php esc_html_e( 'Response caching for performance', 'kdna-ecommerce' ); ?></li>
            </ul>
        </div>
        <?php
    }

    private function render_tracking_tab( $modules ) {
        $is_active = ( $modules['shipment_tracking'] ?? 'no' ) === 'yes';
        ?>
        <?php if ( ! $is_active ) : ?>
            <div class="notice notice-warning inline" style="margin-top:15px;">
                <p><?php esc_html_e( 'The Shipment Tracking module is currently disabled. Enable it in the General tab.', 'kdna-ecommerce' ); ?></p>
            </div>
        <?php endif; ?>

        <div class="kdna-module-info" style="margin-top:15px;">
            <h2><?php esc_html_e( 'Shipment Tracking', 'kdna-ecommerce' ); ?></h2>
            <p><?php esc_html_e( 'This module adds shipment tracking to individual WooCommerce orders. Once enabled:', 'kdna-ecommerce' ); ?></p>
            <ol>
                <li><?php
                    printf(
                        wp_kses(
                            __( 'Edit any order and use the <strong>Shipment Tracking</strong> metabox on the right side to add tracking numbers.', 'kdna-ecommerce' ),
                            array( 'strong' => array() )
                        )
                    );
                ?></li>
                <li><?php esc_html_e( 'Choose from a built-in list of shipping providers or enter a custom provider with tracking URL.', 'kdna-ecommerce' ); ?></li>
                <li><?php esc_html_e( 'Multiple tracking numbers can be added per order.', 'kdna-ecommerce' ); ?></li>
            </ol>

            <h3><?php esc_html_e( 'Email Integration', 'kdna-ecommerce' ); ?></h3>
            <p><?php esc_html_e( 'Tracking information is automatically included in WooCommerce order emails (including the Completed order email). When you mark an order as complete, the customer receives their tracking details.', 'kdna-ecommerce' ); ?></p>

            <h3><?php esc_html_e( 'Customer View', 'kdna-ecommerce' ); ?></h3>
            <p><?php esc_html_e( 'Customers can view tracking information on their My Account > View Order page with direct links to the carrier tracking page.', 'kdna-ecommerce' ); ?></p>

            <h3><?php esc_html_e( 'Supported Providers', 'kdna-ecommerce' ); ?></h3>
            <p><?php esc_html_e( 'Built-in support for providers including:', 'kdna-ecommerce' ); ?></p>
            <ul style="list-style:disc; padding-left:20px; column-count:3;">
                <li>Australia Post</li>
                <li>Aramex</li>
                <li>Canada Post</li>
                <li>DHL</li>
                <li>DPD</li>
                <li>EVRi</li>
                <li>FedEx</li>
                <li>Fastway</li>
                <li>NZ Post</li>
                <li>PostNL</li>
                <li>Royal Mail</li>
                <li>Sendle</li>
                <li>StarTrack</li>
                <li>TNT Express</li>
                <li>UPS</li>
                <li>USPS</li>
            </ul>
            <p><?php esc_html_e( 'Plus many more regional providers. Custom providers can be added per-order.', 'kdna-ecommerce' ); ?></p>
        </div>
        <?php
    }

    private function render_invoice_tab( $modules ) {
        $settings = wp_parse_args(
            get_option( 'kdna_invoice_settings', [] ),
            [
                'logo_id'      => '',
                'accent_color' => '#C8E600',
                'footer_text'  => '',
            ]
        );
        $active   = ( $modules['tax_invoice'] ?? 'no' ) === 'yes';
        $logo_url = $settings['logo_id'] ? wp_get_attachment_image_url( $settings['logo_id'], 'medium' ) : '';
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'kdna_ecommerce_invoice' ); ?>

            <?php if ( ! $active ) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'This module is currently disabled. Enable it in the General tab.', 'kdna-ecommerce' ); ?></p></div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Tax Invoice Settings', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th>
                        <label><?php esc_html_e( 'Invoice Logo', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Upload the logo displayed at the top-left of the invoice.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="hidden" id="kdna_invoice_logo_id" name="kdna_invoice_settings[logo_id]" value="<?php echo esc_attr( $settings['logo_id'] ); ?>">
                        <div id="kdna-invoice-logo-preview" style="margin-bottom:10px;">
                            <?php if ( $logo_url ) : ?>
                                <img src="<?php echo esc_url( $logo_url ); ?>" style="max-height:80px;">
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button" id="kdna-upload-logo"><?php esc_html_e( 'Upload Logo', 'kdna-ecommerce' ); ?></button>
                        <button type="button" class="button" id="kdna-remove-logo" style="<?php echo $settings['logo_id'] ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'kdna-ecommerce' ); ?></button>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="kdna_accent_color"><?php esc_html_e( 'Accent Colour', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Hex colour used for the top bar, table header, and Total Paid row.', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <input type="text" id="kdna_accent_color" name="kdna_invoice_settings[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" class="kdna-color-picker" data-default-color="#C8E600">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label><?php esc_html_e( 'Footer Text', 'kdna-ecommerce' ); ?></label>
                        <span class="kdna-tooltip dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Content displayed at the bottom of the invoice (e.g. company name, ABN, address).', 'kdna-ecommerce' ); ?>"></span>
                    </th>
                    <td>
                        <?php
                        wp_editor( $settings['footer_text'], 'kdna_invoice_footer_text', [
                            'textarea_name' => 'kdna_invoice_settings[footer_text]',
                            'textarea_rows' => 6,
                            'media_buttons' => false,
                            'teeny'         => false,
                            'quicktags'     => true,
                        ] );
                        ?>
                        <p class="description"><?php esc_html_e( 'Use HTML for formatting. Example: company name, ABN, address, email, website.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <hr>
        <h3><?php esc_html_e( 'Preview', 'kdna-ecommerce' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Download a test PDF using your current settings with dummy customer and product data.', 'kdna-ecommerce' ); ?></p>
        <p>
            <?php
            $test_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=kdna_test_invoice' ),
                'kdna_test_invoice'
            );
            ?>
            <a href="<?php echo esc_url( $test_url ); ?>" class="button button-secondary" target="_blank">
                <?php esc_html_e( 'Download Test PDF', 'kdna-ecommerce' ); ?>
            </a>
        </p>

        <script>
        jQuery(function($) {
            // Colour picker
            $('.kdna-color-picker').wpColorPicker();

            // Logo upload
            var mediaFrame;
            $('#kdna-upload-logo').on('click', function(e) {
                e.preventDefault();
                if (mediaFrame) { mediaFrame.open(); return; }
                mediaFrame = wp.media({
                    title: '<?php echo esc_js( __( 'Select Invoice Logo', 'kdna-ecommerce' ) ); ?>',
                    button: { text: '<?php echo esc_js( __( 'Use as Logo', 'kdna-ecommerce' ) ); ?>' },
                    multiple: false,
                    library: { type: 'image' }
                });
                mediaFrame.on('select', function() {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    $('#kdna_invoice_logo_id').val(attachment.id);
                    var imgUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    $('#kdna-invoice-logo-preview').html('<img src="' + imgUrl + '" style="max-height:80px;">');
                    $('#kdna-remove-logo').show();
                });
                mediaFrame.open();
            });

            $('#kdna-remove-logo').on('click', function(e) {
                e.preventDefault();
                $('#kdna_invoice_logo_id').val('');
                $('#kdna-invoice-logo-preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    private function render_smart_coupons_tab( $modules ) {
        require_once KDNA_ECOMMERCE_PATH . 'modules/smart-coupons/class-kdna-smart-coupons.php';
        $s      = KDNA_Smart_Coupons::get_settings();
        $active = ( $modules['smart_coupons'] ?? 'no' ) === 'yes';
        $sub    = isset( $_GET['sc_section'] ) ? sanitize_text_field( $_GET['sc_section'] ) : 'general';
        $n      = 'kdna_smart_coupons_settings';

        $sections = [
            'general'    => __( 'General', 'kdna-ecommerce' ),
            'customize'  => __( 'Customize coupons', 'kdna-ecommerce' ),
            'display'    => __( 'Display coupons', 'kdna-ecommerce' ),
            'tax'        => __( 'Tax', 'kdna-ecommerce' ),
            'labels'     => __( 'Labels', 'kdna-ecommerce' ),
            'send_form'  => __( 'Send coupon form', 'kdna-ecommerce' ),
            'emails'     => __( 'Emails', 'kdna-ecommerce' ),
            'cashback'   => __( 'Cashback Rewards', 'kdna-ecommerce' ),
        ];
        ?>

        <?php if ( ! $active ) : ?>
            <div class="notice notice-warning inline"><p><?php esc_html_e( 'This module is currently disabled. Enable it in the General tab.', 'kdna-ecommerce' ); ?></p></div>
        <?php endif; ?>

        <p class="kdna-sc-sub-nav" style="margin:12px 0 18px;">
            <?php foreach ( $sections as $key => $label ) : ?>
                <?php $url = add_query_arg( [ 'page' => 'kdna-ecommerce', 'tab' => 'smart_coupons', 'sc_section' => $key ] ); ?>
                <?php if ( $key === $sub ) : ?>
                    <strong><?php echo esc_html( $label ); ?></strong>
                <?php else : ?>
                    <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endif; ?>
                <?php if ( $key !== array_key_last( $sections ) ) echo ' | '; ?>
            <?php endforeach; ?>
        </p>

        <form method="post" action="options.php">
            <?php settings_fields( 'kdna_ecommerce_smart_coupons' ); ?>
            <?php
            // Preserve fields from other sub-sections as hidden inputs.
            $all_defaults = KDNA_Smart_Coupons::get_default_settings();
            foreach ( $s as $k => $v ) {
                if ( is_array( $v ) ) {
                    foreach ( $v as $vv ) {
                        echo '<input type="hidden" class="kdna-sc-preserve" name="' . esc_attr( $n ) . '[' . esc_attr( $k ) . '][]" value="' . esc_attr( $vv ) . '">';
                    }
                } else {
                    echo '<input type="hidden" class="kdna-sc-preserve" name="' . esc_attr( $n ) . '[' . esc_attr( $k ) . ']" value="' . esc_attr( $v ) . '">';
                }
            }
            ?>

        <?php // ===================== GENERAL ===================== ?>
        <?php if ( $sub === 'general' ) : ?>
            <h2><?php esc_html_e( 'General', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Number of coupons to show', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="number" name="<?php echo esc_attr( $n ); ?>[max_coupons_shown]" value="<?php echo esc_attr( $s['max_coupons_shown'] ); ?>" class="small-text" min="0">
                        <p class="description"><?php esc_html_e( 'How many coupons (at max) should be shown on cart, checkout & my account page? If set to 0 then coupons will not be displayed.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Number of characters in auto-generated coupon code', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="number" name="<?php echo esc_attr( $n ); ?>[coupon_code_length]" value="<?php echo esc_attr( $s['coupon_code_length'] ); ?>" class="small-text" min="6" max="20">
                        <p class="description"><?php esc_html_e( 'Excluding prefix and/or suffix. Default is 13. Recommended: 10 to 15 to avoid duplication.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Valid order status for auto-generating coupon', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <?php
                        $wc_statuses = wc_get_order_statuses();
                        $selected    = (array) $s['valid_order_statuses'];
                        foreach ( $wc_statuses as $slug => $label ) :
                            $clean = str_replace( 'wc-', '', $slug );
                        ?>
                            <label style="display:inline-block;margin-right:12px;">
                                <input type="checkbox" name="<?php echo esc_attr( $n ); ?>[valid_order_statuses][]" value="<?php echo esc_attr( $clean ); ?>" <?php checked( in_array( $clean, $selected, true ) ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( 'Order statuses that trigger auto-generation of coupons.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Auto apply coupons', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enable_auto_apply]" value="yes" <?php checked( $s['enable_auto_apply'], 'yes' ); ?>>
                        <?php esc_html_e( 'When enabled, each coupon will have the option to enable auto-apply for that coupon', 'kdna-ecommerce' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Disabling this, no coupons will be auto-applied — even if any coupon has "Auto apply?" enabled.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Automatic deletion', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[delete_after_use]" value="yes" <?php checked( $s['delete_after_use'], 'yes' ); ?>>
                        <?php esc_html_e( 'Delete the store credit when entire credit amount is used up', 'kdna-ecommerce' ); ?></label>
                        <span class="description">(<?php esc_html_e( "It's recommended to keep it Disabled", 'kdna-ecommerce' ); ?>)</span>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Coupon emails', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[send_coupon_email]" value="yes" <?php checked( $s['send_coupon_email'], 'yes' ); ?>>
                        <?php esc_html_e( 'Email auto generated coupons to recipients', 'kdna-ecommerce' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Printing coupons', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enable_printing]" value="yes" <?php checked( $s['enable_printing'], 'yes' ); ?>>
                        <?php esc_html_e( 'Enable feature to allow printing of coupons', 'kdna-ecommerce' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Sell store credits at less price?', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[sell_credit_at_less_price]" value="yes" <?php checked( $s['sell_credit_at_less_price'], 'yes' ); ?>>
                        <?php esc_html_e( 'Allow selling store credits at discounted price', 'kdna-ecommerce' ); ?></label>
                        <p class="description"><?php esc_html_e( 'When selling store credit, if Regular and Sale price is found, coupon will be created with Regular Price but customer pays Sale price.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Display Discounted Price', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_discounted_price]" value="yes" <?php checked( $s['show_discounted_price'], 'yes' ); ?>>
                        <?php esc_html_e( 'Show both the original (crossed-out) price and the discounted price in the cart when coupons are applied.', 'kdna-ecommerce' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'URL Coupons', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enable_url_coupons]" value="yes" <?php checked( $s['enable_url_coupons'], 'yes' ); ?>>
                        <?php esc_html_e( 'Allow applying coupons via URL', 'kdna-ecommerce' ); ?></label>
                        <p class="description"><code><?php echo esc_html( home_url( '/?apply_coupon=CODE' ) ); ?></code> <?php esc_html_e( 'or', 'kdna-ecommerce' ); ?> <code><?php echo esc_html( home_url( '/coupon/CODE/' ) ); ?></code></p>
                    </td>
                </tr>
            </table>

        <?php // ===================== CUSTOMIZE COUPONS ===================== ?>
        <?php elseif ( $sub === 'customize' ) : ?>
            <h2><?php esc_html_e( 'Customize Coupons', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Background Colour', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="text" name="<?php echo esc_attr( $n ); ?>[custom_bg_color]" value="<?php echo esc_attr( $s['custom_bg_color'] ); ?>" class="kdna-color-picker" data-default-color="#39cccc"></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Foreground / Text Colour', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="text" name="<?php echo esc_attr( $n ); ?>[custom_fg_color]" value="<?php echo esc_attr( $s['custom_fg_color'] ); ?>" class="kdna-color-picker" data-default-color="#30050b"></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Accent / Third Colour', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="text" name="<?php echo esc_attr( $n ); ?>[custom_third_color]" value="<?php echo esc_attr( $s['custom_third_color'] ); ?>" class="kdna-color-picker" data-default-color="#39cccc"></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Coupon Style', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <?php
                        $designs = [
                            'basic'     => __( 'Basic', 'kdna-ecommerce' ),
                            'flat'      => __( 'Flat', 'kdna-ecommerce' ),
                            'promotion' => __( 'Promotion', 'kdna-ecommerce' ),
                            'ticket'    => __( 'Ticket', 'kdna-ecommerce' ),
                            'festive'   => __( 'Festive', 'kdna-ecommerce' ),
                            'special'   => __( 'Special', 'kdna-ecommerce' ),
                            'shipment'  => __( 'Shipment', 'kdna-ecommerce' ),
                            'cutout'    => __( 'Cutout', 'kdna-ecommerce' ),
                            'deliver'   => __( 'Deliver', 'kdna-ecommerce' ),
                            'clipper'   => __( 'Clipper', 'kdna-ecommerce' ),
                            'deal'      => __( 'Deal', 'kdna-ecommerce' ),
                            'minimal'   => __( 'Minimal', 'kdna-ecommerce' ),
                            'bold'      => __( 'Bold', 'kdna-ecommerce' ),
                        ];
                        foreach ( $designs as $val => $label ) : ?>
                            <label style="display:inline-block;margin:0 12px 12px 0;">
                                <input type="radio" name="<?php echo esc_attr( $n ); ?>[coupon_design]" value="<?php echo esc_attr( $val ); ?>" <?php checked( $s['coupon_design'], $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( 'Choose the visual style for coupon cards on the website.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Style for email', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <select name="<?php echo esc_attr( $n ); ?>[coupon_email_design]">
                            <option value="email-coupon" <?php selected( $s['coupon_email_design'], 'email-coupon' ); ?>><?php esc_html_e( 'Default email style', 'kdna-ecommerce' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>

        <?php // ===================== DISPLAY COUPONS ===================== ?>
        <?php elseif ( $sub === 'display' ) : ?>
            <h2><?php esc_html_e( 'Display Coupons', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Storewide offer coupon', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( $n ); ?>[storewide_coupon_code]" value="<?php echo esc_attr( $s['storewide_coupon_code'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter coupon code', 'kdna-ecommerce' ); ?>">
                        <p class="description"><?php esc_html_e( 'Coupon to display as store notice banner. Leave empty to disable.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Store notice design', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <select name="<?php echo esc_attr( $n ); ?>[store_notice_design]">
                            <option value="notification" <?php selected( $s['store_notice_design'], 'notification' ); ?>><?php esc_html_e( 'Notification', 'kdna-ecommerce' ); ?></option>
                            <option value="balloon" <?php selected( $s['store_notice_design'], 'balloon' ); ?>><?php esc_html_e( 'Balloon', 'kdna-ecommerce' ); ?></option>
                            <option value="gift-box" <?php selected( $s['store_notice_design'], 'gift-box' ); ?>><?php esc_html_e( 'Gift Box', 'kdna-ecommerce' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Show associated coupons on product page', 'kdna-ecommerce' ); ?></label></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_associated_on_product]" value="yes" <?php checked( $s['show_associated_on_product'], 'yes' ); ?>> <?php esc_html_e( 'Include coupon details on product page for products that issue coupons', 'kdna-ecommerce' ); ?></label></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Show on My Account', 'kdna-ecommerce' ); ?></label></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_on_myaccount]" value="yes" <?php checked( $s['show_on_myaccount'], 'yes' ); ?>> <?php esc_html_e( 'Show coupons on My Account > Coupons page', 'kdna-ecommerce' ); ?></label></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Show received coupons', 'kdna-ecommerce' ); ?></label></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_received_on_myaccount]" value="yes" <?php checked( $s['show_received_on_myaccount'], 'yes' ); ?>> <?php esc_html_e( 'Include coupons received from other people on My Account', 'kdna-ecommerce' ); ?></label></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Show invalid coupons', 'kdna-ecommerce' ); ?></label></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_invalid_on_myaccount]" value="yes" <?php checked( $s['show_invalid_on_myaccount'], 'yes' ); ?>> <?php esc_html_e( 'Show expired/used coupons in My Account', 'kdna-ecommerce' ); ?></label></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Show coupon description', 'kdna-ecommerce' ); ?></label></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_coupon_description]" value="yes" <?php checked( $s['show_coupon_description'], 'yes' ); ?>> <?php esc_html_e( 'Display coupon description alongside the coupon code', 'kdna-ecommerce' ); ?></label></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Show on Cart', 'kdna-ecommerce' ); ?></label></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_on_cart]" value="yes" <?php checked( $s['show_on_cart'], 'yes' ); ?>> <?php esc_html_e( 'Show available coupons on Cart page', 'kdna-ecommerce' ); ?></label></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Show on Checkout', 'kdna-ecommerce' ); ?></label></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_on_checkout]" value="yes" <?php checked( $s['show_on_checkout'], 'yes' ); ?>> <?php esc_html_e( 'Show available coupons on Checkout page', 'kdna-ecommerce' ); ?></label></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Always show coupons section', 'kdna-ecommerce' ); ?></label></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[always_show_section]" value="yes" <?php checked( $s['always_show_section'], 'yes' ); ?>> <?php esc_html_e( 'Always show the coupons section even if no coupons are available', 'kdna-ecommerce' ); ?></label></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Default section open', 'kdna-ecommerce' ); ?></label></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[default_section_open]" value="yes" <?php checked( $s['default_section_open'], 'yes' ); ?>> <?php esc_html_e( 'Coupon section is expanded by default', 'kdna-ecommerce' ); ?></label></td>
                </tr>
            </table>

        <?php // ===================== TAX ===================== ?>
        <?php elseif ( $sub === 'tax' ) : ?>
            <h2><?php esc_html_e( 'Tax', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Include tax in store credit', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[include_tax]" value="yes" <?php checked( $s['include_tax'], 'yes' ); ?>>
                        <?php esc_html_e( 'Apply store credit to price inclusive of taxes', 'kdna-ecommerce' ); ?></label>
                        <p class="description"><?php esc_html_e( 'When enabled, store credit discount calculations will include tax amounts.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
            </table>

        <?php // ===================== LABELS ===================== ?>
        <?php elseif ( $sub === 'labels' ) : ?>
            <h2><?php esc_html_e( 'Labels', 'kdna-ecommerce' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Use these to quickly change text labels through your store.', 'kdna-ecommerce' ); ?></p>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Store Credit / Gift Certificate', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( $n ); ?>[credit_label_singular]" value="<?php echo esc_attr( $s['credit_label_singular'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Singular name', 'kdna-ecommerce' ); ?>">
                        <p class="description"><?php esc_html_e( 'Singular name for Store Credit / Gift Certificate.', 'kdna-ecommerce' ); ?></p>
                        <br>
                        <input type="text" name="<?php echo esc_attr( $n ); ?>[credit_label_plural]" value="<?php echo esc_attr( $s['credit_label_plural'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Plural name', 'kdna-ecommerce' ); ?>">
                        <p class="description"><?php esc_html_e( 'Plural name for the above singular name.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Store credit product CTA', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( $n ); ?>[credit_product_cta]" value="<?php echo esc_attr( $s['credit_product_cta'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Shown instead of "Add to Cart" for products that sell store credits.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'While purchasing store credits', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( $n ); ?>[purchasing_credits_label]" value="<?php echo esc_attr( $s['purchasing_credits_label'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Label used when customers buy store credits of any amount.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( '"Coupons with Product" description', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( $n ); ?>[coupons_with_product_text]" value="<?php echo esc_attr( $s['coupons_with_product_text'] ); ?>" class="large-text">
                        <p class="description"><?php esc_html_e( 'Heading above coupon details on products that issue coupons.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'On Cart/Checkout pages', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( $n ); ?>[cart_checkout_label]" value="<?php echo esc_attr( $s['cart_checkout_label'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Title for the available coupons list. Use {coupons_count} for the number.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'My Account page', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( $n ); ?>[myaccount_label]" value="<?php echo esc_attr( $s['myaccount_label'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Title of available coupons list on My Account page.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Product page text', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( $n ); ?>[product_page_text]" value="<?php echo esc_attr( $s['product_page_text'] ); ?>" class="large-text">
                        <p class="description"><?php esc_html_e( 'Text displayed on product page when coupon is attached.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
            </table>

        <?php // ===================== SEND COUPON FORM ===================== ?>
        <?php elseif ( $sub === 'send_form' ) : ?>
            <h2><?php esc_html_e( 'Send Coupon Form', 'kdna-ecommerce' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Buyers can send purchased coupons to anyone — right while they\'re checking out.', 'kdna-ecommerce' ); ?></p>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Allow sending of coupons to others', 'kdna-ecommerce' ); ?></label></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[allow_sending_to_others]" value="yes" <?php checked( $s['allow_sending_to_others'], 'yes' ); ?>> <?php esc_html_e( 'Allow the buyer to send coupons to someone else.', 'kdna-ecommerce' ); ?></label></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Title', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( $n ); ?>[send_form_title]" value="<?php echo esc_attr( $s['send_form_title'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'The title for the coupon receiver details block.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Description', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( $n ); ?>[send_form_description]" value="<?php echo esc_attr( $s['send_form_description'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Additional text below the title.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Allow schedule sending of coupons?', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[allow_schedule_sending]" value="yes" <?php checked( $s['allow_schedule_sending'], 'yes' ); ?>>
                        <?php esc_html_e( 'Enable this to allow buyers to select date & time for delivering the coupon.', 'kdna-ecommerce' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Coupons will be sent via email on the selected date & time.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Combine emails', 'kdna-ecommerce' ); ?></label></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[combine_emails]" value="yes" <?php checked( $s['combine_emails'], 'yes' ); ?>> <?php esc_html_e( 'Send only one email instead of multiple emails when multiple coupons are generated for same recipient', 'kdna-ecommerce' ); ?></label></td>
                </tr>
            </table>

        <?php // ===================== EMAILS ===================== ?>
        <?php elseif ( $sub === 'emails' ) : ?>
            <h2><?php esc_html_e( 'Email notifications', 'kdna-ecommerce' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Email notifications sent from Smart Coupons are listed below.', 'kdna-ecommerce' ); ?></p>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Auto generated coupon email', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[email_auto_generated]" value="yes" <?php checked( $s['email_auto_generated'], 'yes' ); ?>>
                        <?php esc_html_e( 'Enabled', 'kdna-ecommerce' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Email auto generated coupon to recipients. One email per coupon.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Combined auto generated coupons email', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[email_combined]" value="yes" <?php checked( $s['email_combined'], 'yes' ); ?>>
                        <?php esc_html_e( 'Enabled', 'kdna-ecommerce' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Send only one email instead of multiple when multiple coupons are generated per recipient.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Acknowledgement email', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[email_acknowledgement]" value="yes" <?php checked( $s['email_acknowledgement'], 'yes' ); ?>>
                        <?php esc_html_e( 'Enabled', 'kdna-ecommerce' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Send an acknowledgement email to the purchaser. One email per customer.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Coupon Expiry Reminder', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[expiry_reminder_enabled]" value="yes" <?php checked( $s['expiry_reminder_enabled'], 'yes' ); ?>>
                        <?php esc_html_e( 'Enabled', 'kdna-ecommerce' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Send a reminder email for coupons approaching expiry.', 'kdna-ecommerce' ); ?></p>
                        <br>
                        <label><?php esc_html_e( 'Days before expiry:', 'kdna-ecommerce' ); ?>
                            <input type="number" name="<?php echo esc_attr( $n ); ?>[expiry_reminder_days_before]" value="<?php echo esc_attr( $s['expiry_reminder_days_before'] ); ?>" class="small-text" min="1">
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Store Credit email with image', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[email_store_credit_image]" value="yes" <?php checked( $s['email_store_credit_image'], 'yes' ); ?>>
                        <?php esc_html_e( 'Enabled', 'kdna-ecommerce' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Send Store Credit email including image uploaded by the purchaser.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Unused Coupon Reminder', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[unused_reminder_enabled]" value="yes" <?php checked( $s['unused_reminder_enabled'], 'yes' ); ?>>
                        <?php esc_html_e( 'Enabled', 'kdna-ecommerce' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Send a reminder email for unused coupons.', 'kdna-ecommerce' ); ?></p>
                        <br>
                        <label><?php esc_html_e( 'Days since issued:', 'kdna-ecommerce' ); ?>
                            <input type="number" name="<?php echo esc_attr( $n ); ?>[unused_reminder_days]" value="<?php echo esc_attr( $s['unused_reminder_days'] ); ?>" class="small-text" min="1">
                        </label>
                        <br><br>
                        <label><?php esc_html_e( 'Max reminders per coupon:', 'kdna-ecommerce' ); ?>
                            <input type="number" name="<?php echo esc_attr( $n ); ?>[unused_max_reminders]" value="<?php echo esc_attr( $s['unused_max_reminders'] ); ?>" class="small-text" min="1">
                        </label>
                    </td>
                </tr>
            </table>

        <?php // ===================== CASHBACK REWARDS ===================== ?>
        <?php elseif ( $sub === 'cashback' ) : ?>
            <h2><?php esc_html_e( 'Cashback Rewards', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Enable Cashback', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <label class="kdna-toggle">
                            <input type="checkbox" name="<?php echo esc_attr( $n ); ?>[cashback_enabled]" value="yes" <?php checked( $s['cashback_enabled'], 'yes' ); ?>>
                            <span class="kdna-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Automatically generate a store credit coupon as cashback when an order meets the criteria.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Cashback Amount', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="number" name="<?php echo esc_attr( $n ); ?>[cashback_amount]" value="<?php echo esc_attr( $s['cashback_amount'] ); ?>" class="small-text" min="0" step="any">
                        <select name="<?php echo esc_attr( $n ); ?>[cashback_type]">
                            <option value="fixed" <?php selected( $s['cashback_type'], 'fixed' ); ?>><?php esc_html_e( 'Fixed amount', 'kdna-ecommerce' ); ?></option>
                            <option value="percentage" <?php selected( $s['cashback_type'], 'percentage' ); ?>><?php esc_html_e( 'Percentage of order total', 'kdna-ecommerce' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Minimum Order Value', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="number" name="<?php echo esc_attr( $n ); ?>[cashback_min_order]" value="<?php echo esc_attr( $s['cashback_min_order'] ); ?>" class="small-text" min="0" step="any">
                        <p class="description"><?php esc_html_e( 'Minimum order total required to qualify for cashback. Leave empty for no minimum.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Template Coupon ID', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="number" name="<?php echo esc_attr( $n ); ?>[cashback_template_coupon]" value="<?php echo esc_attr( $s['cashback_template_coupon'] ); ?>" class="small-text" min="0">
                        <p class="description"><?php esc_html_e( 'Optional: ID of a coupon to use as template for cashback coupon properties (description, restrictions, etc).', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
            </table>

        <?php endif; ?>

            <?php submit_button(); ?>
        </form>

        <script>
        jQuery(function($) {
            // Init colour pickers.
            $('.kdna-color-picker').wpColorPicker();
            // Remove hidden preserves for fields that exist in the visible form.
            $('input.kdna-sc-preserve').each(function() {
                var name = $(this).attr('name');
                if ( $('input[name="' + name + '"]:not(.kdna-sc-preserve), select[name="' + name + '"], textarea[name="' + name + '"]').length ) {
                    $(this).remove();
                }
            });
            // Also remove array preserves if checkboxes exist.
            $('input.kdna-sc-preserve[name$="[]"]').each(function() {
                var base = $(this).attr('name');
                if ( $('input[name="' + base + '"]:not(.kdna-sc-preserve)').length ) {
                    $(this).remove();
                }
            });
        });
        </script>
        <?php
    }
}
