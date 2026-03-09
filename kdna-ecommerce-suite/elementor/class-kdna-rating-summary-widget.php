<?php
defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget that displays the product rating summary:
 * star icons, average rating number, and total review count.
 * Intended for the top of the product page.
 */
class KDNA_Rating_Summary_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'kdna_rating_summary';
    }

    public function get_title() {
        return __( 'KDNA Rating Summary', 'kdna-ecommerce' );
    }

    public function get_icon() {
        return 'eicon-rating';
    }

    public function get_categories() {
        return [ 'woocommerce-elements' ];
    }

    public function get_keywords() {
        return [ 'rating', 'stars', 'summary', 'reviews', 'average', 'kdna' ];
    }

    protected function register_controls() {

        // Content
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Content', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control( 'layout', [
            'label'   => __( 'Layout', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'inline',
            'options' => [
                'inline'  => __( 'Inline (horizontal)', 'kdna-ecommerce' ),
                'stacked' => __( 'Stacked (vertical)', 'kdna-ecommerce' ),
            ],
        ]);

        $this->add_control( 'show_average_number', [
            'label'   => __( 'Show Average Number', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'show_review_count', [
            'label'   => __( 'Show Review Count', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'review_count_text', [
            'label'     => __( 'Review Count Text', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::TEXT,
            'default'   => '{count} reviews',
            'description' => __( 'Use {count} as placeholder for the number.', 'kdna-ecommerce' ),
            'condition' => [ 'show_review_count' => 'yes' ],
        ]);

        $this->add_control( 'link_to_reviews', [
            'label'        => __( 'Link to Reviews Tab', 'kdna-ecommerce' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'description'  => __( 'Makes the widget clickable to scroll to the reviews section.', 'kdna-ecommerce' ),
        ]);

        $this->end_controls_section();

        // Style - Container
        $this->start_controls_section( 'container_style', [
            'label' => __( 'Container', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'container_bg', [
            'label'     => __( 'Background Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .kdna-rating-summary' => 'background-color: {{VALUE}};' ],
        ]);

        $this->add_responsive_control( 'container_padding', [
            'label'      => __( 'Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-rating-summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_control( 'container_border_radius', [
            'label'      => __( 'Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-rating-summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_control( 'alignment', [
            'label'   => __( 'Alignment', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'flex-start' => [ 'title' => __( 'Left', 'kdna-ecommerce' ), 'icon' => 'eicon-text-align-left' ],
                'center'     => [ 'title' => __( 'Center', 'kdna-ecommerce' ), 'icon' => 'eicon-text-align-center' ],
                'flex-end'   => [ 'title' => __( 'Right', 'kdna-ecommerce' ), 'icon' => 'eicon-text-align-right' ],
            ],
            'default'   => 'flex-start',
            'selectors' => [ '{{WRAPPER}} .kdna-rating-summary' => 'justify-content: {{VALUE}};' ],
        ]);

        $this->end_controls_section();

        // Style - Stars
        $this->start_controls_section( 'stars_style', [
            'label' => __( 'Stars', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'star_color', [
            'label'     => __( 'Star Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f0ad4e',
            'selectors' => [ '{{WRAPPER}} .kdna-stars-filled' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'star_empty_color', [
            'label'     => __( 'Empty Star Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ddd',
            'selectors' => [ '{{WRAPPER}} .kdna-stars-empty' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'star_size', [
            'label'     => __( 'Star Size', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 12, 'max' => 48 ] ],
            'default'   => [ 'size' => 20, 'unit' => 'px' ],
            'selectors' => [ '{{WRAPPER}} .kdna-rating-stars' => 'font-size: {{SIZE}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // Style - Average Number
        $this->start_controls_section( 'average_style', [
            'label'     => __( 'Average Rating', 'kdna-ecommerce' ),
            'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_average_number' => 'yes' ],
        ]);

        $this->add_control( 'average_color', [
            'label'     => __( 'Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#333',
            'selectors' => [ '{{WRAPPER}} .kdna-average-number' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'average_typography',
            'selector' => '{{WRAPPER}} .kdna-average-number',
        ]);

        $this->end_controls_section();

        // Style - Review Count
        $this->start_controls_section( 'count_style', [
            'label'     => __( 'Review Count', 'kdna-ecommerce' ),
            'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_review_count' => 'yes' ],
        ]);

        $this->add_control( 'count_color', [
            'label'     => __( 'Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#666',
            'selectors' => [ '{{WRAPPER}} .kdna-review-count' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'count_typography',
            'selector' => '{{WRAPPER}} .kdna-review-count',
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

        if ( $is_editor && ! $product ) {
            $avg = 4.5;
            $count = 12;
        } elseif ( $product ) {
            $avg = (float) $product->get_average_rating();
            $count = (int) $product->get_review_count();
        } else {
            return;
        }

        $rounded = (int) round( $avg );
        $stars_filled = str_repeat( '&#9733;', $rounded );
        $stars_empty = str_repeat( '&#9734;', 5 - $rounded );

        $is_inline = ( $settings['layout'] ?? 'inline' ) === 'inline';
        $direction = $is_inline ? 'row' : 'column';
        $gap = $is_inline ? '12px' : '4px';

        $link_open = '';
        $link_close = '';
        if ( $settings['link_to_reviews'] === 'yes' && $product ) {
            $link_open = '<a href="' . esc_url( $product->get_permalink() ) . '#reviews" style="text-decoration:none;color:inherit;">';
            $link_close = '</a>';
        }

        echo $link_open;
        echo '<div class="kdna-rating-summary" style="display:inline-flex;align-items:center;flex-direction:' . $direction . ';gap:' . $gap . ';">';

        if ( $settings['show_average_number'] === 'yes' ) {
            echo '<span class="kdna-average-number" style="font-weight:700;line-height:1;">' . number_format( $avg, 1 ) . '</span>';
        }

        echo '<span class="kdna-rating-stars" style="line-height:1;white-space:nowrap;">';
        echo '<span class="kdna-stars-filled">' . $stars_filled . '</span>';
        echo '<span class="kdna-stars-empty">' . $stars_empty . '</span>';
        echo '</span>';

        if ( $settings['show_review_count'] === 'yes' ) {
            $count_text = str_replace( '{count}', $count, $settings['review_count_text'] );
            echo '<span class="kdna-review-count">' . esc_html( $count_text ) . '</span>';
        }

        echo '</div>';
        echo $link_close;
    }
}
