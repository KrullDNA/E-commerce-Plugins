<?php
defined( 'ABSPATH' ) || exit;

class KDNA_Reviews {

    private $settings;

    public function __construct() {
        $this->settings = wp_parse_args( get_option( 'kdna_reviews_settings', [] ), [
            'enable_photos'     => 'yes',
            'enable_videos'     => 'yes',
            'enable_voting'     => 'yes',
            'enable_flagging'   => 'yes',
            'enable_qualifiers' => 'no',
            'qualifier_labels'  => '',
            'max_attachments'   => '5',
            'max_file_size'     => '5',
        ]);

        // Register qualifier taxonomy
        if ( $this->settings['enable_qualifiers'] === 'yes' ) {
            add_action( 'init', [ $this, 'register_qualifier_taxonomy' ] );
        }

        // Modify review form
        add_action( 'comment_form_logged_in_after', [ $this, 'add_review_fields' ] );
        add_action( 'comment_form_after_fields', [ $this, 'add_review_fields' ] );

        // Save review meta
        add_action( 'comment_post', [ $this, 'save_review_meta' ], 10, 2 );

        // Display enhancements
        add_filter( 'comment_text', [ $this, 'enhance_review_display' ], 20, 2 );

        // Voting AJAX
        if ( $this->settings['enable_voting'] === 'yes' ) {
            add_action( 'wp_ajax_kdna_review_vote', [ $this, 'handle_vote' ] );
        }

        // Flagging AJAX
        if ( $this->settings['enable_flagging'] === 'yes' ) {
            add_action( 'wp_ajax_kdna_review_flag', [ $this, 'handle_flag' ] );
        }

        // Sort reviews by helpfulness
        add_filter( 'comments_clauses', [ $this, 'sort_by_helpfulness' ], 10, 2 );

        // Enqueue assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Handle file uploads
        add_action( 'pre_comment_on_post', [ $this, 'process_upload' ] );
    }

    public function register_qualifier_taxonomy() {
        register_taxonomy( 'review_qualifier', 'product', [
            'label'        => __( 'Review Qualifiers', 'kdna-ecommerce' ),
            'hierarchical' => false,
            'public'       => false,
            'show_ui'      => true,
            'meta_box_cb'  => false,
        ]);
    }

    // ─── Review Form Fields ───

    public function add_review_fields() {
        global $product;
        if ( ! $product || ! is_product() ) {
            return;
        }
        ?>
        <div class="kdna-review-fields">
            <?php if ( $this->settings['enable_photos'] === 'yes' || $this->settings['enable_videos'] === 'yes' ) : ?>
            <div class="kdna-review-attachments-field">
                <label><?php esc_html_e( 'Attachments', 'kdna-ecommerce' ); ?></label>
                <?php if ( $this->settings['enable_photos'] === 'yes' ) : ?>
                <div class="kdna-upload-field">
                    <label for="kdna_review_photos"><?php esc_html_e( 'Upload Photos', 'kdna-ecommerce' ); ?></label>
                    <input type="file" id="kdna_review_photos" name="kdna_review_photos[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                    <p class="description"><?php echo esc_html( sprintf( __( 'Max %d files, %dMB each', 'kdna-ecommerce' ), (int) $this->settings['max_attachments'], (int) $this->settings['max_file_size'] ) ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( $this->settings['enable_videos'] === 'yes' ) : ?>
                <div class="kdna-video-field">
                    <label for="kdna_review_video_url"><?php esc_html_e( 'Video URL', 'kdna-ecommerce' ); ?></label>
                    <input type="url" id="kdna_review_video_url" name="kdna_review_video_url" placeholder="https://...">
                    <p class="description"><?php esc_html_e( 'Supports YouTube, Vimeo, Dailymotion, and other oEmbed-compatible video URLs.', 'kdna-ecommerce' ); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ( $this->settings['enable_qualifiers'] === 'yes' ) : ?>
            <?php $this->render_qualifier_fields( $product->get_id() ); ?>
            <?php endif; ?>

            <p class="kdna-review-title-field">
                <label for="kdna_review_title"><?php esc_html_e( 'Review Title', 'kdna-ecommerce' ); ?></label>
                <input type="text" id="kdna_review_title" name="kdna_review_title" maxlength="200">
            </p>
        </div>
        <?php
    }

    private function render_qualifier_fields( $product_id ) {
        $labels = array_map( 'trim', explode( ',', $this->settings['qualifier_labels'] ) );
        $labels = array_filter( $labels );

        if ( empty( $labels ) ) {
            return;
        }

        echo '<div class="kdna-review-qualifiers">';
        echo '<p><strong>' . esc_html__( 'Rate the following:', 'kdna-ecommerce' ) . '</strong></p>';

        foreach ( $labels as $index => $label ) {
            $field_name = 'kdna_qualifier_' . $index;
            echo '<div class="kdna-qualifier-field">';
            echo '<label>' . esc_html( $label ) . '</label>';
            echo '<div class="kdna-star-rating-input" data-field="' . esc_attr( $field_name ) . '">';
            for ( $i = 5; $i >= 1; $i-- ) {
                echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . $i . '" id="' . esc_attr( $field_name . '_' . $i ) . '">';
                echo '<label for="' . esc_attr( $field_name . '_' . $i ) . '">&#9733;</label>';
            }
            echo '</div></div>';
        }
        echo '</div>';
    }

    // ─── Upload Processing ───

    public function process_upload( $comment_post_ID ) {
        $post = get_post( $comment_post_ID );
        if ( ! $post || $post->post_type !== 'product' ) {
            return;
        }

        if ( empty( $_FILES['kdna_review_photos']['name'][0] ) ) {
            return;
        }

        $max_size = (int) $this->settings['max_file_size'] * 1024 * 1024;
        $max_files = (int) $this->settings['max_attachments'];
        $files = $_FILES['kdna_review_photos'];
        $file_count = min( count( $files['name'] ), $max_files );

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_ids = [];
        $allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];

        for ( $i = 0; $i < $file_count; $i++ ) {
            if ( $files['error'][ $i ] !== UPLOAD_ERR_OK ) {
                continue;
            }
            if ( $files['size'][ $i ] > $max_size ) {
                continue;
            }
            if ( ! in_array( $files['type'][ $i ], $allowed_types, true ) ) {
                continue;
            }

            $_FILES['kdna_upload'] = [
                'name'     => sanitize_file_name( $files['name'][ $i ] ),
                'type'     => $files['type'][ $i ],
                'tmp_name' => $files['tmp_name'][ $i ],
                'error'    => $files['error'][ $i ],
                'size'     => $files['size'][ $i ],
            ];

            $attachment_id = media_handle_upload( 'kdna_upload', $comment_post_ID );
            if ( ! is_wp_error( $attachment_id ) ) {
                $attachment_ids[] = $attachment_id;
            }
        }

        if ( ! empty( $attachment_ids ) ) {
            // Store temporarily for comment_post hook to pick up
            $GLOBALS['kdna_review_attachment_ids'] = $attachment_ids;
        }
    }

    // ─── Save Review Meta ───

    public function save_review_meta( $comment_id, $approved ) {
        $comment = get_comment( $comment_id );
        if ( ! $comment || get_post_type( $comment->comment_post_ID ) !== 'product' ) {
            return;
        }

        // Title
        if ( ! empty( $_POST['kdna_review_title'] ) ) {
            update_comment_meta( $comment_id, '_kdna_review_title', sanitize_text_field( $_POST['kdna_review_title'] ) );
        }

        // Photo attachments
        if ( ! empty( $GLOBALS['kdna_review_attachment_ids'] ) ) {
            update_comment_meta( $comment_id, '_kdna_attachment_ids', array_map( 'absint', $GLOBALS['kdna_review_attachment_ids'] ) );
            update_comment_meta( $comment_id, '_kdna_attachment_type', 'photo' );
        }

        // Video URL
        if ( ! empty( $_POST['kdna_review_video_url'] ) ) {
            $url = esc_url_raw( $_POST['kdna_review_video_url'] );
            if ( $url ) {
                update_comment_meta( $comment_id, '_kdna_video_url', $url );
                update_comment_meta( $comment_id, '_kdna_attachment_type', 'video' );
            }
        }

        // Qualifiers
        if ( $this->settings['enable_qualifiers'] === 'yes' ) {
            $labels = array_map( 'trim', explode( ',', $this->settings['qualifier_labels'] ) );
            foreach ( $labels as $index => $label ) {
                $key = 'kdna_qualifier_' . $index;
                if ( isset( $_POST[ $key ] ) ) {
                    update_comment_meta( $comment_id, '_kdna_qualifier_' . sanitize_key( $label ), absint( $_POST[ $key ] ) );
                }
            }
        }

        // Initialize vote counts
        update_comment_meta( $comment_id, '_kdna_positive_votes', 0 );
        update_comment_meta( $comment_id, '_kdna_negative_votes', 0 );
    }

    // ─── Enhanced Display ───

    public function enhance_review_display( $text, $comment = null ) {
        if ( ! $comment || ! is_product() || get_post_type( $comment->comment_post_ID ) !== 'product' ) {
            return $text;
        }

        $output = '';

        // Title
        $title = get_comment_meta( $comment->comment_ID, '_kdna_review_title', true );
        if ( $title ) {
            $output .= '<strong class="kdna-review-title">' . esc_html( $title ) . '</strong>';
        }

        $output .= $text;

        // Qualifiers
        if ( $this->settings['enable_qualifiers'] === 'yes' ) {
            $labels = array_map( 'trim', explode( ',', $this->settings['qualifier_labels'] ) );
            $qualifier_html = '';
            foreach ( $labels as $label ) {
                $rating = (int) get_comment_meta( $comment->comment_ID, '_kdna_qualifier_' . sanitize_key( $label ), true );
                if ( $rating > 0 ) {
                    $stars = str_repeat( '&#9733;', $rating ) . str_repeat( '&#9734;', 5 - $rating );
                    $qualifier_html .= '<span class="kdna-qualifier"><span class="label">' . esc_html( $label ) . ':</span> <span class="stars">' . $stars . '</span></span>';
                }
            }
            if ( $qualifier_html ) {
                $output .= '<div class="kdna-review-qualifiers-display">' . $qualifier_html . '</div>';
            }
        }

        // Photo attachments
        $attachment_ids = get_comment_meta( $comment->comment_ID, '_kdna_attachment_ids', true );
        if ( ! empty( $attachment_ids ) && is_array( $attachment_ids ) ) {
            $output .= '<div class="kdna-review-photos">';
            foreach ( $attachment_ids as $id ) {
                $url = wp_get_attachment_url( $id );
                $thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
                if ( $url ) {
                    $output .= '<a href="' . esc_url( $url ) . '" target="_blank" class="kdna-review-photo">';
                    $output .= '<img src="' . esc_url( $thumb ?: $url ) . '" alt="" loading="lazy">';
                    $output .= '</a>';
                }
            }
            $output .= '</div>';
        }

        // Video
        $video_url = get_comment_meta( $comment->comment_ID, '_kdna_video_url', true );
        if ( $video_url ) {
            $embed = wp_oembed_get( $video_url );
            if ( $embed ) {
                $output .= '<div class="kdna-review-video">' . $embed . '</div>';
            }
        }

        // Voting
        if ( $this->settings['enable_voting'] === 'yes' ) {
            $positive = (int) get_comment_meta( $comment->comment_ID, '_kdna_positive_votes', true );
            $negative = (int) get_comment_meta( $comment->comment_ID, '_kdna_negative_votes', true );
            $output .= '<div class="kdna-review-voting" data-comment-id="' . esc_attr( $comment->comment_ID ) . '">';
            $output .= '<span class="kdna-helpful-label">' . esc_html__( 'Was this helpful?', 'kdna-ecommerce' ) . '</span>';
            $output .= '<button class="kdna-vote-btn kdna-vote-up" data-vote="positive" title="' . esc_attr__( 'Helpful', 'kdna-ecommerce' ) . '">&#9650; <span class="count">' . $positive . '</span></button>';
            $output .= '<button class="kdna-vote-btn kdna-vote-down" data-vote="negative" title="' . esc_attr__( 'Not helpful', 'kdna-ecommerce' ) . '">&#9660; <span class="count">' . $negative . '</span></button>';
            $output .= '</div>';
        }

        // Flagging
        if ( $this->settings['enable_flagging'] === 'yes' && is_user_logged_in() ) {
            $output .= '<div class="kdna-review-flag">';
            $output .= '<button class="kdna-flag-btn" data-comment-id="' . esc_attr( $comment->comment_ID ) . '" title="' . esc_attr__( 'Report this review', 'kdna-ecommerce' ) . '">&#9873; ' . esc_html__( 'Report', 'kdna-ecommerce' ) . '</button>';
            $output .= '</div>';
        }

        return $output;
    }

    // ─── Voting ───

    public function handle_vote() {
        check_ajax_referer( 'kdna-reviews-nonce', 'nonce' );

        $comment_id = absint( $_POST['comment_id'] ?? 0 );
        $vote_type = sanitize_text_field( $_POST['vote_type'] ?? '' );
        $user_id = get_current_user_id();

        if ( ! $comment_id || ! in_array( $vote_type, [ 'positive', 'negative' ], true ) || ! $user_id ) {
            wp_send_json_error();
        }

        $comment = get_comment( $comment_id );
        if ( ! $comment || (int) $comment->user_id === $user_id ) {
            wp_send_json_error( [ 'message' => __( 'Cannot vote on your own review.', 'kdna-ecommerce' ) ] );
        }

        $votes = get_comment_meta( $comment_id, '_kdna_user_votes', true );
        if ( ! is_array( $votes ) ) {
            $votes = [];
        }

        $previous_vote = $votes[ $user_id ] ?? null;

        // Remove previous vote
        if ( $previous_vote ) {
            $meta_key = '_kdna_' . $previous_vote . '_votes';
            $count = max( 0, (int) get_comment_meta( $comment_id, $meta_key, true ) - 1 );
            update_comment_meta( $comment_id, $meta_key, $count );
        }

        // Toggle off if same vote
        if ( $previous_vote === $vote_type ) {
            unset( $votes[ $user_id ] );
        } else {
            $votes[ $user_id ] = $vote_type;
            $meta_key = '_kdna_' . $vote_type . '_votes';
            $count = (int) get_comment_meta( $comment_id, $meta_key, true ) + 1;
            update_comment_meta( $comment_id, $meta_key, $count );
        }

        update_comment_meta( $comment_id, '_kdna_user_votes', $votes );

        // Update karma for sorting
        $positive = (int) get_comment_meta( $comment_id, '_kdna_positive_votes', true );
        $negative = (int) get_comment_meta( $comment_id, '_kdna_negative_votes', true );
        wp_update_comment( [ 'comment_ID' => $comment_id, 'comment_karma' => $positive - $negative ] );

        wp_send_json_success([
            'positive' => $positive,
            'negative' => $negative,
        ]);
    }

    // ─── Flagging ───

    public function handle_flag() {
        check_ajax_referer( 'kdna-reviews-nonce', 'nonce' );

        $comment_id = absint( $_POST['comment_id'] ?? 0 );
        $reason = sanitize_text_field( $_POST['reason'] ?? '' );
        $user_id = get_current_user_id();

        if ( ! $comment_id || ! $user_id ) {
            wp_send_json_error();
        }

        $comment = get_comment( $comment_id );
        if ( ! $comment || (int) $comment->user_id === $user_id ) {
            wp_send_json_error( [ 'message' => __( 'Cannot flag your own review.', 'kdna-ecommerce' ) ] );
        }

        $flagged_users = get_comment_meta( $comment_id, '_kdna_flagged_users', true );
        if ( ! is_array( $flagged_users ) ) {
            $flagged_users = [];
        }

        if ( in_array( $user_id, $flagged_users, true ) ) {
            wp_send_json_error( [ 'message' => __( 'You have already flagged this review.', 'kdna-ecommerce' ) ] );
        }

        $flagged_users[] = $user_id;
        update_comment_meta( $comment_id, '_kdna_flagged_users', $flagged_users );

        $flag_count = (int) get_comment_meta( $comment_id, '_kdna_flag_count', true ) + 1;
        update_comment_meta( $comment_id, '_kdna_flag_count', $flag_count );

        $flags = get_comment_meta( $comment_id, '_kdna_flags', true );
        if ( ! is_array( $flags ) ) {
            $flags = [];
        }
        $flags[] = [
            'user_id'   => $user_id,
            'reason'    => $reason,
            'timestamp' => current_time( 'mysql' ),
        ];
        update_comment_meta( $comment_id, '_kdna_flags', $flags );

        // Auto-moderate if threshold reached (default 3)
        $threshold = apply_filters( 'kdna_review_flag_threshold', 3 );
        if ( $flag_count >= $threshold ) {
            wp_set_comment_status( $comment_id, 'hold' );
        }

        wp_send_json_success( [ 'message' => __( 'Review has been flagged for moderation.', 'kdna-ecommerce' ) ] );
    }

    // ─── Sort by Helpfulness ───

    public function sort_by_helpfulness( $clauses, $query ) {
        if ( ! is_product() || ! isset( $query->query_vars['post_type'] ) ) {
            return $clauses;
        }

        // Only apply to product reviews
        if ( isset( $query->query_vars['type'] ) && $query->query_vars['type'] === 'review' ) {
            $clauses['orderby'] = 'comment_karma DESC, comment_date_gmt DESC';
        }

        return $clauses;
    }

    // ─── Assets ───

    public function enqueue_assets() {
        if ( ! is_product() ) {
            return;
        }

        wp_enqueue_style( 'kdna-reviews', KDNA_ECOMMERCE_URL . 'assets/css/reviews.css', [], KDNA_ECOMMERCE_VERSION );
        wp_enqueue_script( 'kdna-reviews', KDNA_ECOMMERCE_URL . 'assets/js/reviews.js', [ 'jquery' ], KDNA_ECOMMERCE_VERSION, true );
        wp_localize_script( 'kdna-reviews', 'kdnaReviews', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'kdna-reviews-nonce' ),
        ]);

        // Ensure comment form supports file uploads
        add_filter( 'comment_form_defaults', function( $defaults ) {
            if ( strpos( $defaults['id_form'] ?? '', 'commentform' ) !== false ) {
                $defaults['id_form'] = $defaults['id_form'] ?? 'commentform';
            }
            return $defaults;
        });
    }

    public function get_settings() {
        return $this->settings;
    }

    public static function get_review_data( $product_id ) {
        $reviews = get_comments([
            'post_id' => $product_id,
            'status'  => 'approve',
            'type'    => 'review',
            'orderby' => 'comment_karma',
            'order'   => 'DESC',
        ]);

        $data = [];
        foreach ( $reviews as $review ) {
            $data[] = [
                'id'            => $review->comment_ID,
                'author'        => $review->comment_author,
                'date'          => $review->comment_date,
                'content'       => $review->comment_content,
                'rating'        => (int) get_comment_meta( $review->comment_ID, 'rating', true ),
                'title'         => get_comment_meta( $review->comment_ID, '_kdna_review_title', true ),
                'photos'        => get_comment_meta( $review->comment_ID, '_kdna_attachment_ids', true ),
                'video_url'     => get_comment_meta( $review->comment_ID, '_kdna_video_url', true ),
                'positive_votes'=> (int) get_comment_meta( $review->comment_ID, '_kdna_positive_votes', true ),
                'negative_votes'=> (int) get_comment_meta( $review->comment_ID, '_kdna_negative_votes', true ),
            ];
        }

        return $data;
    }
}
