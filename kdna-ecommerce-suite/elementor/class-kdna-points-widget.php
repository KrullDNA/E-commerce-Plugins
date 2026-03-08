<?php
defined( 'ABSPATH' ) || exit;

class KDNA_Points_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'kdna_points_rewards';
    }

    public function get_title() {
        return __( 'KDNA Points & Rewards', 'kdna-ecommerce' );
    }

    public function get_icon() {
        return 'eicon-star';
    }

    public function get_categories() {
        return [ 'woocommerce-elements' ];
    }

    public function get_keywords() {
        return [ 'points', 'rewards', 'woocommerce', 'kdna' ];
    }

    protected function register_controls() {

        // Content Section
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Content', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control( 'show_earn_message', [
            'label'        => __( 'Show Earn Message', 'kdna-ecommerce' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
        ]);

        $this->add_control( 'custom_message', [
            'label'       => __( 'Custom Message', 'kdna-ecommerce' ),
            'type'        => \Elementor\Controls_Manager::TEXTAREA,
            'placeholder' => __( 'Leave empty to use default from settings', 'kdna-ecommerce' ),
        ]);

        $this->end_controls_section();

        // Style Section - Message Box
        $this->start_controls_section( 'style_section', [
            'label' => __( 'Message Box', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'bg_color', [
            'label'     => __( 'Background Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f7f6f7',
            'selectors' => [ '{{WRAPPER}} .kdna-points-widget-box' => 'background-color: {{VALUE}};' ],
        ]);

        $this->add_control( 'text_color', [
            'label'     => __( 'Text Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#515151',
            'selectors' => [ '{{WRAPPER}} .kdna-points-widget-box' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'highlight_color', [
            'label'     => __( 'Highlight Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#2271b1',
            'selectors' => [ '{{WRAPPER}} .kdna-points-widget-box strong' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'border_color', [
            'label'     => __( 'Border Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#e0dadf',
            'selectors' => [ '{{WRAPPER}} .kdna-points-widget-box' => 'border-color: {{VALUE}};' ],
        ]);

        $this->add_responsive_control( 'padding', [
            'label'      => __( 'Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '12', 'right' => '16', 'bottom' => '12', 'left' => '16', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-points-widget-box' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_control( 'border_radius', [
            'label'      => __( 'Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px' ],
            'default'    => [ 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-points-widget-box' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'message_typography',
            'selector' => '{{WRAPPER}} .kdna-points-widget-box',
        ]);

        $this->end_controls_section();

        // Style Section - Icon
        $this->start_controls_section( 'icon_style_section', [
            'label' => __( 'Icon', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'show_icon', [
            'label'   => __( 'Show Icon', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'icon_color', [
            'label'     => __( 'Icon Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f0ad4e',
            'selectors' => [ '{{WRAPPER}} .kdna-points-icon' => 'color: {{VALUE}};' ],
            'condition' => [ 'show_icon' => 'yes' ],
        ]);

        $this->add_control( 'icon_size', [
            'label'      => __( 'Icon Size', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => [ 'px' => [ 'min' => 12, 'max' => 40 ] ],
            'default'    => [ 'size' => 18, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-points-icon' => 'font-size: {{SIZE}}{{UNIT}};' ],
            'condition'  => [ 'show_icon' => 'yes' ],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $module = kdna_ecommerce()->get_module( 'points_rewards' );

        if ( ! $module ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div class="kdna-points-widget-box" style="padding:12px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">Points & Rewards module is disabled.</div>';
            }
            return;
        }

        global $product;
        if ( ! $product && is_product() ) {
            $product = wc_get_product( get_the_ID() );
        }

        // Editor preview
        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            $points = $product ? $module->get_points_for_product( $product ) : 50;
            $label = $module->get_label( $points );
        } else {
            if ( ! $product || ! is_user_logged_in() ) {
                return;
            }
            $points = $module->get_points_for_product( $product );
            if ( $points <= 0 ) {
                return;
            }
            $label = $module->get_label( $points );
        }

        $message = ! empty( $settings['custom_message'] )
            ? $settings['custom_message']
            : $module->get_settings()['product_message'];

        $message = str_replace(
            [ '{points}', '{points_label}' ],
            [ $points, $label ],
            $message
        );

        echo '<div class="kdna-points-widget-box" style="border:1px solid;display:flex;align-items:center;gap:8px;">';
        if ( $settings['show_icon'] === 'yes' ) {
            echo '<span class="kdna-points-icon">&#9733;</span>';
        }
        echo '<span class="kdna-points-text">' . wp_kses_post( $message ) . '</span>';
        echo '</div>';
    }
}
