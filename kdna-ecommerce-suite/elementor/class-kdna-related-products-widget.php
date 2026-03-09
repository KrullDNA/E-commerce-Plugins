<?php
defined( 'ABSPATH' ) || exit;

class KDNA_Related_Products_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'kdna_related_products';
    }

    public function get_title() {
        return __( 'KDNA Related Products', 'kdna-ecommerce' );
    }

    public function get_icon() {
        return 'eicon-products';
    }

    public function get_categories() {
        return [ 'woocommerce-elements' ];
    }

    public function get_keywords() {
        return [ 'related', 'products', 'woocommerce', 'loop', 'grid', 'kdna' ];
    }

    protected function register_controls() {

        // Content Section
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Content', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control( 'section_title', [
            'label'   => __( 'Section Title', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => __( 'Related Products', 'kdna-ecommerce' ),
        ]);

        $this->add_control( 'products_count', [
            'label'   => __( 'Number of Products', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 4,
            'min'     => 1,
            'max'     => 20,
        ]);

        $this->add_control( 'loop_template_id', [
            'label'       => __( 'Elementor Loop Grid Template', 'kdna-ecommerce' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'description' => __( 'Enter the Elementor Loop Template ID to use for displaying each product. Leave empty for default WooCommerce product card layout.', 'kdna-ecommerce' ),
            'label_block' => true,
        ]);

        $this->add_control( 'hide_if_empty', [
            'label'        => __( 'Hide if No Related Products', 'kdna-ecommerce' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
        ]);

        $this->end_controls_section();

        // Layout Section
        $this->start_controls_section( 'layout_section', [
            'label' => __( 'Layout', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_responsive_control( 'columns', [
            'label'   => __( 'Columns', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '4',
            'options' => [
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
                '5' => '5',
                '6' => '6',
            ],
            'selectors' => [
                '{{WRAPPER}} .kdna-related-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
            ],
        ]);

        $this->add_responsive_control( 'column_gap', [
            'label'      => __( 'Column Gap', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
            'default'    => [ 'size' => 20, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-related-grid' => 'column-gap: {{SIZE}}{{UNIT}};' ],
        ]);

        $this->add_responsive_control( 'row_gap', [
            'label'      => __( 'Row Gap', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
            'default'    => [ 'size' => 20, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-related-grid' => 'row-gap: {{SIZE}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // Style - Section Title
        $this->start_controls_section( 'title_style', [
            'label' => __( 'Section Title', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'title_color', [
            'label'     => __( 'Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#333',
            'selectors' => [ '{{WRAPPER}} .kdna-related-title' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'title_typography',
            'selector' => '{{WRAPPER}} .kdna-related-title',
        ]);

        $this->add_responsive_control( 'title_margin', [
            'label'      => __( 'Margin', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '0', 'right' => '0', 'bottom' => '20', 'left' => '0', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-related-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_control( 'title_alignment', [
            'label'   => __( 'Alignment', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'left'   => [ 'title' => __( 'Left', 'kdna-ecommerce' ), 'icon' => 'eicon-text-align-left' ],
                'center' => [ 'title' => __( 'Center', 'kdna-ecommerce' ), 'icon' => 'eicon-text-align-center' ],
                'right'  => [ 'title' => __( 'Right', 'kdna-ecommerce' ), 'icon' => 'eicon-text-align-right' ],
            ],
            'default'   => 'left',
            'selectors' => [ '{{WRAPPER}} .kdna-related-title' => 'text-align: {{VALUE}};' ],
        ]);

        $this->end_controls_section();

        // Style - Product Card (for default layout)
        $this->start_controls_section( 'card_style', [
            'label' => __( 'Product Card (Default Layout)', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'card_bg', [
            'label'     => __( 'Card Background', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#fff',
            'selectors' => [ '{{WRAPPER}} .kdna-related-product-card' => 'background-color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Border::get_type(), [
            'name'     => 'card_border',
            'selector' => '{{WRAPPER}} .kdna-related-product-card',
        ]);

        $this->add_control( 'card_border_radius', [
            'label'      => __( 'Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px' ],
            'default'    => [ 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-related-product-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), [
            'name'     => 'card_shadow',
            'selector' => '{{WRAPPER}} .kdna-related-product-card',
        ]);

        $this->add_responsive_control( 'card_padding', [
            'label'      => __( 'Card Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '0', 'right' => '0', 'bottom' => '12', 'left' => '0', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-related-product-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_control( 'card_product_title_color', [
            'label'     => __( 'Product Title Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#333',
            'selectors' => [ '{{WRAPPER}} .kdna-related-product-card .kdna-product-title a' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'card_product_title_typography',
            'label'    => __( 'Product Title Typography', 'kdna-ecommerce' ),
            'selector' => '{{WRAPPER}} .kdna-related-product-card .kdna-product-title',
        ]);

        $this->add_control( 'card_price_color', [
            'label'     => __( 'Price Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#333',
            'selectors' => [ '{{WRAPPER}} .kdna-related-product-card .kdna-product-price' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'card_image_border_radius', [
            'label'      => __( 'Image Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px' ],
            'default'    => [ 'top' => '8', 'right' => '8', 'bottom' => '0', 'left' => '0', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-related-product-card img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();

        global $product;
        if ( ! $product && is_product() ) {
            $product = wc_get_product( get_the_ID() );
        }

        $product_id = $product ? $product->get_id() : 0;
        $related_ids = KDNA_Related_Products::get_related_product_ids( $product_id );
        $count = (int) $settings['products_count'];

        if ( ! empty( $related_ids ) ) {
            $related_ids = array_slice( $related_ids, 0, $count );
        }

        // Editor preview with no product context
        if ( $is_editor && empty( $related_ids ) ) {
            $related_ids = wc_get_products([
                'limit'  => $count,
                'return' => 'ids',
                'status' => 'publish',
            ]);
        }

        if ( empty( $related_ids ) ) {
            if ( $settings['hide_if_empty'] === 'yes' && ! $is_editor ) {
                return;
            }
            echo '<div class="kdna-related-products-widget"><p>' . esc_html__( 'No related products found.', 'kdna-ecommerce' ) . '</p></div>';
            return;
        }

        echo '<div class="kdna-related-products-widget">';

        // Section title
        if ( ! empty( $settings['section_title'] ) ) {
            echo '<h2 class="kdna-related-title">' . esc_html( $settings['section_title'] ) . '</h2>';
        }

        $template_id = ! empty( $settings['loop_template_id'] ) ? (int) $settings['loop_template_id'] : 0;

        echo '<div class="kdna-related-grid" style="display:grid;">';

        if ( $template_id && $this->is_loop_template_valid( $template_id ) ) {
            $this->render_with_loop_template( $related_ids, $template_id );
        } else {
            $this->render_default_cards( $related_ids );
        }

        echo '</div></div>';
    }

    private function is_loop_template_valid( $template_id ) {
        $post = get_post( $template_id );
        return $post && $post->post_status === 'publish' && $post->post_type === 'elementor_library';
    }

    private function render_with_loop_template( $product_ids, $template_id ) {
        // Explicitly enqueue the Elementor template's CSS so styles aren't stripped on the frontend.
        if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
            $css_file = new \Elementor\Core\Files\CSS\Post( $template_id );
            $css_file->enqueue();
        }

        foreach ( $product_ids as $product_id ) {
            $product_obj = wc_get_product( $product_id );
            if ( ! $product_obj ) {
                continue;
            }

            // Set up global post data for Elementor template rendering
            global $post;
            $original_post = $post;

            $post = get_post( $product_id );
            setup_postdata( $post );

            // Also set global $product for WooCommerce template tags
            $GLOBALS['product'] = $product_obj;

            echo '<div class="kdna-related-grid-item">';
            echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id );
            echo '</div>';

            // Restore
            $post = $original_post;
            if ( $original_post ) {
                setup_postdata( $original_post );
            } else {
                wp_reset_postdata();
            }
        }

        // Final reset
        wp_reset_postdata();
    }

    private function render_default_cards( $product_ids ) {
        // Use WooCommerce's native product content template for proper theme compatibility.
        // The output must be wrapped in .woocommerce ul.products so theme CSS selectors
        // (e.g. `.woocommerce ul.products li.product .button`) apply correctly.
        global $post, $product;
        $original_post = $post;
        $original_product = $product;

        echo '<style>'
            . '.kdna-related-grid .woocommerce ul.products { display: contents; }'
            . '.kdna-related-grid .product { list-style: none; }'
            . '</style>';

        // Wrap in .woocommerce > ul.products for full theme selector compatibility.
        echo '<div class="woocommerce"><ul class="products columns-' . count( $product_ids ) . '">';

        foreach ( $product_ids as $product_id ) {
            $product_obj = wc_get_product( $product_id );
            if ( ! $product_obj ) {
                continue;
            }

            $post = get_post( $product_id );
            setup_postdata( $post );
            $GLOBALS['product'] = $product_obj;

            wc_get_template_part( 'content', 'product' );
        }

        echo '</ul></div>';

        // Restore globals.
        $post = $original_post;
        $product = $original_product;
        if ( $original_post ) {
            setup_postdata( $original_post );
        } else {
            wp_reset_postdata();
        }
    }
}
