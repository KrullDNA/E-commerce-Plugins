<?php
defined( 'ABSPATH' ) || exit;

class KDNA_Smart_Coupons_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'kdna_smart_coupons';
    }

    public function get_title() {
        return __( 'KDNA Available Coupons', 'kdna-ecommerce' );
    }

    public function get_icon() {
        return 'eicon-price-table';
    }

    public function get_categories() {
        return [ 'woocommerce-elements' ];
    }

    public function get_keywords() {
        return [ 'coupon', 'coupons', 'discount', 'smart', 'woocommerce', 'kdna', 'gift', 'credit' ];
    }

    protected function register_controls() {

        // =====================================================================
        // Content Tab
        // =====================================================================

        $this->start_controls_section( 'content_section', [
            'label' => __( 'Content', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control( 'heading_text', [
            'label'   => __( 'Heading', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => __( 'Available Coupons', 'kdna-ecommerce' ),
            'label_block' => true,
        ]);

        $this->add_control( 'show_heading', [
            'label'   => __( 'Show Heading', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'max_coupons', [
            'label'   => __( 'Max Coupons', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 10,
            'min'     => 1,
            'max'     => 50,
        ]);

        $this->add_control( 'show_description', [
            'label'   => __( 'Show Description', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'show_expiry', [
            'label'   => __( 'Show Expiry Date', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'show_apply_button', [
            'label'   => __( 'Show Apply Button', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'coupon_design', [
            'label'   => __( 'Card Design', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'flat',
            'options' => [
                'flat'    => __( 'Flat (dashed border)', 'kdna-ecommerce' ),
                'ticket'  => __( 'Ticket', 'kdna-ecommerce' ),
                'minimal' => __( 'Minimal (left accent)', 'kdna-ecommerce' ),
                'bold'    => __( 'Bold (full colour)', 'kdna-ecommerce' ),
            ],
        ]);

        $this->add_control( 'empty_message', [
            'label'       => __( 'No Coupons Message', 'kdna-ecommerce' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => __( 'No coupons available right now.', 'kdna-ecommerce' ),
            'label_block' => true,
        ]);

        $this->add_control( 'hide_if_empty', [
            'label'   => __( 'Hide if No Coupons', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->end_controls_section();

        // =====================================================================
        // Layout Section
        // =====================================================================

        $this->start_controls_section( 'layout_section', [
            'label' => __( 'Layout', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_responsive_control( 'columns', [
            'label'   => __( 'Columns', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '2',
            'options' => [
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
            ],
            'selectors' => [
                '{{WRAPPER}} .kdna-sc-coupon-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
            ],
        ]);

        $this->add_responsive_control( 'column_gap', [
            'label'     => __( 'Column Gap', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
            'default'   => [ 'size' => 15, 'unit' => 'px' ],
            'selectors' => [ '{{WRAPPER}} .kdna-sc-coupon-grid' => 'column-gap: {{SIZE}}{{UNIT}};' ],
        ]);

        $this->add_responsive_control( 'row_gap', [
            'label'     => __( 'Row Gap', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
            'default'   => [ 'size' => 15, 'unit' => 'px' ],
            'selectors' => [ '{{WRAPPER}} .kdna-sc-coupon-grid' => 'row-gap: {{SIZE}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // =====================================================================
        // Style Tab - Heading
        // =====================================================================

        $this->start_controls_section( 'heading_style', [
            'label' => __( 'Heading', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'heading_color', [
            'label'     => __( 'Colour', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#333333',
            'selectors' => [ '{{WRAPPER}} .kdna-sc-heading' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'heading_typography',
            'selector' => '{{WRAPPER}} .kdna-sc-heading',
        ]);

        $this->add_responsive_control( 'heading_margin', [
            'label'      => __( 'Margin', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '0', 'right' => '0', 'bottom' => '15', 'left' => '0', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-sc-heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_control( 'heading_alignment', [
            'label'   => __( 'Alignment', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'left'   => [ 'title' => __( 'Left', 'kdna-ecommerce' ), 'icon' => 'eicon-text-align-left' ],
                'center' => [ 'title' => __( 'Center', 'kdna-ecommerce' ), 'icon' => 'eicon-text-align-center' ],
                'right'  => [ 'title' => __( 'Right', 'kdna-ecommerce' ), 'icon' => 'eicon-text-align-right' ],
            ],
            'default'   => 'left',
            'selectors' => [ '{{WRAPPER}} .kdna-sc-heading' => 'text-align: {{VALUE}};' ],
        ]);

        $this->end_controls_section();

        // =====================================================================
        // Style Tab - Coupon Card
        // =====================================================================

        $this->start_controls_section( 'card_style', [
            'label' => __( 'Coupon Card', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'card_primary_color', [
            'label'   => __( 'Primary Colour', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#39cccc',
        ]);

        $this->add_control( 'card_text_on_primary', [
            'label'   => __( 'Text on Primary', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#ffffff',
        ]);

        $this->add_control( 'card_bg_color', [
            'label'     => __( 'Card Background', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .kdna-sc-coupon-card' => 'background-color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Border::get_type(), [
            'name'     => 'card_border',
            'selector' => '{{WRAPPER}} .kdna-sc-coupon-card',
        ]);

        $this->add_control( 'card_border_radius', [
            'label'      => __( 'Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px' ],
            'default'    => [ 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-sc-coupon-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), [
            'name'     => 'card_shadow',
            'selector' => '{{WRAPPER}} .kdna-sc-coupon-card',
        ]);

        $this->add_responsive_control( 'card_padding', [
            'label'      => __( 'Card Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-sc-coupon-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // =====================================================================
        // Style Tab - Amount Section
        // =====================================================================

        $this->start_controls_section( 'amount_style', [
            'label' => __( 'Amount Section', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control( 'amount_width', [
            'label'     => __( 'Width', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 60, 'max' => 200 ] ],
            'default'   => [ 'size' => 90, 'unit' => 'px' ],
            'selectors' => [ '{{WRAPPER}} .kdna-sc-coupon-amount' => 'min-width: {{SIZE}}{{UNIT}};' ],
        ]);

        $this->add_responsive_control( 'amount_padding', [
            'label'      => __( 'Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-sc-coupon-amount' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'discount_typography',
            'label'    => __( 'Discount Typography', 'kdna-ecommerce' ),
            'selector' => '{{WRAPPER}} .kdna-sc-discount',
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'label_typography',
            'label'    => __( 'Label Typography', 'kdna-ecommerce' ),
            'selector' => '{{WRAPPER}} .kdna-sc-label',
        ]);

        $this->end_controls_section();

        // =====================================================================
        // Style Tab - Details Section
        // =====================================================================

        $this->start_controls_section( 'details_style', [
            'label' => __( 'Details Section', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'code_color', [
            'label'     => __( 'Code Colour', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#333333',
            'selectors' => [ '{{WRAPPER}} .kdna-sc-code' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'code_typography',
            'label'    => __( 'Code Typography', 'kdna-ecommerce' ),
            'selector' => '{{WRAPPER}} .kdna-sc-code',
        ]);

        $this->add_control( 'desc_color', [
            'label'     => __( 'Description Colour', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#666666',
            'selectors' => [ '{{WRAPPER}} .kdna-sc-desc' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'desc_typography',
            'label'    => __( 'Description Typography', 'kdna-ecommerce' ),
            'selector' => '{{WRAPPER}} .kdna-sc-desc',
        ]);

        $this->add_control( 'expiry_color', [
            'label'     => __( 'Expiry Colour', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#999999',
            'selectors' => [ '{{WRAPPER}} .kdna-sc-expiry' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'expiry_typography',
            'label'    => __( 'Expiry Typography', 'kdna-ecommerce' ),
            'selector' => '{{WRAPPER}} .kdna-sc-expiry',
        ]);

        $this->add_responsive_control( 'details_padding', [
            'label'      => __( 'Details Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-sc-coupon-details' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // =====================================================================
        // Style Tab - Apply Button
        // =====================================================================

        $this->start_controls_section( 'button_style', [
            'label' => __( 'Apply Button', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'button_typography',
            'selector' => '{{WRAPPER}} .kdna-sc-apply-btn',
        ]);

        $this->add_control( 'button_border_radius', [
            'label'      => __( 'Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px' ],
            'default'    => [ 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-sc-apply-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_responsive_control( 'button_padding', [
            'label'      => __( 'Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '8', 'right' => '16', 'bottom' => '8', 'left' => '16', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-sc-apply-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // =====================================================================
        // Style Tab - Applied Badge
        // =====================================================================

        $this->start_controls_section( 'badge_style', [
            'label' => __( 'Applied Badge', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'badge_bg_color', [
            'label'     => __( 'Background', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#4caf50',
            'selectors' => [ '{{WRAPPER}} .kdna-sc-applied-badge' => 'background-color: {{VALUE}};' ],
        ]);

        $this->add_control( 'badge_text_color', [
            'label'     => __( 'Text Colour', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .kdna-sc-applied-badge' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'badge_border_radius', [
            'label'      => __( 'Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px' ],
            'default'    => [ 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-sc-applied-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings  = $this->get_settings_for_display();
        $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();

        // Load the module class if not already loaded.
        if ( ! class_exists( 'KDNA_Smart_Coupons' ) ) {
            require_once KDNA_ECOMMERCE_PATH . 'modules/smart-coupons/class-kdna-smart-coupons.php';
        }

        $sc      = kdna_ecommerce()->get_module( 'smart_coupons' );
        $coupons = [];

        if ( $sc ) {
            $coupons = $sc->get_available_coupons();
        }

        // Limit coupons.
        $max = (int) $settings['max_coupons'];
        if ( $max > 0 && count( $coupons ) > $max ) {
            $coupons = array_slice( $coupons, 0, $max );
        }

        // Handle empty state.
        if ( empty( $coupons ) ) {
            if ( $is_editor ) {
                // Show placeholder in editor.
                echo '<div class="kdna-sc-available-coupons">';
                if ( $settings['show_heading'] === 'yes' && ! empty( $settings['heading_text'] ) ) {
                    echo '<h3 class="kdna-sc-heading">' . esc_html( $settings['heading_text'] ) . '</h3>';
                }
                echo '<p class="kdna-sc-empty">' . esc_html( $settings['empty_message'] ) . '</p>';
                echo '</div>';
                return;
            }

            if ( $settings['hide_if_empty'] === 'yes' ) {
                return;
            }

            echo '<div class="kdna-sc-available-coupons">';
            if ( $settings['show_heading'] === 'yes' && ! empty( $settings['heading_text'] ) ) {
                echo '<h3 class="kdna-sc-heading">' . esc_html( $settings['heading_text'] ) . '</h3>';
            }
            echo '<p class="kdna-sc-empty">' . esc_html( $settings['empty_message'] ) . '</p>';
            echo '</div>';
            return;
        }

        $design  = $settings['coupon_design'];
        $primary = $settings['card_primary_color'] ?? '#39cccc';
        $text_on = $settings['card_text_on_primary'] ?? '#ffffff';

        // Enqueue frontend assets.
        wp_enqueue_style( 'kdna-smart-coupons', KDNA_ECOMMERCE_URL . 'modules/smart-coupons/assets/smart-coupons.css', [], KDNA_ECOMMERCE_VERSION );
        wp_enqueue_script( 'kdna-smart-coupons', KDNA_ECOMMERCE_URL . 'modules/smart-coupons/assets/smart-coupons.js', [ 'jquery' ], KDNA_ECOMMERCE_VERSION, true );
        wp_localize_script( 'kdna-smart-coupons', 'kdna_sc', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'applying' => __( 'Applying...', 'kdna-ecommerce' ),
            'applied'  => __( 'Applied', 'kdna-ecommerce' ),
            'error'    => __( 'Error', 'kdna-ecommerce' ),
        ] );

        echo '<div class="kdna-sc-available-coupons">';

        if ( $settings['show_heading'] === 'yes' && ! empty( $settings['heading_text'] ) ) {
            echo '<h3 class="kdna-sc-heading">' . esc_html( $settings['heading_text'] ) . '</h3>';
        }

        echo '<div class="kdna-sc-coupon-grid kdna-sc-design-' . esc_attr( $design ) . '">';

        foreach ( $coupons as $coupon ) {
            $this->render_coupon_card( $coupon, $settings, $primary, $text_on );
        }

        echo '</div></div>';
    }

    private function render_coupon_card( $coupon, $settings, $primary, $text_on ) {
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
                $label   = __( 'CREDIT', 'kdna-ecommerce' );
                break;
            default:
                $display = wc_price( $amount );
                $label   = __( 'OFF', 'kdna-ecommerce' );
                break;
        }

        $nonce      = wp_create_nonce( 'kdna_sc_apply_' . $code );
        $is_applied = WC()->cart && WC()->cart->has_discount( $code );
        $show_apply = $settings['show_apply_button'] === 'yes';
        ?>
        <div class="kdna-sc-coupon-card <?php echo $is_applied ? 'kdna-sc-applied' : ''; ?>"
             style="--kdna-sc-primary:<?php echo esc_attr( $primary ); ?>;--kdna-sc-text:<?php echo esc_attr( $text_on ); ?>;"
             data-code="<?php echo esc_attr( $code ); ?>">
            <div class="kdna-sc-coupon-amount">
                <span class="kdna-sc-discount"><?php echo $display; ?></span>
                <span class="kdna-sc-label"><?php echo esc_html( $label ); ?></span>
            </div>
            <div class="kdna-sc-coupon-details">
                <span class="kdna-sc-code"><?php echo esc_html( strtoupper( $code ) ); ?></span>
                <?php if ( $settings['show_description'] === 'yes' && $description ) : ?>
                    <span class="kdna-sc-desc"><?php echo esc_html( $description ); ?></span>
                <?php endif; ?>
                <?php if ( $settings['show_expiry'] === 'yes' && $expiry ) : ?>
                    <span class="kdna-sc-expiry"><?php
                        printf( esc_html__( 'Expires: %s', 'kdna-ecommerce' ), esc_html( $expiry->date_i18n( wc_date_format() ) ) );
                    ?></span>
                <?php endif; ?>
            </div>
            <?php if ( $show_apply && ! $is_applied ) : ?>
                <button type="button" class="kdna-sc-apply-btn"
                        data-coupon="<?php echo esc_attr( $code ); ?>"
                        data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    <?php esc_html_e( 'Apply', 'kdna-ecommerce' ); ?>
                </button>
            <?php elseif ( $is_applied ) : ?>
                <span class="kdna-sc-applied-badge"><?php esc_html_e( 'Applied', 'kdna-ecommerce' ); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }
}
