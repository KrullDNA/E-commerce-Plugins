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
    }

    public function sanitize_modules( $input ) {
        $modules = [ 'points_rewards', 'reviews', 'related_products', 'sequential_orders', 'australia_post', 'shipment_tracking' ];
        $output = [];
        foreach ( $modules as $module ) {
            $output[ $module ] = isset( $input[ $module ] ) ? 'yes' : 'no';
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
        wp_enqueue_style( 'kdna-admin', KDNA_ECOMMERCE_URL . 'admin/css/admin.css', [], KDNA_ECOMMERCE_VERSION );
        wp_enqueue_script( 'kdna-admin', KDNA_ECOMMERCE_URL . 'admin/js/admin.js', [ 'jquery' ], KDNA_ECOMMERCE_VERSION, true );
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
}
