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
            __( 'E-Commerce Plugins', 'kdna-ecommerce' ),
            __( 'E-Commerce Plugins', 'kdna-ecommerce' ),
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
        $modules = [ 'points_rewards', 'reviews', 'related_products', 'sequential_orders' ];
        $output = [];
        foreach ( $modules as $module ) {
            $output[ $module ] = isset( $input[ $module ] ) ? 'yes' : 'no';
        }
        return $output;
    }

    public function sanitize_array( $input ) {
        return is_array( $input ) ? array_map( 'sanitize_text_field', $input ) : [];
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_kdna-ecommerce' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'kdna-admin', KDNA_ECOMMERCE_URL . 'admin/css/admin.css', [], KDNA_ECOMMERCE_VERSION );
        wp_enqueue_script( 'kdna-admin', KDNA_ECOMMERCE_URL . 'admin/js/admin.js', [ 'jquery' ], KDNA_ECOMMERCE_VERSION, true );
    }

    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        $modules = get_option( 'kdna_ecommerce_modules', [] );
        ?>
        <div class="wrap kdna-ecommerce-wrap">
            <h1><?php esc_html_e( 'E-Commerce Plugins', 'kdna-ecommerce' ); ?></h1>

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
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function render_points_tab( $modules ) {
        $settings = get_option( 'kdna_points_settings', [] );
        $defaults = [
            'earn_ratio'              => '1:1',
            'redeem_ratio'            => '100:1',
            'points_label_singular'   => 'Point',
            'points_label_plural'     => 'Points',
            'earn_account_signup'     => '0',
            'earn_review'             => '0',
            'max_discount'            => '',
            'max_discount_type'       => 'fixed',
            'product_message'         => 'Earn <strong>{points}</strong> {points_label} by purchasing this product.',
            'cart_message'            => 'Complete this order to earn <strong>{points}</strong> {points_label}.',
            'redeem_message'          => 'Use <strong>{points}</strong> {points_label} for a <strong>{discount}</strong> discount.',
            'expiry_period'           => '',
        ];
        $settings = wp_parse_args( $settings, $defaults );
        $active = ( $modules['points_rewards'] ?? 'no' ) === 'yes';
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'kdna_ecommerce_points' ); ?>

            <?php if ( ! $active ) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'This module is currently disabled. Enable it in the General tab.', 'kdna-ecommerce' ); ?></p></div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Points Earning', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="earn_ratio"><?php esc_html_e( 'Earn Points Ratio', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" id="earn_ratio" name="kdna_points_settings[earn_ratio]" value="<?php echo esc_attr( $settings['earn_ratio'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Points earned per currency spent. E.g., "1:1" = 1 point per $1 spent.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="earn_account_signup"><?php esc_html_e( 'Account Signup Points', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="number" id="earn_account_signup" name="kdna_points_settings[earn_account_signup]" value="<?php echo esc_attr( $settings['earn_account_signup'] ); ?>" min="0">
                    </td>
                </tr>
                <tr>
                    <th><label for="earn_review"><?php esc_html_e( 'Review Points', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="number" id="earn_review" name="kdna_points_settings[earn_review]" value="<?php echo esc_attr( $settings['earn_review'] ); ?>" min="0">
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Points Redemption', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="redeem_ratio"><?php esc_html_e( 'Redeem Points Ratio', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" id="redeem_ratio" name="kdna_points_settings[redeem_ratio]" value="<?php echo esc_attr( $settings['redeem_ratio'] ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Points needed per currency discount. E.g., "100:1" = 100 points for $1 off.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="max_discount"><?php esc_html_e( 'Maximum Discount', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" id="max_discount" name="kdna_points_settings[max_discount]" value="<?php echo esc_attr( $settings['max_discount'] ); ?>" class="small-text">
                        <select name="kdna_points_settings[max_discount_type]">
                            <option value="fixed" <?php selected( $settings['max_discount_type'], 'fixed' ); ?>><?php esc_html_e( 'Fixed amount', 'kdna-ecommerce' ); ?></option>
                            <option value="percentage" <?php selected( $settings['max_discount_type'], 'percentage' ); ?>><?php esc_html_e( 'Percentage of cart', 'kdna-ecommerce' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Leave blank for no limit.', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="expiry_period"><?php esc_html_e( 'Points Expiry', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <input type="text" id="expiry_period" name="kdna_points_settings[expiry_period]" value="<?php echo esc_attr( $settings['expiry_period'] ); ?>" class="small-text" placeholder="e.g. 12">
                        <span><?php esc_html_e( 'months (leave blank for no expiry)', 'kdna-ecommerce' ); ?></span>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Labels & Messages', 'kdna-ecommerce' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="points_label_singular"><?php esc_html_e( 'Points Label (Singular)', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="text" id="points_label_singular" name="kdna_points_settings[points_label_singular]" value="<?php echo esc_attr( $settings['points_label_singular'] ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="points_label_plural"><?php esc_html_e( 'Points Label (Plural)', 'kdna-ecommerce' ); ?></label></th>
                    <td><input type="text" id="points_label_plural" name="kdna_points_settings[points_label_plural]" value="<?php echo esc_attr( $settings['points_label_plural'] ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="product_message"><?php esc_html_e( 'Single Product Message', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <textarea id="product_message" name="kdna_points_settings[product_message]" rows="2" class="large-text"><?php echo esc_textarea( $settings['product_message'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Available placeholders: {points}, {points_label}', 'kdna-ecommerce' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="cart_message"><?php esc_html_e( 'Cart Earn Message', 'kdna-ecommerce' ); ?></label></th>
                    <td><textarea id="cart_message" name="kdna_points_settings[cart_message]" rows="2" class="large-text"><?php echo esc_textarea( $settings['cart_message'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="redeem_message"><?php esc_html_e( 'Cart Redeem Message', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <textarea id="redeem_message" name="kdna_points_settings[redeem_message]" rows="2" class="large-text"><?php echo esc_textarea( $settings['redeem_message'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Available placeholders: {points}, {points_label}, {discount}', 'kdna-ecommerce' ); ?></p>
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
            'enable_photos'     => 'yes',
            'enable_videos'     => 'yes',
            'enable_voting'     => 'yes',
            'enable_flagging'   => 'yes',
            'enable_qualifiers' => 'no',
            'qualifier_labels'  => '',
            'max_attachments'   => '5',
            'max_file_size'     => '5',
        ];
        $settings = wp_parse_args( $settings, $defaults );
        $active = ( $modules['reviews'] ?? 'no' ) === 'yes';
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'kdna_ecommerce_reviews' ); ?>

            <?php if ( ! $active ) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'This module is currently disabled. Enable it in the General tab.', 'kdna-ecommerce' ); ?></p></div>
            <?php endif; ?>

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
}
