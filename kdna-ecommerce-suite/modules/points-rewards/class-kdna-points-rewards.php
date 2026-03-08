<?php
defined( 'ABSPATH' ) || exit;

class KDNA_Points_Rewards {

    private $settings;
    private $points_table;
    private $log_table;

    public function __construct() {
        global $wpdb;

        $this->points_table = $wpdb->prefix . 'kdna_points';
        $this->log_table    = $wpdb->prefix . 'kdna_points_log';

        $this->settings = wp_parse_args( get_option( 'kdna_points_settings', [] ), [
            'earn_ratio'            => '1:1',
            'redeem_ratio'          => '100:1',
            'points_label_singular' => 'Point',
            'points_label_plural'   => 'Points',
            'earn_account_signup'   => '0',
            'earn_review'           => '0',
            'max_discount'          => '',
            'max_discount_type'     => 'fixed',
            'product_message'       => 'Earn <strong>{points}</strong> {points_label} by purchasing this product.',
            'cart_message'          => 'Complete this order to earn <strong>{points}</strong> {points_label}.',
            'redeem_message'        => 'Use <strong>{points}</strong> {points_label} for a <strong>{discount}</strong> discount.',
            'expiry_period'         => '',
        ]);

        $this->create_tables();

        // Frontend hooks
        add_action( 'woocommerce_before_add_to_cart_form', [ $this, 'show_product_points_message' ] );
        add_action( 'woocommerce_before_cart', [ $this, 'show_cart_messages' ], 15 );
        add_action( 'woocommerce_before_checkout_form', [ $this, 'show_cart_messages' ], 5 );
        add_action( 'woocommerce_thankyou', [ $this, 'show_thankyou_message' ] );

        // Points earning on order completion
        add_action( 'woocommerce_order_status_completed', [ $this, 'award_points_for_order' ] );
        add_action( 'woocommerce_order_status_processing', [ $this, 'award_points_for_order' ] );

        // Points reversal on cancel/refund
        add_action( 'woocommerce_order_status_cancelled', [ $this, 'reverse_points_for_order' ] );
        add_action( 'woocommerce_order_status_refunded', [ $this, 'reverse_points_for_order' ] );
        add_action( 'woocommerce_order_partially_refunded', [ $this, 'handle_partial_refund' ], 10, 2 );

        // Redeem points via cart
        add_action( 'wp_loaded', [ $this, 'handle_redeem_action' ] );
        add_filter( 'woocommerce_get_shop_coupon_data', [ $this, 'get_virtual_coupon_data' ], 10, 2 );
        add_filter( 'woocommerce_cart_totals_coupon_label', [ $this, 'coupon_label' ], 10, 2 );

        // Account signup points
        if ( (int) $this->settings['earn_account_signup'] > 0 ) {
            add_action( 'user_register', [ $this, 'award_signup_points' ] );
        }

        // Review points
        if ( (int) $this->settings['earn_review'] > 0 ) {
            add_action( 'comment_post', [ $this, 'award_review_points' ], 10, 2 );
            add_action( 'comment_unapproved_to_approved', [ $this, 'award_review_points_on_approval' ] );
        }

        // My Account endpoint
        add_action( 'init', [ $this, 'add_endpoint' ] );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ] );
        add_action( 'woocommerce_account_kdna-points_endpoint', [ $this, 'render_my_points' ] );

        // Points expiry cron
        if ( ! empty( $this->settings['expiry_period'] ) ) {
            add_action( 'kdna_points_expire_daily', [ $this, 'expire_points' ] );
            if ( ! wp_next_scheduled( 'kdna_points_expire_daily' ) ) {
                wp_schedule_event( time(), 'daily', 'kdna_points_expire_daily' );
            }
        }

        // Enqueue frontend assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
    }

    private function create_tables() {
        global $wpdb;

        $installed = get_option( 'kdna_points_db_version', '' );
        if ( $installed === '1.0' ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        dbDelta( "CREATE TABLE {$this->points_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points bigint(20) NOT NULL,
            points_balance bigint(20) NOT NULL,
            order_id bigint(20) DEFAULT NULL,
            date datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_balance (user_id, points_balance),
            KEY date_balance (date, points_balance)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$this->log_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points bigint(20) NOT NULL,
            type varchar(255) DEFAULT NULL,
            user_points_id bigint(20) DEFAULT NULL,
            order_id bigint(20) DEFAULT NULL,
            admin_user_id bigint(20) DEFAULT NULL,
            data longtext DEFAULT NULL,
            date datetime NOT NULL,
            PRIMARY KEY (id),
            KEY log_date (date),
            KEY log_type (type)
        ) {$charset};" );

        update_option( 'kdna_points_db_version', '1.0' );
    }

    // ─── Points Core ───

    public function get_user_points( $user_id ) {
        global $wpdb;
        $balance = $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(points_balance), 0) FROM {$this->points_table} WHERE user_id = %d",
            $user_id
        ));
        return (int) $balance;
    }

    public function increase_points( $user_id, $points, $type = '', $order_id = 0 ) {
        global $wpdb;
        if ( $points <= 0 ) {
            return;
        }

        $wpdb->insert( $this->points_table, [
            'user_id'        => $user_id,
            'points'         => $points,
            'points_balance' => $points,
            'order_id'       => $order_id ?: null,
            'date'           => current_time( 'mysql', 1 ),
        ]);

        $this->add_log( $user_id, $points, $type, $wpdb->insert_id, $order_id );
    }

    public function decrease_points( $user_id, $points, $type = '', $order_id = 0 ) {
        global $wpdb;
        if ( $points <= 0 ) {
            return;
        }

        $remaining = $points;

        // FIFO: drain oldest balances first
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, points_balance FROM {$this->points_table} WHERE user_id = %d AND points_balance > 0 ORDER BY date ASC",
            $user_id
        ));

        foreach ( $rows as $row ) {
            if ( $remaining <= 0 ) {
                break;
            }
            $deduct = min( $remaining, (int) $row->points_balance );
            $wpdb->update(
                $this->points_table,
                [ 'points_balance' => (int) $row->points_balance - $deduct ],
                [ 'id' => $row->id ]
            );
            $remaining -= $deduct;
        }

        $this->add_log( $user_id, -$points, $type, 0, $order_id );
    }

    private function add_log( $user_id, $points, $type, $points_id = 0, $order_id = 0 ) {
        global $wpdb;
        $wpdb->insert( $this->log_table, [
            'user_id'        => $user_id,
            'points'         => $points,
            'type'           => $type,
            'user_points_id' => $points_id ?: null,
            'order_id'       => $order_id ?: null,
            'date'           => current_time( 'mysql', 1 ),
        ]);
    }

    // ─── Conversion Rates ───

    public function calculate_points( $amount ) {
        $ratio = $this->parse_ratio( $this->settings['earn_ratio'] );
        if ( ! $ratio ) {
            return 0;
        }
        return (int) round( (float) $amount * ( $ratio['points'] / $ratio['monetary'] ) );
    }

    public function calculate_points_value( $points ) {
        $ratio = $this->parse_ratio( $this->settings['redeem_ratio'] );
        if ( ! $ratio ) {
            return 0;
        }
        return round( (float) $points * ( $ratio['monetary'] / $ratio['points'] ), 2 );
    }

    public function calculate_points_for_discount( $discount ) {
        $ratio = $this->parse_ratio( $this->settings['redeem_ratio'] );
        if ( ! $ratio ) {
            return 0;
        }
        return (int) ceil( (float) $discount * ( $ratio['points'] / $ratio['monetary'] ) );
    }

    private function parse_ratio( $ratio_string ) {
        $parts = explode( ':', $ratio_string );
        if ( count( $parts ) !== 2 || (float) $parts[1] <= 0 ) {
            return null;
        }
        return [ 'points' => (float) $parts[0], 'monetary' => (float) $parts[1] ];
    }

    // ─── Order Points ───

    public function get_points_for_product( $product ) {
        $product_points = $product->get_meta( '_kdna_points_earned' );
        if ( $product_points !== '' && $product_points !== false ) {
            return (int) $product_points;
        }
        return $this->calculate_points( (float) $product->get_price() );
    }

    public function get_points_for_cart() {
        $points = 0;
        foreach ( WC()->cart->get_cart() as $item ) {
            $product = $item['data'];
            $points += $this->get_points_for_product( $product ) * $item['quantity'];
        }
        return $points;
    }

    public function award_points_for_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! $order->get_user_id() || $order->get_meta( '_kdna_points_earned' ) ) {
            return;
        }

        $points = 0;
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product ) {
                $points += $this->get_points_for_product( $product ) * $item->get_quantity();
            }
        }

        // Subtract points equivalent of discount
        $discount_total = (float) $order->get_discount_total();
        if ( $discount_total > 0 ) {
            $discount_points = $this->calculate_points( $discount_total );
            $points = max( 0, $points - $discount_points );
        }

        if ( $points > 0 ) {
            $this->increase_points( $order->get_user_id(), $points, 'order-placed', $order_id );
        }

        $order->update_meta_data( '_kdna_points_earned', $points );
        $order->save();
    }

    public function reverse_points_for_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! $order->get_user_id() ) {
            return;
        }

        // Reverse earned points
        $earned = (int) $order->get_meta( '_kdna_points_earned' );
        if ( $earned > 0 ) {
            $this->decrease_points( $order->get_user_id(), $earned, 'order-cancelled', $order_id );
            $order->delete_meta_data( '_kdna_points_earned' );
        }

        // Credit back redeemed points
        $redeemed = (int) $order->get_meta( '_kdna_points_redeemed' );
        if ( $redeemed > 0 ) {
            $this->increase_points( $order->get_user_id(), $redeemed, 'order-cancelled', $order_id );
            $order->delete_meta_data( '_kdna_points_redeemed' );
        }

        $order->save();
    }

    public function handle_partial_refund( $order_id, $refund_id ) {
        $order = wc_get_order( $order_id );
        $refund = wc_get_order( $refund_id );
        if ( ! $order || ! $refund || ! $order->get_user_id() ) {
            return;
        }

        $refund_amount = abs( (float) $refund->get_total() );
        $points_to_remove = $this->calculate_points( $refund_amount );

        if ( $points_to_remove > 0 ) {
            $this->decrease_points( $order->get_user_id(), $points_to_remove, 'order-refunded', $order_id );
        }
    }

    // ─── Discount / Redemption ───

    public function handle_redeem_action() {
        if ( ! isset( $_POST['kdna_redeem_points'] ) || ! is_user_logged_in() ) {
            return;
        }

        check_admin_referer( 'kdna-redeem-points', 'kdna_redeem_nonce' );

        $user_id = get_current_user_id();
        $available_points = $this->get_user_points( $user_id );
        $available_discount = $this->calculate_points_value( $available_points );

        if ( $available_discount <= 0 ) {
            wc_add_notice( __( 'You do not have enough points to redeem.', 'kdna-ecommerce' ), 'error' );
            return;
        }

        $max_discount = $this->get_max_cart_discount();
        $discount = min( $available_discount, $max_discount );

        if ( $discount <= 0 ) {
            return;
        }

        $code = 'kdna_points_' . $user_id . '_' . time();
        WC()->session->set( 'kdna_points_coupon', $code );
        WC()->session->set( 'kdna_points_discount', $discount );
        WC()->session->set( 'kdna_points_to_deduct', $this->calculate_points_for_discount( $discount ) );

        WC()->cart->add_discount( $code );
    }

    private function get_max_cart_discount() {
        $subtotal = (float) WC()->cart->get_subtotal();
        $max = $subtotal;

        if ( ! empty( $this->settings['max_discount'] ) ) {
            $val = $this->settings['max_discount'];
            if ( $this->settings['max_discount_type'] === 'percentage' ) {
                $max = $subtotal * ( (float) $val / 100 );
            } else {
                $max = (float) $val;
            }
        }

        return min( $max, $subtotal );
    }

    public function get_virtual_coupon_data( $data, $code ) {
        $session_code = WC()->session ? WC()->session->get( 'kdna_points_coupon' ) : '';
        if ( $code !== $session_code ) {
            return $data;
        }

        $discount = WC()->session->get( 'kdna_points_discount', 0 );

        return [
            'id'                         => true,
            'amount'                     => $discount,
            'discount_type'              => 'fixed_cart',
            'individual_use'             => false,
            'product_ids'                => [],
            'exclude_product_ids'        => [],
            'usage_limit'                => '',
            'usage_limit_per_user'       => '',
            'limit_usage_to_x_items'     => '',
            'usage_count'                => '',
            'date_expires'               => '',
            'free_shipping'              => false,
            'product_categories'         => [],
            'exclude_product_categories' => [],
            'exclude_sale_items'         => false,
            'minimum_amount'             => '',
            'maximum_amount'             => '',
            'virtual'                    => true,
        ];
    }

    public function coupon_label( $label, $coupon ) {
        $session_code = WC()->session ? WC()->session->get( 'kdna_points_coupon' ) : '';
        if ( $coupon->get_code() === $session_code ) {
            return __( 'Points Discount', 'kdna-ecommerce' );
        }
        return $label;
    }

    // ─── Bonus Points Actions ───

    public function award_signup_points( $user_id ) {
        $points = (int) $this->settings['earn_account_signup'];
        if ( $points > 0 ) {
            $this->increase_points( $user_id, $points, 'account-signup' );
        }
    }

    public function award_review_points( $comment_id, $approved ) {
        if ( $approved !== 1 ) {
            return;
        }
        $this->process_review_points( $comment_id );
    }

    public function award_review_points_on_approval( $comment ) {
        $this->process_review_points( $comment->comment_ID );
    }

    private function process_review_points( $comment_id ) {
        $comment = get_comment( $comment_id );
        if ( ! $comment || ! $comment->user_id || $comment->comment_type !== 'review' ) {
            return;
        }

        // Only award for first review on a product
        if ( get_comment_meta( $comment_id, '_kdna_points_awarded', true ) ) {
            return;
        }

        $existing = get_comments([
            'post_id'  => $comment->comment_post_ID,
            'user_id'  => $comment->user_id,
            'status'   => 'approve',
            'type'     => 'review',
            'count'    => true,
        ]);

        if ( $existing > 1 ) {
            return;
        }

        $points = (int) $this->settings['earn_review'];
        if ( $points > 0 ) {
            $this->increase_points( (int) $comment->user_id, $points, 'product-review' );
            update_comment_meta( $comment_id, '_kdna_points_awarded', $points );
        }
    }

    // ─── Frontend Messages ───

    public function show_product_points_message() {
        global $product;
        if ( ! $product || ! is_user_logged_in() ) {
            return;
        }

        $points = $this->get_points_for_product( $product );
        if ( $points <= 0 ) {
            return;
        }

        $message = str_replace(
            [ '{points}', '{points_label}' ],
            [ $points, $this->get_label( $points ) ],
            $this->settings['product_message']
        );

        echo '<div class="kdna-points-message woocommerce-info">' . wp_kses_post( $message ) . '</div>';
    }

    public function show_cart_messages() {
        if ( ! is_user_logged_in() || ! WC()->cart ) {
            return;
        }

        $user_id = get_current_user_id();
        $points_for_cart = $this->get_points_for_cart();

        // Earn message
        if ( $points_for_cart > 0 ) {
            $message = str_replace(
                [ '{points}', '{points_label}' ],
                [ $points_for_cart, $this->get_label( $points_for_cart ) ],
                $this->settings['cart_message']
            );
            echo '<div class="kdna-points-message woocommerce-info">' . wp_kses_post( $message ) . '</div>';
        }

        // Redeem message
        $user_points = $this->get_user_points( $user_id );
        if ( $user_points > 0 ) {
            $available_discount = min( $this->calculate_points_value( $user_points ), $this->get_max_cart_discount() );
            $points_needed = $this->calculate_points_for_discount( $available_discount );

            if ( $available_discount > 0 ) {
                // Check if already applied
                $session_code = WC()->session ? WC()->session->get( 'kdna_points_coupon' ) : '';
                $applied = $session_code && WC()->cart->has_discount( $session_code );

                if ( ! $applied ) {
                    $message = str_replace(
                        [ '{points}', '{points_label}', '{discount}' ],
                        [ $points_needed, $this->get_label( $points_needed ), wc_price( $available_discount ) ],
                        $this->settings['redeem_message']
                    );
                    echo '<div class="kdna-points-redeem woocommerce-info">';
                    echo wp_kses_post( $message );
                    echo '<form method="post" class="kdna-redeem-form" style="display:inline-block;margin-left:10px;">';
                    wp_nonce_field( 'kdna-redeem-points', 'kdna_redeem_nonce' );
                    echo '<button type="submit" name="kdna_redeem_points" value="1" class="button">' . esc_html__( 'Apply Discount', 'kdna-ecommerce' ) . '</button>';
                    echo '</form></div>';
                }
            }
        }
    }

    public function show_thankyou_message( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $earned = $order->get_meta( '_kdna_points_earned' );
        if ( $earned > 0 ) {
            $message = str_replace(
                [ '{points}', '{points_label}' ],
                [ $earned, $this->get_label( $earned ) ],
                'You have earned <strong>{points}</strong> {points_label} for this order!'
            );
            echo '<div class="kdna-points-message woocommerce-message">' . wp_kses_post( $message ) . '</div>';
        }
    }

    // ─── My Account ───

    public function add_endpoint() {
        add_rewrite_endpoint( 'kdna-points', EP_ROOT | EP_PAGES );
    }

    public function add_menu_item( $items ) {
        $logout = $items['customer-logout'] ?? '';
        unset( $items['customer-logout'] );
        $items['kdna-points'] = $this->get_label( 2 );
        if ( $logout ) {
            $items['customer-logout'] = $logout;
        }
        return $items;
    }

    public function render_my_points() {
        $user_id = get_current_user_id();
        $balance = $this->get_user_points( $user_id );
        $label = $this->get_label( $balance );

        global $wpdb;
        $page = max( 1, absint( get_query_var( 'kdna-points', 1 ) ) );
        $per_page = 15;
        $offset = ( $page - 1 ) * $per_page;

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->log_table} WHERE user_id = %d",
            $user_id
        ));

        $events = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->log_table} WHERE user_id = %d ORDER BY date DESC LIMIT %d OFFSET %d",
            $user_id, $per_page, $offset
        ));

        $type_labels = [
            'order-placed'   => __( 'Purchase', 'kdna-ecommerce' ),
            'order-cancelled'=> __( 'Order Cancelled', 'kdna-ecommerce' ),
            'order-refunded' => __( 'Order Refunded', 'kdna-ecommerce' ),
            'order-redeem'   => __( 'Points Redeemed', 'kdna-ecommerce' ),
            'account-signup' => __( 'Account Signup', 'kdna-ecommerce' ),
            'product-review' => __( 'Product Review', 'kdna-ecommerce' ),
            'expire'         => __( 'Points Expired', 'kdna-ecommerce' ),
            'admin-adjust'   => __( 'Admin Adjustment', 'kdna-ecommerce' ),
        ];

        ?>
        <h2><?php echo esc_html( sprintf( __( 'My %s', 'kdna-ecommerce' ), $this->get_label( 2 ) ) ); ?></h2>
        <p><?php echo wp_kses_post( sprintf( __( 'You have <strong>%d</strong> %s.', 'kdna-ecommerce' ), $balance, $label ) ); ?></p>

        <?php if ( $events ) : ?>
        <table class="woocommerce-table shop_table kdna-points-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Event', 'kdna-ecommerce' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'kdna-ecommerce' ); ?></th>
                    <th><?php esc_html_e( 'Points', 'kdna-ecommerce' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $events as $event ) : ?>
                <tr>
                    <td><?php echo esc_html( $type_labels[ $event->type ] ?? ucwords( str_replace( '-', ' ', $event->type ) ) ); ?></td>
                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->date ) ) ); ?></td>
                    <td><?php echo (int) $event->points > 0 ? '+' . (int) $event->points : (int) $event->points; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
            $total_pages = ceil( $total / $per_page );
            if ( $total_pages > 1 ) {
                echo '<div class="woocommerce-pagination">';
                if ( $page > 1 ) {
                    echo '<a class="button" href="' . esc_url( wc_get_endpoint_url( 'kdna-points', $page - 1 ) ) . '">' . esc_html__( 'Previous', 'kdna-ecommerce' ) . '</a> ';
                }
                if ( $page < $total_pages ) {
                    echo '<a class="button" href="' . esc_url( wc_get_endpoint_url( 'kdna-points', $page + 1 ) ) . '">' . esc_html__( 'Next', 'kdna-ecommerce' ) . '</a>';
                }
                echo '</div>';
            }
        endif;
    }

    // ─── Expiry ───

    public function expire_points() {
        global $wpdb;

        $months = (int) $this->settings['expiry_period'];
        if ( $months <= 0 ) {
            return;
        }

        $expire_before = gmdate( 'Y-m-d H:i:s', strtotime( "-{$months} months" ) );

        $expiring = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->points_table} WHERE date < %s AND points_balance > 0",
            $expire_before
        ));

        foreach ( $expiring as $row ) {
            $wpdb->update( $this->points_table, [ 'points_balance' => 0 ], [ 'id' => $row->id ] );
            $this->add_log( (int) $row->user_id, -(int) $row->points_balance, 'expire', (int) $row->id );
        }
    }

    // ─── Frontend Assets ───

    public function enqueue_frontend_assets() {
        if ( ! is_product() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
            return;
        }
        wp_enqueue_style( 'kdna-points-frontend', KDNA_ECOMMERCE_URL . 'assets/css/points-rewards.css', [], KDNA_ECOMMERCE_VERSION );
    }

    // ─── Helpers ───

    public function get_label( $count = 1 ) {
        return (int) $count === 1 ? $this->settings['points_label_singular'] : $this->settings['points_label_plural'];
    }

    public function get_settings() {
        return $this->settings;
    }
}
