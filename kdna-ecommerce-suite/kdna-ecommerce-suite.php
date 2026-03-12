<?php
/**
 * Plugin Name: E-commerce Suite
 * Plugin URI: https://kdnastaging2.com
 * Description: All-in-one WooCommerce enhancement suite: Points & Rewards, Product Reviews, Related Products, Sequential Order Numbers, Australia Post Shipping, and Shipment Tracking.
 * Version: 1.0.0
 * Author: Krull D+A
 * Author URI: https://kdnastaging2.com
 * Text Domain: kdna-ecommerce
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * WC requires at least: 7.5
 * WC tested up to: 10.6
 * License: GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

define( 'KDNA_ECOMMERCE_VERSION', '1.0.0' );
define( 'KDNA_ECOMMERCE_FILE', __FILE__ );
define( 'KDNA_ECOMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'KDNA_ECOMMERCE_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader (required for DOMPDF and other dependencies).
if ( file_exists( KDNA_ECOMMERCE_PATH . 'vendor/autoload.php' ) ) {
    require_once KDNA_ECOMMERCE_PATH . 'vendor/autoload.php';
}

// HPOS compatibility
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});

class KDNA_Ecommerce_Suite {

    private static $instance = null;
    private $modules = [];

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ], 10 );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    }

    public function activate() {
        if ( ! get_option( 'kdna_ecommerce_modules' ) ) {
            update_option( 'kdna_ecommerce_modules', [
                'points_rewards'     => 'no',
                'reviews'            => 'no',
                'related_products'   => 'no',
                'sequential_orders'  => 'no',
                'australia_post'     => 'no',
                'shipment_tracking'  => 'no',
                'tax_invoice'        => 'no',
            ]);
        }
        flush_rewrite_rules();
    }

    public function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        require_once KDNA_ECOMMERCE_PATH . 'includes/class-kdna-admin.php';
        new KDNA_Admin();

        $this->load_modules();
    }

    public function is_module_active( $module ) {
        $modules = get_option( 'kdna_ecommerce_modules', [] );
        return isset( $modules[ $module ] ) && $modules[ $module ] === 'yes';
    }

    private function load_modules() {
        if ( $this->is_module_active( 'points_rewards' ) ) {
            require_once KDNA_ECOMMERCE_PATH . 'modules/points-rewards/class-kdna-points-rewards.php';
            $this->modules['points_rewards'] = new KDNA_Points_Rewards();

            if ( is_admin() ) {
                require_once KDNA_ECOMMERCE_PATH . 'includes/class-kdna-points-admin.php';
                new KDNA_Points_Admin();
            }
        }

        if ( $this->is_module_active( 'reviews' ) ) {
            require_once KDNA_ECOMMERCE_PATH . 'modules/reviews/class-kdna-reviews.php';
            $this->modules['reviews'] = new KDNA_Reviews();
        }

        if ( $this->is_module_active( 'related_products' ) ) {
            require_once KDNA_ECOMMERCE_PATH . 'modules/related-products/class-kdna-related-products.php';
            $this->modules['related_products'] = new KDNA_Related_Products();
        }

        if ( $this->is_module_active( 'sequential_orders' ) ) {
            require_once KDNA_ECOMMERCE_PATH . 'modules/sequential-orders/class-kdna-sequential-orders.php';
            $this->modules['sequential_orders'] = new KDNA_Sequential_Orders();
        }

        if ( $this->is_module_active( 'australia_post' ) ) {
            require_once KDNA_ECOMMERCE_PATH . 'modules/australia-post/class-kdna-australia-post.php';
            $this->modules['australia_post'] = new KDNA_Australia_Post();
        }

        if ( $this->is_module_active( 'shipment_tracking' ) ) {
            require_once KDNA_ECOMMERCE_PATH . 'modules/shipment-tracking/class-kdna-shipment-tracking.php';
            $this->modules['shipment_tracking'] = new KDNA_Shipment_Tracking();
        }

        require_once KDNA_ECOMMERCE_PATH . 'modules/tax-invoice/class-kdna-tax-invoice.php';
        if ( $this->is_module_active( 'tax_invoice' ) ) {
            $this->modules['tax_invoice'] = new KDNA_Tax_Invoice();
        }

        // Always register the admin test PDF handler so the preview works
        // even before the module is enabled.
        KDNA_Tax_Invoice::register_test_pdf_handler();

        // Load Elementor widgets if Elementor is active
        add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widgets' ] );
    }

    public function register_elementor_widgets( $widgets_manager ) {
        if ( $this->is_module_active( 'points_rewards' ) ) {
            require_once KDNA_ECOMMERCE_PATH . 'elementor/class-kdna-points-widget.php';
            $widgets_manager->register( new KDNA_Points_Widget() );
        }

        if ( $this->is_module_active( 'reviews' ) ) {
            require_once KDNA_ECOMMERCE_PATH . 'elementor/class-kdna-reviews-widget.php';
            $widgets_manager->register( new KDNA_Reviews_Widget() );

            require_once KDNA_ECOMMERCE_PATH . 'elementor/class-kdna-rating-summary-widget.php';
            $widgets_manager->register( new KDNA_Rating_Summary_Widget() );

            require_once KDNA_ECOMMERCE_PATH . 'elementor/class-kdna-review-form-widget.php';
            $widgets_manager->register( new KDNA_Review_Form_Widget() );
        }

        if ( $this->is_module_active( 'related_products' ) ) {
            require_once KDNA_ECOMMERCE_PATH . 'elementor/class-kdna-related-products-widget.php';
            $widgets_manager->register( new KDNA_Related_Products_Widget() );
        }
    }

    public function get_module( $name ) {
        return $this->modules[ $name ] ?? null;
    }
}

function kdna_ecommerce() {
    return KDNA_Ecommerce_Suite::instance();
}

kdna_ecommerce();
