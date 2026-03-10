<?php
defined( 'ABSPATH' ) || exit;

class KDNA_Reviews_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'kdna_product_reviews';
    }

    public function get_title() {
        return __( 'KDNA Product Reviews', 'kdna-ecommerce' );
    }

    public function get_icon() {
        return 'eicon-review';
    }

    public function get_categories() {
        return [ 'woocommerce-elements' ];
    }

    public function get_keywords() {
        return [ 'reviews', 'woocommerce', 'ratings', 'kdna' ];
    }

    protected function register_controls() {

        // Content
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Content', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control( 'show_summary', [
            'label'   => __( 'Show Rating Summary', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'show_photos', [
            'label'   => __( 'Show Photos', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'show_voting', [
            'label'   => __( 'Show Voting', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'reviews_per_page', [
            'label'   => __( 'Reviews Per Page', 'kdna-ecommerce' ),
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 10,
            'min'     => 1,
            'max'     => 50,
        ]);

        $this->end_controls_section();

        // Style - Container
        $this->start_controls_section( 'container_style', [
            'label' => __( 'Container', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'container_bg', [
            'label'     => __( 'Background', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .kdna-reviews-widget' => 'background-color: {{VALUE}};' ],
        ]);

        $this->add_responsive_control( 'container_padding', [
            'label'      => __( 'Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-reviews-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // Style - Summary
        $this->start_controls_section( 'summary_style', [
            'label'     => __( 'Rating Summary', 'kdna-ecommerce' ),
            'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_summary' => 'yes' ],
        ]);

        $this->add_control( 'summary_bg', [
            'label'     => __( 'Background', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f9f9f9',
            'selectors' => [ '{{WRAPPER}} .kdna-reviews-summary' => 'background-color: {{VALUE}};' ],
        ]);

        $this->add_control( 'star_color', [
            'label'     => __( 'Star Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f0ad4e',
            'selectors' => [ '{{WRAPPER}} .kdna-reviews-widget .star-rating span::before, {{WRAPPER}} .kdna-review-stars' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'star_empty_color', [
            'label'     => __( 'Empty Star Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ddd',
            'selectors' => [ '{{WRAPPER}} .kdna-reviews-widget .star-rating::before' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'average_size', [
            'label'      => __( 'Average Rating Size', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => [ 'px' => [ 'min' => 20, 'max' => 80 ] ],
            'default'    => [ 'size' => 48, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-average-rating' => 'font-size: {{SIZE}}{{UNIT}};' ],
        ]);

        $this->add_responsive_control( 'summary_padding', [
            'label'      => __( 'Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-reviews-summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_control( 'summary_border_radius', [
            'label'      => __( 'Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px' ],
            'default'    => [ 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-reviews-summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // Style - Individual Review
        $this->start_controls_section( 'review_style', [
            'label' => __( 'Individual Review', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control( 'review_border_color', [
            'label'     => __( 'Border Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#eee',
            'selectors' => [ '{{WRAPPER}} .kdna-review-item' => 'border-bottom-color: {{VALUE}};' ],
        ]);

        $this->add_responsive_control( 'review_padding', [
            'label'      => __( 'Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '16', 'right' => '0', 'bottom' => '16', 'left' => '0', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-review-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'review_title_typography',
            'label'    => __( 'Title Typography', 'kdna-ecommerce' ),
            'selector' => '{{WRAPPER}} .kdna-review-item-title',
        ]);

        $this->add_control( 'review_title_color', [
            'label'     => __( 'Title Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#333',
            'selectors' => [ '{{WRAPPER}} .kdna-review-item-title' => 'color: {{VALUE}};' ],
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'review_content_typography',
            'label'    => __( 'Content Typography', 'kdna-ecommerce' ),
            'selector' => '{{WRAPPER}} .kdna-review-item-content',
        ]);

        $this->add_control( 'review_content_color', [
            'label'     => __( 'Content Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#666',
            'selectors' => [ '{{WRAPPER}} .kdna-review-item-content' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'review_meta_color', [
            'label'     => __( 'Author/Date Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#999',
            'selectors' => [ '{{WRAPPER}} .kdna-review-item-meta' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'photo_size', [
            'label'      => __( 'Photo Thumbnail Size', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => [ 'px' => [ 'min' => 40, 'max' => 200 ] ],
            'default'    => [ 'size' => 80, 'unit' => 'px' ],
            'selectors'  => [
                '{{WRAPPER}} .kdna-review-item-photos img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
            'condition' => [ 'show_photos' => 'yes' ],
        ]);

        $this->add_control( 'photo_border_radius', [
            'label'      => __( 'Photo Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 20 ] ],
            'default'    => [ 'size' => 4, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-review-item-photos img' => 'border-radius: {{SIZE}}{{UNIT}};' ],
            'condition'  => [ 'show_photos' => 'yes' ],
        ]);

        $this->end_controls_section();

        // Style - Voting
        $this->start_controls_section( 'voting_style', [
            'label'     => __( 'Voting Buttons', 'kdna-ecommerce' ),
            'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_voting' => 'yes' ],
        ]);

        $this->add_control( 'vote_btn_color', [
            'label'     => __( 'Button Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#666',
            'selectors' => [ '{{WRAPPER}} .kdna-vote-btn' => 'color: {{VALUE}};' ],
        ]);

        $this->add_control( 'vote_btn_hover_color', [
            'label'     => __( 'Button Hover Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#2271b1',
            'selectors' => [ '{{WRAPPER}} .kdna-vote-btn:hover' => 'color: {{VALUE}}; border-color: {{VALUE}};' ],
        ]);

        $this->add_control( 'vote_btn_bg', [
            'label'     => __( 'Button Background', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .kdna-vote-btn' => 'background-color: {{VALUE}};' ],
        ]);

        $this->add_responsive_control( 'vote_btn_padding', [
            'label'      => __( 'Button Padding', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '4', 'right' => '10', 'bottom' => '4', 'left' => '10', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-vote-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ]);

        $this->add_control( 'vote_btn_border_radius', [
            'label'      => __( 'Button Border Radius', 'kdna-ecommerce' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 20 ] ],
            'default'    => [ 'size' => 3, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .kdna-vote-btn' => 'border-radius: {{SIZE}}{{UNIT}};' ],
        ]);

        $this->end_controls_section();

        // Style - Review Title
        $this->start_controls_section( 'review_title_style_section', [
            'label' => __( 'Review Title', 'kdna-ecommerce' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'review_item_title_typo',
            'label'    => __( 'Typography', 'kdna-ecommerce' ),
            'selector' => '{{WRAPPER}} .kdna-review-item-title',
        ]);

        $this->add_control( 'review_item_title_color', [
            'label'     => __( 'Color', 'kdna-ecommerce' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#333',
            'selectors' => [ '{{WRAPPER}} .kdna-review-item-title' => 'color: {{VALUE}};' ],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        global $product;
        if ( ! $product && is_product() ) {
            $product = wc_get_product( get_the_ID() );
        }

        $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();

        if ( ! $product && ! $is_editor ) {
            return;
        }

        $product_id = $product ? $product->get_id() : 0;

        echo '<div class="kdna-reviews-widget">';

        // Rating Summary
        if ( $settings['show_summary'] === 'yes' ) {
            $this->render_summary( $product, $is_editor );
        }

        // Reviews List
        $this->render_reviews_list( $product_id, $settings, $is_editor );

        echo '</div>';
    }

    private function render_summary( $product, $is_editor ) {
        if ( $is_editor && ! $product ) {
            $avg = 4.5;
            $count = 12;
        } else {
            $avg = (float) $product->get_average_rating();
            $count = (int) $product->get_review_count();
        }

        $stars_filled = str_repeat( '&#9733;', (int) round( $avg ) );
        $stars_empty = str_repeat( '&#9734;', 5 - (int) round( $avg ) );

        ?>
        <div class="kdna-reviews-summary">
            <div class="kdna-reviews-summary-inner">
                <span class="kdna-average-rating"><?php echo number_format( $avg, 1 ); ?></span>
                <div>
                    <div class="kdna-review-stars"><?php echo $stars_filled . $stars_empty; ?></div>
                    <div class="kdna-review-count">
                        <?php echo esc_html( sprintf( _n( '%d review', '%d reviews', $count, 'kdna-ecommerce' ), $count ) ); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_reviews_list( $product_id, $settings, $is_editor ) {
        if ( $is_editor && ! $product_id ) {
            $this->render_editor_preview();
            return;
        }

        $reviews = KDNA_Reviews::get_review_data( $product_id );

        if ( empty( $reviews ) ) {
            echo '<p class="kdna-no-reviews">' . esc_html__( 'No reviews yet.', 'kdna-ecommerce' ) . '</p>';
            return;
        }

        $per_page = (int) $settings['reviews_per_page'];
        $display_reviews = array_slice( $reviews, 0, $per_page );

        echo '<div class="kdna-reviews-list">';
        foreach ( $display_reviews as $review ) {
            $this->render_single_review( $review, $settings );
        }
        echo '</div>';
    }

    private function render_single_review( $review, $settings ) {
        $stars_filled = str_repeat( '&#9733;', $review['rating'] );
        $stars_empty = str_repeat( '&#9734;', 5 - $review['rating'] );
        ?>
        <div class="kdna-review-item">
            <div class="kdna-review-item-header">
                <span class="kdna-review-stars"><?php echo $stars_filled . $stars_empty; ?></span>
                <?php if ( $review['title'] ) : ?>
                    <span class="kdna-review-item-title"><?php echo esc_html( $review['title'] ); ?></span>
                <?php endif; ?>
            </div>

            <div class="kdna-review-item-content"><?php echo wp_kses_post( wpautop( $review['content'] ) ); ?></div>

            <?php if ( $settings['show_photos'] === 'yes' && ! empty( $review['photos'] ) && is_array( $review['photos'] ) ) : ?>
            <div class="kdna-review-item-photos">
                <?php foreach ( $review['photos'] as $id ) :
                    $url = wp_get_attachment_url( $id );
                    $thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
                    if ( $url ) : ?>
                    <a href="<?php echo esc_url( $url ); ?>" target="_blank">
                        <img src="<?php echo esc_url( $thumb ?: $url ); ?>" alt="" loading="lazy">
                    </a>
                    <?php endif; endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $review['video_url'] ) ) :
                $embed = wp_oembed_get( $review['video_url'] );
                if ( $embed ) : ?>
                <div class="kdna-review-item-video"><?php echo $embed; ?></div>
                <?php endif; endif; ?>

            <div class="kdna-review-item-meta">
                <?php echo esc_html( $review['author'] ); ?> &mdash; <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $review['date'] ) ) ); ?>
            </div>

            <?php if ( $settings['show_voting'] === 'yes' ) : ?>
            <div class="kdna-review-voting" data-comment-id="<?php echo esc_attr( $review['id'] ); ?>">
                <span class="kdna-helpful-label"><?php esc_html_e( 'Helpful?', 'kdna-ecommerce' ); ?></span>
                <button class="kdna-vote-btn kdna-vote-up" data-vote="positive">&#9650; <span class="count"><?php echo $review['positive_votes']; ?></span></button>
                <button class="kdna-vote-btn kdna-vote-down" data-vote="negative">&#9660; <span class="count"><?php echo $review['negative_votes']; ?></span></button>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_editor_preview() {
        ?>
        <div class="kdna-reviews-list">
            <div class="kdna-review-item">
                <div class="kdna-review-item-header">
                    <span class="kdna-review-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                    <span class="kdna-review-item-title">Great product!</span>
                </div>
                <div class="kdna-review-item-content"><p>This is an excellent product, highly recommended.</p></div>
                <div class="kdna-review-item-meta">John D. &mdash; <?php echo esc_html( date_i18n( get_option( 'date_format' ) ) ); ?></div>
            </div>
            <div class="kdna-review-item">
                <div class="kdna-review-item-header">
                    <span class="kdna-review-stars">&#9733;&#9733;&#9733;&#9733;&#9734;</span>
                    <span class="kdna-review-item-title">Very good quality</span>
                </div>
                <div class="kdna-review-item-content"><p>Good value for money, would buy again.</p></div>
                <div class="kdna-review-item-meta">Jane S. &mdash; <?php echo esc_html( date_i18n( get_option( 'date_format' ) ) ); ?></div>
            </div>
        </div>
        <?php
    }
}
