<?php
defined( 'ABSPATH' ) || exit;

class KDNA_Related_Products {

    private $settings;

    public function __construct() {
        $this->settings = wp_parse_args( get_option( 'kdna_related_settings', [] ), [
            'section_title'  => 'Related Products',
            'products_count' => '4',
        ]);

        // Add metabox to product data panel (Linked Products tab)
        add_action( 'woocommerce_product_options_related', [ $this, 'add_related_products_field' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_related_products' ] );

        // Override WooCommerce related products
        add_filter( 'woocommerce_related_products', [ $this, 'get_custom_related_products' ], 10, 3 );
    }

    public function add_related_products_field() {
        global $post;
        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            return;
        }

        $related_ids = $product->get_meta( '_kdna_related_product_ids' );
        if ( ! is_array( $related_ids ) ) {
            $related_ids = [];
        }
        ?>
        <div class="options_group">
            <p class="form-field">
                <label for="kdna_related_products"><?php esc_html_e( 'KDNA Related Products', 'kdna-ecommerce' ); ?></label>
                <select class="wc-product-search" multiple="multiple" style="width: 50%;" id="kdna_related_products" name="kdna_related_product_ids[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'kdna-ecommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-exclude="<?php echo intval( $post->ID ); ?>">
                    <?php
                    foreach ( $related_ids as $product_id ) {
                        $related_product = wc_get_product( $product_id );
                        if ( $related_product ) {
                            echo '<option value="' . esc_attr( $product_id ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $related_product->get_formatted_name() ) ) . '</option>';
                        }
                    }
                    ?>
                </select>
                <?php echo wc_help_tip( __( 'Select specific related products to display for this product. Uses the Elementor widget or shortcode [kdna_related_products].', 'kdna-ecommerce' ) ); ?>
            </p>
        </div>
        <?php
    }

    public function save_related_products( $post_id ) {
        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return;
        }

        $related_ids = isset( $_POST['kdna_related_product_ids'] ) ? array_map( 'absint', (array) $_POST['kdna_related_product_ids'] ) : [];
        $product->update_meta_data( '_kdna_related_product_ids', $related_ids );
        $product->save();
    }

    public function get_custom_related_products( $related_products, $product_id, $args ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return $related_products;
        }

        $custom_ids = $product->get_meta( '_kdna_related_product_ids' );
        if ( ! empty( $custom_ids ) && is_array( $custom_ids ) ) {
            return array_slice( $custom_ids, 0, (int) $this->settings['products_count'] );
        }

        return $related_products;
    }

    public static function get_related_product_ids( $product_id = null ) {
        if ( ! $product_id ) {
            global $product;
            $product_id = $product ? $product->get_id() : get_the_ID();
        }

        $wc_product = wc_get_product( $product_id );
        if ( ! $wc_product ) {
            return [];
        }

        $ids = $wc_product->get_meta( '_kdna_related_product_ids' );
        return is_array( $ids ) ? $ids : [];
    }
}
