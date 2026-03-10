<?php
defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget that renders the WooCommerce review submission form.
 * Intended for the bottom of the product page, below the reviews list.
 */
class KDNA_Review_Form_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'kdna_review_form';
    }

    public function get_title() {
        return __( 'KDNA Review Form', 'kdna-ecommerce' );
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return [ 'woocommerce-elements' ];
    }

    public function get_keywords() {
        return [ 'review', 'form', 'submit', 'comment', 'woocommerce', 'kdna' ];
    }

    protected function register_controls() {

        // Content
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Content', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control( 'form_title', [
            'label'   => __( 'Form Title', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => __( 'Add a Review', 'kdna-ecommerce' ),
        ]);

        $this->add_control( 'form_subtitle', [
            'label'       => __( 'Subheading', 'kdna-ecommerce' ),
            'type'        => \Elementor\Controls_Manager::TEXTAREA,
            'default'     => '',
            'placeholder' => __( 'Share your experience with this product...', 'kdna-ecommerce' ),
            'rows'        => 2,
        ]);

        $this->add_control( 'show_rating', [
            'label'   => __( 'Show Star Rating', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'show_title_field', [
            'label'   => __( 'Show Review Title Field', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'show_photos_field', [
            'label'       => __( 'Show Photo Upload', 'kdna-ecommerce' ),
            'type'        => \Elementor\Controls_Manager::SWITCHER,
            'default'     => 'yes',
            'description' => __( 'Requires photos to be enabled in Reviews settings.', 'kdna-ecommerce' ),
        ]);

        $this->add_control( 'show_video_field', [
            'label'       => __( 'Show Video URL Field', 'kdna-ecommerce' ),
            'type'        => \Elementor\Controls_Manager::SWITCHER,
            'default'     => 'yes',
            'description' => __( 'Requires videos to be enabled in Reviews settings.', 'kdna-ecommerce' ),
        ]);

        $this->end_controls_section();

        // Style - Container
        $this->start_controls_section( 'form_style', [
            'label' => __( 'Form Container', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'form_bg', [
            'label'     => __( 'Background Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .kdna-review-form-container' => 'background-color: {{VALUE}};' ],
        ]);

        $this->add_responsive_control( 'form_padding', [
            'label'      => __( 'Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-review-form-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Border::get_type(), [
            'name'     => 'form_border',
            'selector' => '{{WRAPPER}} .kdna-review-form-container',
        ]);

        $this->add_control( 'form_border_radius', [
            'label'      => __( 'Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-review-form-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // Style - Title
        $this->start_controls_section( 'title_style', [
            'label' => __( 'Form Title', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'title_color', [
            'label'     => __( 'Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#333',
            'selectors' => [ '{{WRAPPER}} .kdna-review-form-container .kdna-review-form-title' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'title_typography',
            'selector' => '{{WRAPPER}} .kdna-review-form-container .kdna-review-form-title',
        ]);

        $this->add_responsive_control( 'title_margin', [
            'label'      => __( 'Margin', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '0', 'right' => '0', 'bottom' => '16', 'left' => '0', 'unit' => 'px', 'isLinked' => false ],
            'selectors'  => [ '{{WRAPPER}} .kdna-review-form-container .kdna-review-form-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;' ],
        ]);

        $this->end_controls_section();

        // Style - Subheading
        $this->start_controls_section( 'subtitle_style', [
            'label'     => __( 'Subheading', 'kdna-ecommerce' ),
            'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
            'condition' => [ 'form_subtitle!' => '' ],
        ]);

        $this->add_control( 'subtitle_color', [
            'label'     => __( 'Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#666',
            'selectors' => [ '{{WRAPPER}} .kdna-review-form-container .kdna-review-form-subtitle' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'subtitle_typography',
            'selector' => '{{WRAPPER}} .kdna-review-form-container .kdna-review-form-subtitle',
        ]);

        $this->add_responsive_control( 'subtitle_margin', [
            'label'      => __( 'Margin', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '0', 'right' => '0', 'bottom' => '20', 'left' => '0', 'unit' => 'px', 'isLinked' => false ],
            'selectors'  => [ '{{WRAPPER}} .kdna-review-form-container .kdna-review-form-subtitle' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // Style - Field Labels
        $this->start_controls_section( 'label_style', [
            'label' => __( 'Field Labels', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'label_color', [
            'label'     => __( 'Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#333',
            'selectors' => [ '{{WRAPPER}} .kdna-review-form-container label' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'label_typography',
            'selector' => '{{WRAPPER}} .kdna-review-form-container label',
        ]);

        $this->add_responsive_control( 'label_spacing', [
            'label'     => __( 'Bottom Spacing', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
            'default'   => [ 'size' => 6, 'unit' => 'px' ],
            'selectors' => [ '{{WRAPPER}} .kdna-review-form-container label' => 'margin-bottom: {{SIZE}}{{UNIT}}; display: block;' ],
        ]);

        $this->end_controls_section();

        // Style - Form Fields (inputs, textareas)
        $this->start_controls_section( 'field_style', [
            'label' => __( 'Form Fields', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'field_bg', [
            'label'     => __( 'Background Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .kdna-review-form-container input[type="text"]' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .kdna-review-form-container input[type="url"]' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .kdna-review-form-container input[type="email"]' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .kdna-review-form-container textarea' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control( 'field_text_color', [
            'label'     => __( 'Text Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .kdna-review-form-container input[type="text"]' => 'color: {{VALUE}};',
                '{{WRAPPER}} .kdna-review-form-container input[type="url"]' => 'color: {{VALUE}};',
                '{{WRAPPER}} .kdna-review-form-container input[type="email"]' => 'color: {{VALUE}};',
                '{{WRAPPER}} .kdna-review-form-container textarea' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control( 'field_border_color', [
            'label'     => __( 'Border Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ddd',
            'selectors' => [
                '{{WRAPPER}} .kdna-review-form-container input[type="text"]' => 'border-color: {{VALUE}};',
                '{{WRAPPER}} .kdna-review-form-container input[type="url"]' => 'border-color: {{VALUE}};',
                '{{WRAPPER}} .kdna-review-form-container input[type="email"]' => 'border-color: {{VALUE}};',
                '{{WRAPPER}} .kdna-review-form-container textarea' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_control( 'field_border_width', [
            'label'     => __( 'Border Width', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 0, 'max' => 5 ] ],
            'default'   => [ 'size' => 1, 'unit' => 'px' ],
            'selectors' => [
                '{{WRAPPER}} .kdna-review-form-container input[type="text"]' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
                '{{WRAPPER}} .kdna-review-form-container input[type="url"]' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
                '{{WRAPPER}} .kdna-review-form-container input[type="email"]' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
                '{{WRAPPER}} .kdna-review-form-container textarea' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
            ],
        ]);

        $this->add_control( 'field_border_radius', [
            'label'      => __( 'Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px' ],
            'default'    => [ 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px' ],
            'selectors'  => [
                '{{WRAPPER}} .kdna-review-form-container input[type="text"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .kdna-review-form-container input[type="url"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .kdna-review-form-container input[type="email"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .kdna-review-form-container textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control( 'field_padding', [
            'label'      => __( 'Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '10', 'right' => '12', 'bottom' => '10', 'left' => '12', 'unit' => 'px' ],
            'selectors'  => [
                '{{WRAPPER}} .kdna-review-form-container input[type="text"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .kdna-review-form-container input[type="url"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .kdna-review-form-container input[type="email"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .kdna-review-form-container textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'field_typography',
            'selector' => '{{WRAPPER}} .kdna-review-form-container input[type="text"], {{WRAPPER}} .kdna-review-form-container input[type="url"], {{WRAPPER}} .kdna-review-form-container input[type="email"], {{WRAPPER}} .kdna-review-form-container textarea',
        ]);

        $this->add_responsive_control( 'field_spacing', [
            'label'     => __( 'Field Bottom Spacing', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
            'default'   => [ 'size' => 16, 'unit' => 'px' ],
            'selectors' => [
                '{{WRAPPER}} .kdna-review-form-container .kdna-form-field' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .kdna-review-form-container .comment-form-comment' => 'margin-bottom: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        // Style - Star Rating Input
        $this->start_controls_section( 'star_input_style', [
            'label'     => __( 'Star Rating Input', 'kdna-ecommerce' ),
            'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_rating' => 'yes' ],
        ]);

        $this->add_control( 'star_input_color', [
            'label'     => __( 'Star Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f0ad4e',
            'selectors' => [
                '{{WRAPPER}} .kdna-star-rating-input input:checked ~ label' => 'color: {{VALUE}};',
                '{{WRAPPER}} .kdna-star-rating-input label:hover' => 'color: {{VALUE}};',
                '{{WRAPPER}} .kdna-star-rating-input label:hover ~ label' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control( 'star_input_empty_color', [
            'label'     => __( 'Empty Star Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ddd',
            'selectors' => [ '{{WRAPPER}} .kdna-star-rating-input label' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'star_input_size', [
            'label'     => __( 'Star Size', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 16, 'max' => 48 ] ],
            'default'   => [ 'size' => 24, 'unit' => 'px' ],
            'selectors' => [ '{{WRAPPER}} .kdna-star-rating-input label' => 'font-size: {{SIZE}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // Style - Submit Button
        // WooCommerce's woocommerce.css targets `#respond input#submit` (2 IDs).
        // We MUST include both #respond AND #submit in our selector so our
        // specificity (2 IDs + classes) beats theirs.  Combined with !important
        // this guarantees our values win.
        // For the editor preview (no #respond), target via .kdna-review-form-preview
        // with input#submit for equal specificity against any global #submit rules.
        $btn_sel = '{{WRAPPER}} .kdna-review-form-container #respond .form-submit input#submit[type="submit"], {{WRAPPER}} .kdna-review-form-container .kdna-review-form-preview .form-submit input#submit[type="submit"]';

        $this->start_controls_section( 'button_style', [
            'label' => __( 'Submit Button', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'button_bg', [
            'label'     => __( 'Background Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [ $btn_sel => 'background-color: {{VALUE}} !important; border-color: {{VALUE}} !important;' ],
        ]);

        $this->add_control( 'button_text_color', [
            'label'     => __( 'Text Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [ $btn_sel => 'color: {{VALUE}} !important;' ],
        ]);

        $this->add_responsive_control( 'button_padding', [
            'label'      => __( 'Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'selectors'  => [ $btn_sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;' ],
        ]);

        $this->add_control( 'button_border_radius', [
            'label'      => __( 'Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
            'selectors'  => [ $btn_sel => 'border-radius: {{SIZE}}{{UNIT}} !important;' ],
        ]);

        $this->add_control( 'button_font_size', [
            'label'     => __( 'Font Size', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 10, 'max' => 30 ] ],
            'selectors' => [ $btn_sel => 'font-size: {{SIZE}}{{UNIT}} !important;' ],
        ]);

        $this->add_control( 'button_font_weight', [
            'label'   => __( 'Font Weight', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '',
            'options' => [
                ''    => __( 'Default', 'kdna-ecommerce' ),
                '300' => '300',
                '400' => '400 (Normal)',
                '500' => '500',
                '600' => '600 (Semi Bold)',
                '700' => '700 (Bold)',
                '800' => '800',
            ],
            'selectors' => [ $btn_sel => 'font-weight: {{VALUE}} !important;' ],
        ]);

        $this->add_control( 'button_text_transform', [
            'label'   => __( 'Text Transform', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '',
            'options' => [
                ''           => __( 'Default', 'kdna-ecommerce' ),
                'none'       => __( 'None', 'kdna-ecommerce' ),
                'uppercase'  => __( 'Uppercase', 'kdna-ecommerce' ),
                'lowercase'  => __( 'Lowercase', 'kdna-ecommerce' ),
                'capitalize' => __( 'Capitalize', 'kdna-ecommerce' ),
            ],
            'selectors' => [ $btn_sel => 'text-transform: {{VALUE}} !important;' ],
        ]);

        $this->add_control( 'button_letter_spacing', [
            'label'     => __( 'Letter Spacing', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => -2, 'max' => 10, 'step' => 0.1 ] ],
            'selectors' => [ $btn_sel => 'letter-spacing: {{SIZE}}{{UNIT}} !important;' ],
        ]);

        $this->add_control( 'button_width', [
            'label'   => __( 'Full Width', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'selectors' => [ $btn_sel => 'width: 100% !important;' ],
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

        if ( ! $product && ! $is_editor ) {
            return;
        }

        $reviews_module = kdna_ecommerce()->get_module( 'reviews' );
        $review_settings = $reviews_module ? $reviews_module->get_settings() : [];

        echo '<div class="kdna-review-form-container">';

        if ( ! empty( $settings['form_title'] ) ) {
            echo '<h3 class="kdna-review-form-title">' . esc_html( $settings['form_title'] ) . '</h3>';
        }

        if ( ! empty( $settings['form_subtitle'] ) ) {
            echo '<p class="kdna-review-form-subtitle">' . esc_html( $settings['form_subtitle'] ) . '</p>';
        }

        if ( $is_editor ) {
            $this->render_editor_preview( $settings, $review_settings );
        } else {
            $this->render_live_form( $product, $settings, $review_settings, $reviews_module );
        }

        echo '</div>';
    }

    private function render_live_form( $product, $settings, $review_settings, $reviews_module ) {
        if ( ! comments_open( $product->get_id() ) ) {
            echo '<p>' . esc_html__( 'Reviews are closed for this product.', 'kdna-ecommerce' ) . '</p>';
            return;
        }

        if ( ! is_user_logged_in() && get_option( 'comment_registration' ) ) {
            echo '<p>' . wp_kses_post( sprintf(
                __( 'You must be <a href="%s">logged in</a> to post a review.', 'kdna-ecommerce' ),
                esc_url( wp_login_url( get_permalink( $product->get_id() ) ) )
            ) ) . '</p>';
            return;
        }

        // IMPORTANT: Temporarily remove the KDNA_Reviews module's hooks that add
        // duplicate fields to the comment form. The widget provides its own fields.
        if ( $reviews_module ) {
            remove_action( 'comment_form_logged_in_after', [ $reviews_module, 'add_review_fields' ] );
            remove_action( 'comment_form_after_fields', [ $reviews_module, 'add_review_fields' ] );
        }

        // Build the comment form with our custom fields.
        $comment_form = [
            'title_reply'         => '',
            'title_reply_before'  => '',
            'title_reply_after'   => '',
            'comment_notes_after' => '',
            'label_submit'        => __( 'Submit Review', 'kdna-ecommerce' ),
            'logged_in_as'        => '',
            'comment_field'       => '',
        ];

        // Star rating field.
        if ( $settings['show_rating'] === 'yes' && get_option( 'woocommerce_enable_review_rating' ) === 'yes' ) {
            $comment_form['comment_field'] .= '<div class="kdna-form-field kdna-form-rating-field">';
            $comment_form['comment_field'] .= '<label>' . esc_html__( 'Your Rating', 'kdna-ecommerce' ) . ' <span class="required">*</span></label>';
            $comment_form['comment_field'] .= '<div class="kdna-star-rating-input">';
            for ( $i = 5; $i >= 1; $i-- ) {
                $comment_form['comment_field'] .= '<input type="radio" name="rating" value="' . $i . '" id="kdna_rating_' . $i . '" required>';
                $comment_form['comment_field'] .= '<label for="kdna_rating_' . $i . '">&#9733;</label>';
            }
            $comment_form['comment_field'] .= '</div></div>';
        }

        // Review title field.
        if ( $settings['show_title_field'] === 'yes' ) {
            $comment_form['comment_field'] .= '<div class="kdna-form-field kdna-review-title-field">';
            $comment_form['comment_field'] .= '<label for="kdna_review_title">' . esc_html__( 'Review Title', 'kdna-ecommerce' ) . '</label>';
            $comment_form['comment_field'] .= '<input type="text" id="kdna_review_title" name="kdna_review_title" maxlength="200" style="width:100%;box-sizing:border-box;">';
            $comment_form['comment_field'] .= '</div>';
        }

        // Comment textarea.
        $comment_form['comment_field'] .= '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'Your Review', 'kdna-ecommerce' ) . ' <span class="required">*</span></label>';
        $comment_form['comment_field'] .= '<textarea id="comment" name="comment" cols="45" rows="8" required style="width:100%;box-sizing:border-box;"></textarea></p>';

        // Photo upload field.
        if ( $settings['show_photos_field'] === 'yes' && ( $review_settings['enable_photos'] ?? 'no' ) === 'yes' ) {
            $max = (int) ( $review_settings['max_attachments'] ?? 5 );
            $size = (int) ( $review_settings['max_file_size'] ?? 5 );
            $comment_form['comment_field'] .= '<div class="kdna-form-field kdna-upload-field">';
            $comment_form['comment_field'] .= '<label for="kdna_review_photos">' . esc_html__( 'Upload Photos', 'kdna-ecommerce' ) . '</label>';
            $comment_form['comment_field'] .= '<input type="file" id="kdna_review_photos" name="kdna_review_photos[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>';
            $comment_form['comment_field'] .= '<p class="description">' . esc_html( sprintf( __( 'Max %d files, %dMB each', 'kdna-ecommerce' ), $max, $size ) ) . '</p>';
            $comment_form['comment_field'] .= '</div>';
        }

        // Video URL field — accepts any oEmbed-compatible URL.
        if ( $settings['show_video_field'] === 'yes' && ( $review_settings['enable_videos'] ?? 'no' ) === 'yes' ) {
            $comment_form['comment_field'] .= '<div class="kdna-form-field kdna-video-field">';
            $comment_form['comment_field'] .= '<label for="kdna_review_video_url">' . esc_html__( 'Video URL', 'kdna-ecommerce' ) . '</label>';
            $comment_form['comment_field'] .= '<input type="url" id="kdna_review_video_url" name="kdna_review_video_url" placeholder="https://..." style="width:100%;box-sizing:border-box;">';
            $comment_form['comment_field'] .= '<p class="description">' . esc_html__( 'Supports YouTube, Vimeo, Dailymotion, and other oEmbed-compatible video URLs.', 'kdna-ecommerce' ) . '</p>';
            $comment_form['comment_field'] .= '</div>';
        }

        // Qualifier fields.
        if ( $reviews_module && ( $review_settings['enable_qualifiers'] ?? 'no' ) === 'yes' ) {
            $labels = array_map( 'trim', explode( ',', $review_settings['qualifier_labels'] ?? '' ) );
            $labels = array_filter( $labels );
            if ( ! empty( $labels ) ) {
                $comment_form['comment_field'] .= '<div class="kdna-form-field kdna-review-qualifiers">';
                $comment_form['comment_field'] .= '<p><strong>' . esc_html__( 'Rate the following:', 'kdna-ecommerce' ) . '</strong></p>';
                foreach ( $labels as $index => $label ) {
                    $field_name = 'kdna_qualifier_' . $index;
                    $comment_form['comment_field'] .= '<div class="kdna-qualifier-field">';
                    $comment_form['comment_field'] .= '<label>' . esc_html( $label ) . '</label>';
                    $comment_form['comment_field'] .= '<div class="kdna-star-rating-input" data-field="' . esc_attr( $field_name ) . '">';
                    for ( $i = 5; $i >= 1; $i-- ) {
                        $comment_form['comment_field'] .= '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . $i . '" id="' . esc_attr( $field_name . '_' . $i ) . '">';
                        $comment_form['comment_field'] .= '<label for="' . esc_attr( $field_name . '_' . $i ) . '">&#9733;</label>';
                    }
                    $comment_form['comment_field'] .= '</div></div>';
                }
                $comment_form['comment_field'] .= '</div>';
            }
        }

        // Add enctype to the form tag for file uploads.
        add_action( 'comment_form_top', function() {
            static $done = false;
            if ( $done ) return;
            $done = true;
            echo '<script>document.addEventListener("DOMContentLoaded",function(){var f=document.getElementById("commentform");if(f)f.enctype="multipart/form-data";});</script>';
        });

        comment_form( $comment_form, $product->get_id() );

        // Re-add the hooks so the native WooCommerce review form still works
        // if it's rendered elsewhere on the page.
        if ( $reviews_module ) {
            add_action( 'comment_form_logged_in_after', [ $reviews_module, 'add_review_fields' ] );
            add_action( 'comment_form_after_fields', [ $reviews_module, 'add_review_fields' ] );
        }
    }

    private function render_editor_preview( $settings, $review_settings ) {
        ?>
        <div class="kdna-review-form-preview">
            <?php if ( $settings['show_rating'] === 'yes' ) : ?>
            <div class="kdna-form-field kdna-form-rating-field">
                <label><?php esc_html_e( 'Your Rating', 'kdna-ecommerce' ); ?> <span class="required">*</span></label>
                <div class="kdna-star-rating-input">
                    <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                    <input type="radio" name="preview_rating" value="<?php echo $i; ?>" id="preview_rating_<?php echo $i; ?>" <?php echo $i === 5 ? 'checked' : ''; ?>>
                    <label for="preview_rating_<?php echo $i; ?>">&#9733;</label>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( $settings['show_title_field'] === 'yes' ) : ?>
            <div class="kdna-form-field">
                <label><?php esc_html_e( 'Review Title', 'kdna-ecommerce' ); ?></label>
                <input type="text" style="width:100%;box-sizing:border-box;" disabled>
            </div>
            <?php endif; ?>

            <div class="kdna-form-field comment-form-comment">
                <label><?php esc_html_e( 'Your Review', 'kdna-ecommerce' ); ?> <span class="required">*</span></label>
                <textarea rows="5" style="width:100%;box-sizing:border-box;" disabled></textarea>
            </div>

            <?php if ( $settings['show_photos_field'] === 'yes' ) : ?>
            <div class="kdna-form-field">
                <label><?php esc_html_e( 'Upload Photos', 'kdna-ecommerce' ); ?></label>
                <input type="file" disabled>
            </div>
            <?php endif; ?>

            <?php if ( $settings['show_video_field'] === 'yes' ) : ?>
            <div class="kdna-form-field">
                <label><?php esc_html_e( 'Video URL', 'kdna-ecommerce' ); ?></label>
                <input type="url" style="width:100%;box-sizing:border-box;" placeholder="https://..." disabled>
                <p class="description"><?php esc_html_e( 'Supports YouTube, Vimeo, Dailymotion, and other oEmbed-compatible video URLs.', 'kdna-ecommerce' ); ?></p>
            </div>
            <?php endif; ?>

            <p class="form-submit" style="margin-top:16px;">
                <input type="submit" id="submit" class="submit" value="<?php esc_attr_e( 'Submit Review', 'kdna-ecommerce' ); ?>" disabled>
            </p>
        </div>
        <?php
    }
}
