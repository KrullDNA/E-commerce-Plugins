<?php
/**
 * KDNA Australia Post Shipping Module
 *
 * Registers the Australia Post shipping method with WooCommerce.
 */
defined( 'ABSPATH' ) || exit;

class KDNA_Australia_Post {

	public function __construct() {
		add_action( 'woocommerce_shipping_init', [ $this, 'load_shipping_method' ] );
		add_filter( 'woocommerce_shipping_methods', [ $this, 'register_shipping_method' ] );
	}

	public function load_shipping_method() {
		require_once __DIR__ . '/class-kdna-shipping-australia-post.php';
	}

	public function register_shipping_method( $methods ) {
		$methods['kdna_australia_post'] = 'KDNA_Shipping_Australia_Post';
		return $methods;
	}
}
