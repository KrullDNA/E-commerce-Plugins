<?php
defined( 'ABSPATH' ) || exit;

class KDNA_Sequential_Orders {

    private $settings;

    public function __construct() {
        $this->settings = wp_parse_args( get_option( 'kdna_sequential_settings', [] ), [
            'start_number'      => '1',
            'prefix'            => '',
            'suffix'            => '',
            'skip_free_orders'  => 'no',
            'free_prefix'       => 'FREE-',
            'free_start_number' => '1',
        ]);

        // Set order number on creation
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'set_order_number' ], 10, 1 );
        add_action( 'woocommerce_process_shop_order_meta', [ $this, 'set_order_number_admin' ], 35, 1 );
        add_action( 'woocommerce_order_status_changed', [ $this, 'set_order_number_on_status_change' ], -1, 3 );

        // Display formatted order number
        add_filter( 'woocommerce_order_number', [ $this, 'display_order_number' ], 10, 2 );

        // Order search by custom number
        add_filter( 'woocommerce_order_table_search_query_meta_keys', [ $this, 'add_search_meta_keys' ] );
        add_filter( 'woocommerce_shop_order_search_fields', [ $this, 'add_search_fields' ] );

        // Order tracking
        add_filter( 'woocommerce_shortcode_order_tracking_order_id', [ $this, 'find_order_by_number' ] );
    }

    public function set_order_number( $order_id ) {
        $this->maybe_set_number( $order_id );
    }

    public function set_order_number_admin( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_meta( '_kdna_order_number' ) ) {
            return;
        }
        $this->maybe_set_number( $order_id );
    }

    public function set_order_number_on_status_change( $order_id, $old_status, $new_status ) {
        $skip_statuses = [ 'auto-draft', 'checkout-draft' ];
        if ( in_array( $new_status, $skip_statuses, true ) ) {
            return;
        }
        $this->maybe_set_number( $order_id );
    }

    private function maybe_set_number( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Check if already assigned (direct DB query to bypass cache)
        if ( $order->get_meta( '_kdna_order_number' ) ) {
            return;
        }

        $is_free = $this->settings['skip_free_orders'] === 'yes' && (float) $order->get_total() === 0.0;

        if ( $is_free ) {
            $number = $this->generate_next_number( 'kdna_free_order_number_current', (int) $this->settings['free_start_number'] );
            $formatted = $this->format_number( $number, $this->settings['free_prefix'], '', 0 );
            $order->update_meta_data( '_kdna_order_number', -1 );
            $order->update_meta_data( '_kdna_order_number_free', $number );
        } else {
            $start = $this->settings['start_number'];
            $padding = strlen( $start );
            $start_int = (int) ltrim( $start, '0' ) ?: 1;

            $number = $this->generate_next_number( 'kdna_order_number_current', $start_int );
            $formatted = $this->format_number( $number, $this->settings['prefix'], $this->settings['suffix'], $padding );
            $order->update_meta_data( '_kdna_order_number', $number );
        }

        $order->update_meta_data( '_kdna_order_number_formatted', $formatted );
        $order->save();
    }

    private function generate_next_number( $option_key, $start ) {
        global $wpdb;

        // Atomic increment using wp_options
        $current = get_option( $option_key );
        if ( false === $current ) {
            add_option( $option_key, $start );
            return $start;
        }

        $next = max( (int) $current + 1, $start );

        // Atomic update to prevent race conditions
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %d WHERE option_name = %s AND option_value = %s",
            $next, $option_key, $current
        ));

        if ( ! $updated ) {
            // Retry on conflict
            $current = get_option( $option_key );
            $next = max( (int) $current + 1, $start );
            update_option( $option_key, $next );
        }

        return $next;
    }

    private function format_number( $number, $prefix, $suffix, $padding ) {
        $padded = $padding > 0 ? sprintf( "%0{$padding}d", $number ) : (string) $number;
        $formatted = $prefix . $padded . $suffix;
        return $this->replace_date_patterns( $formatted );
    }

    private function replace_date_patterns( $string ) {
        $now = current_time( 'timestamp' );
        $replacements = [
            '{YYYY}' => date( 'Y', $now ),
            '{YY}'   => date( 'y', $now ),
            '{MM}'   => date( 'm', $now ),
            '{M}'    => date( 'n', $now ),
            '{DD}'   => date( 'd', $now ),
            '{D}'    => date( 'j', $now ),
            '{HH}'   => date( 'H', $now ),
            '{H}'    => date( 'G', $now ),
            '{N}'    => date( 'i', $now ),
            '{S}'    => date( 's', $now ),
        ];
        return str_ireplace( array_keys( $replacements ), array_values( $replacements ), $string );
    }

    public function display_order_number( $order_number, $order ) {
        $formatted = $order->get_meta( '_kdna_order_number_formatted' );
        return $formatted ?: $order_number;
    }

    public function add_search_meta_keys( $meta_keys ) {
        $meta_keys[] = '_kdna_order_number_formatted';
        return $meta_keys;
    }

    public function add_search_fields( $fields ) {
        $fields[] = '_kdna_order_number_formatted';
        return $fields;
    }

    public function find_order_by_number( $order_id ) {
        $orders = wc_get_orders([
            'meta_key'   => '_kdna_order_number_formatted',
            'meta_value' => sanitize_text_field( $order_id ),
            'limit'      => 1,
            'return'     => 'ids',
        ]);

        return ! empty( $orders ) ? $orders[0] : $order_id;
    }
}
