<?php
/**
 * KDNA Australia Post Shipping Method
 *
 * Extends WC_Shipping_Method to provide real-time Australia Post rates.
 */
defined( 'ABSPATH' ) || exit;

class KDNA_Shipping_Australia_Post extends WC_Shipping_Method {

	private $default_api_key = 'smUZLviAk6JIkIHvL0xgzP1yToSu7iQJ';
	private $max_weight;
	public $services;
	public $extra_cover;
	public $delivery_confirmation;
	public $default_boxes;
	public $custom_services;

	private $endpoints = array(
		'calculation' => 'https://digitalapi.auspost.com.au/api/postage/{type}/{doi}/calculate.json',
		'services'    => 'https://digitalapi.auspost.com.au/api/postage/{type}/{doi}/service.json',
	);

	private $au_territories = array( 'AU', 'CC', 'CX', 'HM', 'NF' );
	private $sod_cost       = 2.95;
	private $int_sod_cost   = 5.50;
	private $found_rates;
	private $is_international = false;
	private $letter_sizes;
	private $packing_method;
	private $excluding_tax;
	private $origin;
	private $api_key;
	private $boxes;
	private $offer_rates;
	private $satchel_rates;
	private $debug_mode;

	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );

		$this->id                 = 'kdna_australia_post';
		$this->method_title       = __( 'Australia Post', 'kdna-ecommerce' );
		$this->method_description = __( 'Obtains live shipping rates from the Australia Post API during cart/checkout.', 'kdna-ecommerce' );
		$this->supports           = array( 'shipping-zones', 'instance-settings', 'settings' );
		$this->init();
	}

	private function init() {
		$this->init_form_fields();
		$this->set_settings();

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'clear_transients' ) );
	}

	private function set_settings() {
		$this->title           = $this->get_option( 'title', $this->method_title );
		$this->excluding_tax   = $this->get_option( 'excluding_tax', 'no' );
		$this->tax_status      = wc_string_to_bool( $this->excluding_tax ) ? 'taxable' : 'none';
		$this->origin          = $this->get_option( 'origin', '' );
		$this->api_key         = $this->get_option( 'api_key', '' );
		$this->packing_method  = $this->get_option( 'packing_method', 'per_item' );
		$this->boxes           = $this->get_option( 'boxes', array() );
		$this->custom_services = $this->get_option( 'services', array() );
		$this->offer_rates     = $this->get_option( 'offer_rates', 'all' );
		$this->debug_mode      = 'yes' === $this->get_option( 'debug_mode' );
		$this->satchel_rates   = $this->get_option( 'satchel_rates', 'off' );
		$this->max_weight      = floatval( $this->get_option( 'max_weight', '20' ) );

		$data_path                   = __DIR__ . '/data/';
		$this->services              = include $data_path . 'data-services.php';
		$this->extra_cover           = include $data_path . 'data-extra-cover.php';
		$this->delivery_confirmation = include $data_path . 'data-sod.php';
		$this->default_boxes         = include $data_path . 'data-box-sizes.php';
		$this->letter_sizes          = include $data_path . 'data-letter-sizes.php';

		if ( empty( $this->api_key ) ) {
			$this->api_key = $this->default_api_key;
		}
	}

	public function is_available( $package ) {
		if ( empty( $package['destination']['country'] ) ) {
			return false;
		}
		return apply_filters( 'kdna_shipping_australia_post_is_available', true, $package );
	}

	public function process_admin_options() {
		parent::process_admin_options();
		$this->set_settings();
	}

	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title'          => array(
				'title'       => __( 'Method Title', 'kdna-ecommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'kdna-ecommerce' ),
				'default'     => __( 'Australia Post', 'kdna-ecommerce' ),
			),
			'origin'         => array(
				'title'       => __( 'Origin Postcode', 'kdna-ecommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter the postcode for the <strong>sender</strong>.', 'kdna-ecommerce' ),
				'default'     => '',
			),
			'excluding_tax'  => array(
				'title'       => __( 'Tax', 'kdna-ecommerce' ),
				'label'       => __( 'Calculate Rates Excluding Tax', 'kdna-ecommerce' ),
				'type'        => 'checkbox',
				'description' => __( "Calculate shipping rates excluding tax. By default rates returned by the Australia Post API include tax.", 'kdna-ecommerce' ),
				'default'     => 'no',
			),
			'packing_method' => array(
				'title'   => __( 'Parcel Packing Method', 'kdna-ecommerce' ),
				'type'    => 'select',
				'default' => 'per_item',
				'options' => array(
					'per_item' => __( 'Default: Pack items individually', 'kdna-ecommerce' ),
					'weight'   => __( 'Weight of all items', 'kdna-ecommerce' ),
					'box_packing' => __( 'Recommended: Pack into boxes with weights and dimensions', 'kdna-ecommerce' ),
				),
			),
			'max_weight'     => array(
				'title'       => __( 'Maximum weight (kg)', 'kdna-ecommerce' ),
				'type'        => 'text',
				'default'     => '20',
				'description' => __( 'Maximum weight per package in kg.', 'kdna-ecommerce' ),
			),
			'boxes'          => array(
				'type' => 'box_packing',
			),
			'satchel_rates'  => array(
				'title'   => __( 'Satchel Rates', 'kdna-ecommerce' ),
				'type'    => 'select',
				'options' => array(
					'on'       => __( 'Enable Satchel Rates', 'kdna-ecommerce' ),
					'priority' => __( 'Prioritise Satchel Rates', 'kdna-ecommerce' ),
					'off'      => __( 'Disable Satchel Rates', 'kdna-ecommerce' ),
				),
				'default' => 'off',
			),
			'offer_rates'    => array(
				'title'   => __( 'Offer Rates', 'kdna-ecommerce' ),
				'type'    => 'select',
				'default' => 'all',
				'options' => array(
					'all'      => __( 'Offer the customer all returned rates', 'kdna-ecommerce' ),
					'cheapest' => __( 'Offer the customer the cheapest rate only', 'kdna-ecommerce' ),
				),
			),
			'services'       => array(
				'type' => 'services',
			),
		);

		$this->form_fields = array(
			'api'        => array(
				'title'       => __( 'API Settings', 'kdna-ecommerce' ),
				'type'        => 'title',
				'description' => __( 'Your API key is obtained from the <a href="https://developers.auspost.com.au/apis/pacpcs-registration">Australia Post website</a>. Leave blank to use the default key.', 'kdna-ecommerce' ),
			),
			'api_key'    => array(
				'title'       => __( 'API Key', 'kdna-ecommerce' ),
				'type'        => 'text',
				'description' => __( 'Leave blank to use the default API Key.', 'kdna-ecommerce' ),
				'default'     => '',
				'placeholder' => $this->default_api_key,
			),
			'debug_mode' => array(
				'title'       => __( 'Debug Mode', 'kdna-ecommerce' ),
				'label'       => __( 'Enable debug mode', 'kdna-ecommerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'description' => __( 'Enable debug mode to show debugging information on your cart/checkout.', 'kdna-ecommerce' ),
			),
		);
	}

	// --- Custom field renderers ---

	public function generate_services_html() {
		ob_start();
		$shipping_method = $this;
		include __DIR__ . '/views/html-services.php';
		return ob_get_clean();
	}

	public function generate_box_packing_html() {
		ob_start();
		$shipping_method = $this;
		include __DIR__ . '/views/html-box-packing.php';
		return ob_get_clean();
	}

	public function validate_box_packing_field( $key, $value ) {
		$boxes = array();

		if ( isset( $_POST['boxes_outer_length'] ) ) {
			$boxes_name         = isset( $_POST['boxes_name'] ) ? wc_clean( wp_unslash( $_POST['boxes_name'] ) ) : array();
			$boxes_outer_length = isset( $_POST['boxes_outer_length'] ) ? wc_clean( wp_unslash( $_POST['boxes_outer_length'] ) ) : array();
			$boxes_outer_width  = isset( $_POST['boxes_outer_width'] ) ? wc_clean( wp_unslash( $_POST['boxes_outer_width'] ) ) : array();
			$boxes_outer_height = isset( $_POST['boxes_outer_height'] ) ? wc_clean( wp_unslash( $_POST['boxes_outer_height'] ) ) : array();
			$boxes_inner_length = isset( $_POST['boxes_inner_length'] ) ? wc_clean( wp_unslash( $_POST['boxes_inner_length'] ) ) : array();
			$boxes_inner_width  = isset( $_POST['boxes_inner_width'] ) ? wc_clean( wp_unslash( $_POST['boxes_inner_width'] ) ) : array();
			$boxes_inner_height = isset( $_POST['boxes_inner_height'] ) ? wc_clean( wp_unslash( $_POST['boxes_inner_height'] ) ) : array();
			$boxes_box_weight   = isset( $_POST['boxes_box_weight'] ) ? wc_clean( wp_unslash( $_POST['boxes_box_weight'] ) ) : array();
			$boxes_max_weight   = isset( $_POST['boxes_max_weight'] ) ? wc_clean( wp_unslash( $_POST['boxes_max_weight'] ) ) : array();
			$boxes_type         = isset( $_POST['boxes_type'] ) ? wc_clean( wp_unslash( $_POST['boxes_type'] ) ) : array();
			$boxes_enabled      = isset( $_POST['boxes_enabled'] ) ? wc_clean( wp_unslash( $_POST['boxes_enabled'] ) ) : array();

			$boxes_count         = count( $boxes_outer_length );
			$default_boxes_count = count( $this->default_boxes );

			for ( $i = 0; $i < $boxes_count; $i++ ) {
				if ( $i < $default_boxes_count ) {
					$boxes[] = array(
						'enabled' => isset( $boxes_enabled[ $i ] ),
						'id'      => $this->default_boxes[ $i ]['id'],
					);
				} elseif ( $boxes_outer_length[ $i ] && $boxes_outer_width[ $i ] && $boxes_outer_height[ $i ] && $boxes_inner_length[ $i ] && $boxes_inner_width[ $i ] && $boxes_inner_height[ $i ] ) {
					$boxes[] = array(
						'name'         => sanitize_text_field( $boxes_name[ $i ] ),
						'outer_length' => floatval( $boxes_outer_length[ $i ] ),
						'outer_width'  => floatval( $boxes_outer_width[ $i ] ),
						'outer_height' => floatval( $boxes_outer_height[ $i ] ),
						'inner_length' => floatval( $boxes_inner_length[ $i ] ),
						'inner_width'  => floatval( $boxes_inner_width[ $i ] ),
						'inner_height' => floatval( $boxes_inner_height[ $i ] ),
						'box_weight'   => floatval( $boxes_box_weight[ $i ] ),
						'max_weight'   => floatval( $boxes_max_weight[ $i ] ),
						'type'         => in_array( $boxes_type[ $i ], array( 'box', 'tube', 'envelope', 'packet' ), true ) ? $boxes_type[ $i ] : 'box',
						'enabled'      => isset( $boxes_enabled[ $i ] ),
					);
				}
			}
		}

		return $boxes;
	}

	public function validate_services_field( $key ) {
		if ( ! isset( $_POST['kdna_auspost_service'] ) || ! is_array( $_POST['kdna_auspost_service'] ) ) {
			return array();
		}

		$posted_services = wc_clean( wp_unslash( $_POST['kdna_auspost_service'] ) );
		$services        = array();

		foreach ( $posted_services as $code => $settings ) {
			$services[ $code ] = array(
				'name'                  => isset( $settings['name'] ) ? $settings['name'] : '',
				'order'                 => isset( $settings['order'] ) ? $settings['order'] : '',
				'enabled'               => isset( $settings['enabled'] ),
				'adjustment'            => isset( $settings['adjustment'] ) ? floatval( $settings['adjustment'] ) : '',
				'adjustment_percent'    => isset( $settings['adjustment_percent'] ) ? floatval( str_replace( '%', '', $settings['adjustment_percent'] ) ) : '',
				'extra_cover'           => isset( $settings['extra_cover'] ),
				'delivery_confirmation' => isset( $settings['delivery_confirmation'] ),
			);
		}

		return $services;
	}

	// --- Box helpers ---

	public function get_all_boxes() {
		$enabled_boxes = array();
		foreach ( $this->boxes as $key => &$box ) {
			if ( isset( $box['id'] ) && isset( $box['enabled'] ) ) {
				$enabled_boxes[ $box['id'] ] = $box['enabled'];
				unset( $this->boxes[ $key ] );
			}
			if ( empty( $box['type'] ) && ! empty( $box['is_letter'] ) ) {
				$box['type'] = 'envelope';
			} elseif ( empty( $box['type'] ) ) {
				$box['type'] = 'box';
			}
			if ( empty( $box['name'] ) ) {
				$box['name'] = 'Box ' . ( $key + 1 );
			}
		}

		foreach ( $this->default_boxes as &$box ) {
			if ( isset( $enabled_boxes[ $box['id'] ] ) ) {
				$box['enabled'] = $enabled_boxes[ $box['id'] ];
			} else {
				$box['enabled'] = true;
			}
		}
		return array_merge( $this->default_boxes, $this->boxes );
	}

	// --- Transient cache ---

	public function clear_transients() {
		global $wpdb;
		$wpdb->query( "DELETE FROM `$wpdb->options` WHERE `option_name` LIKE ('_transient_kdna_auspost_quotes_%') OR `option_name` LIKE ('_transient_timeout_kdna_auspost_quotes_%')" );
	}

	// --- Rate calculation ---

	public function calculate_shipping( $package = array() ) {
		$this->found_rates      = array();
		$this->is_international = $this->is_international( $package );
		$headers                = array( 'AUTH-KEY' => $this->api_key );
		$package_requests       = $this->get_package_requests( $package );

		$letter_services_endpoint = str_replace(
			array( '{type}', '{doi}' ),
			array( 'letter', ( $this->is_international ? 'international' : 'domestic' ) ),
			$this->endpoints['services']
		);

		$services_endpoint = str_replace(
			array( '{type}', '{doi}' ),
			array( 'parcel', ( $this->is_international ? 'international' : 'domestic' ) ),
			$this->endpoints['services']
		);

		$to_postcode   = $this->get_to_postcode( $package );
		$from_postcode = $this->get_from_postcode();

		if ( empty( $to_postcode ) && ! $this->is_international ) {
			return;
		}
		if ( empty( $from_postcode ) ) {
			return;
		}

		if ( $package_requests ) {
			foreach ( $package_requests as $package_request ) {
				// Handle satchel dimension adjustments.
				$exact_dimensions = isset( $package_request['exact_dimensions'] ) ? $package_request['exact_dimensions'] : array();
				unset( $package_request['exact_dimensions'] );

				// Build the request query string.
				$base_request = array(
					'from_postcode' => $from_postcode,
					'country_code'  => $package['destination']['country'],
				);
				if ( ! empty( $to_postcode ) ) {
					$base_request['to_postcode'] = $to_postcode;
				}

				$request = http_build_query( array_merge( $package_request, $base_request ), '', '&' );

				if ( isset( $package_request['thickness'] ) ) {
					$response = $this->get_response( $letter_services_endpoint, $request, $headers );
				} else {
					$response = $this->get_response( $services_endpoint, $request, $headers );
				}

				if ( isset( $response->services->service ) && is_object( $response->services->service ) ) {
					$response->services->service = array( $response->services->service );
				}

				if ( isset( $response->services->service ) && is_array( $response->services->service ) ) {
					foreach ( $this->services as $service => $values ) {
						$rate_code            = (string) $service;
						$rate_id              = $this->id . ':' . $rate_code;
						$rate_name            = (string) $values['name'];
						$rate_cost            = null;
						$optional_extras_cost = 0;

						foreach ( $response->services->service as $quote ) {
							if ( ( isset( $values['alternate_services'] ) && in_array( $quote->code, $values['alternate_services'], true ) ) || $service === $quote->code ) {

								$delivery_confirmation = false;
								$rate_set              = false;

								if ( $this->is_satchel( $quote->code ) && 'off' === $this->satchel_rates ) {
									continue;
								}

								if ( $this->is_satchel( $quote->code ) ) {
									if ( 'priority' === $this->satchel_rates ) {
										$rate_cost = $quote->price;
										$rate_set  = true;
									}
									if ( ! empty( $this->custom_services[ $rate_code ]['delivery_confirmation'] ) ) {
										$delivery_confirmation = true;
									}
								} elseif ( ! empty( $this->custom_services[ $rate_code ]['delivery_confirmation'] ) ) {
									$delivery_confirmation = true;
								}

								// SOD required when extra cover + value >= $300 (not for Courier).
								if ( ! $this->is_courier_post( $quote->code ) && ! empty( $this->custom_services[ $rate_code ]['extra_cover'] ) && isset( $package_request['extra_cover'] ) && $package_request['extra_cover'] >= 300 ) {
									$delivery_confirmation = true;
								}

								if ( is_null( $rate_cost ) ) {
									$rate_cost = $quote->price;
									$rate_set  = true;
								} elseif ( $quote->price < $rate_cost ) {
									$rate_cost = $quote->price;
									$rate_set  = true;
								}

								if ( $rate_set ) {
									$optional_extras_cost = 0;

									// Extra cover cost.
									if ( ! empty( $this->custom_services[ $rate_code ]['extra_cover'] ) && isset( $package_request['extra_cover'] ) && isset( $quote->max_extra_cover ) ) {
										$max_extra_cover       = $quote->max_extra_cover;
										$optional_extras_cost += $this->calculate_extra_cover_cost( $package_request['extra_cover'], $max_extra_cover );
									}

									// SOD cost.
									if ( $delivery_confirmation ) {
										$optional_extras_cost += $this->is_international ? $this->int_sod_cost : $this->sod_cost;
									}
								}
							}
						}

						if ( $rate_cost ) {
							$rate_cost += $optional_extras_cost;
							$this->prepare_rate( $rate_code, $rate_id, $rate_name, $rate_cost, $package_request );
						}
					}
				}
			}
		}

		// Ensure rates were found for all packages.
		if ( $this->found_rates ) {
			foreach ( $this->found_rates as $key => $value ) {
				if ( $value['packages'] < count( $package_requests ) ) {
					unset( $this->found_rates[ $key ] );
				}
			}
		}

		if ( $this->found_rates ) {
			if ( 'all' === $this->offer_rates ) {
				uasort( $this->found_rates, array( $this, 'sort_rates' ) );
				foreach ( $this->found_rates as $rate ) {
					$this->add_rate( $rate );
				}
			} else {
				$cheapest_rate = array( 'cost' => PHP_INT_MAX );
				foreach ( $this->found_rates as $rate ) {
					if ( $cheapest_rate['cost'] > $rate['cost'] ) {
						$cheapest_rate = $rate;
					}
				}
				$cheapest_rate['label'] = $this->title;
				$this->add_rate( $cheapest_rate );
			}
		}
	}

	public function calculate_extra_cover_cost( $item_value, $max_extra_cover ) {
		$extra_cover_cost = 2.50;

		if ( empty( $item_value ) || 100 >= $item_value ) {
			return 0;
		}

		if ( $item_value > $max_extra_cover ) {
			$item_value = $max_extra_cover;
		}

		if ( $this->is_international ) {
			$extra_cover_cost = 4.00;
		}

		return $extra_cover_cost * ( ceil( ( $item_value - 100 ) / 100 ) );
	}

	private function is_satchel( $code ) {
		return is_string( $code ) && strpos( $code, '_SATCHEL_' ) !== false;
	}

	private function is_courier_post( $code ) {
		return is_string( $code ) && strpos( $code, 'COURIER' ) !== false;
	}

	public function is_international( $package ) {
		return ! in_array( $package['destination']['country'], $this->au_territories, true );
	}

	private function prepare_rate( $rate_code, $rate_id, $rate_name, $rate_cost, $package_request = '' ) {
		// Custom name.
		if ( ! empty( $this->custom_services[ $rate_code ]['name'] ) ) {
			$rate_name = $this->custom_services[ $rate_code ]['name'];
		}

		// Cost adjustments.
		if ( ! empty( $this->custom_services[ $rate_code ]['adjustment_percent'] ) ) {
			$rate_cost += $rate_cost * ( floatval( $this->custom_services[ $rate_code ]['adjustment_percent'] ) / 100 );
		}
		if ( ! empty( $this->custom_services[ $rate_code ]['adjustment'] ) ) {
			$rate_cost += floatval( $this->custom_services[ $rate_code ]['adjustment'] );
		}

		// Exclude tax.
		if ( 'yes' === $this->excluding_tax && ! $this->is_international ) {
			$tax_rate  = apply_filters( 'kdna_shipping_australia_post_tax_rate', 0.10 );
			$rate_cost = $rate_cost / ( $tax_rate + 1 );
		}

		// Enabled check.
		if ( isset( $this->custom_services[ $rate_code ] ) && empty( $this->custom_services[ $rate_code ]['enabled'] ) ) {
			return;
		}

		// Merge multi-package costs.
		if ( isset( $this->found_rates[ $rate_id ] ) ) {
			$rate_cost += $this->found_rates[ $rate_id ]['cost'];
			$packages   = 1 + $this->found_rates[ $rate_id ]['packages'];
		} else {
			$packages = 1;
		}

		$sort = isset( $this->custom_services[ $rate_code ]['order'] ) ? $this->custom_services[ $rate_code ]['order'] : 999;

		$this->found_rates[ $rate_id ] = array(
			'id'       => $rate_id,
			'label'    => $rate_name,
			'cost'     => $rate_cost,
			'sort'     => $sort,
			'packages' => $packages,
		);
	}

	private function get_response( $endpoint, $request, $headers ) {
		$transient_key = 'kdna_auspost_quotes_' . md5( $request );
		$response      = get_transient( $transient_key );

		if ( $response ) {
			return $response;
		}

		$response = wp_remote_get(
			$endpoint . '?' . $request,
			array(
				'timeout' => 70,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = json_decode( $response['body'] );
		if ( is_null( $response ) ) {
			return false;
		}

		set_transient( $transient_key, $response, WEEK_IN_SECONDS );
		return $response;
	}

	public function sort_rates( $a, $b ) {
		if ( $a['sort'] === $b['sort'] ) {
			return 0;
		}
		return ( $a['sort'] < $b['sort'] ) ? -1 : 1;
	}

	private function get_from_postcode() {
		return str_replace( ' ', '', strtoupper( $this->origin ) );
	}

	private function get_to_postcode( $package ) {
		if ( in_array( $package['destination']['country'], $this->au_territories, true ) ) {
			return str_replace( ' ', '', strtoupper( $package['destination']['postcode'] ) );
		}
		return $package['destination']['postcode'];
	}

	private function get_package_requests( $package ) {
		switch ( $this->packing_method ) {
			case 'weight':
				return $this->weight_only_shipping( $package );
			case 'box_packing':
				return $this->box_shipping( $package );
			case 'per_item':
			default:
				return $this->per_item_shipping( $package );
		}
	}

	private function weight_only_shipping( $package ) {
		$total_weight = 0;
		$total_value  = 0;

		foreach ( $package['contents'] as $values ) {
			$product = $values['data'];
			if ( ! $product->needs_shipping() ) {
				continue;
			}
			if ( ! $product->get_weight() ) {
				return array();
			}
			$weight        = wc_get_weight( $product->get_weight(), 'kg' );
			$total_weight += $weight * $values['quantity'];
			$total_value  += $product->get_price() * $values['quantity'];
		}

		if ( $total_weight <= 0 ) {
			return array();
		}

		$requests = array();
		$packages_needed = ceil( $total_weight / $this->max_weight );

		for ( $i = 0; $i < $packages_needed; $i++ ) {
			$pkg_weight = ( $i < $packages_needed - 1 ) ? $this->max_weight : fmod( $total_weight, $this->max_weight );
			if ( $pkg_weight == 0 ) {
				$pkg_weight = $this->max_weight;
			}

			$requests[] = array(
				'height'      => 1,
				'width'       => 1,
				'length'      => 1,
				'weight'      => round( $pkg_weight, 2 ),
				'extra_cover' => ceil( $total_value / $packages_needed ),
			);
		}

		return $requests;
	}

	private function per_item_shipping( $package ) {
		$requests = array();

		foreach ( $package['contents'] as $values ) {
			$product = $values['data'];

			if ( ! $product->needs_shipping() ) {
				continue;
			}

			if ( ! $product->get_weight() || ! $product->get_length() || ! $product->get_height() || ! $product->get_width() ) {
				return array();
			}

			$dimensions = array(
				wc_get_dimension( $product->get_length(), 'cm' ),
				wc_get_dimension( $product->get_height(), 'cm' ),
				wc_get_dimension( $product->get_width(), 'cm' ),
			);
			sort( $dimensions );

			// Min girth = 16cm.
			$girth = $dimensions[0] + $dimensions[0] + $dimensions[1] + $dimensions[1];
			if ( $girth < 16 ) {
				if ( $dimensions[0] < 4 ) {
					$dimensions[0] = 4;
				}
				if ( $dimensions[1] < 5 ) {
					$dimensions[1] = 5;
				}
			}

			$weight = wc_get_weight( $product->get_weight(), 'kg' );

			if ( $weight > 22 || $dimensions[2] > 105 ) {
				return array();
			}

			$parcel = array(
				'height'      => $dimensions[0],
				'width'       => $dimensions[1],
				'length'      => $dimensions[2],
				'weight'      => $weight,
				'extra_cover' => ceil( $product->get_price() ),
			);

			for ( $i = 0; $i < $values['quantity']; $i++ ) {
				$requests[] = $parcel;
			}
		}

		return $requests;
	}

	private function box_shipping( $package ) {
		$requests = array();

		// Simple volume-based box packing: gather all items and fit them into defined boxes.
		$all_items  = array();
		$all_boxes  = $this->get_all_boxes();

		foreach ( $package['contents'] as $values ) {
			$product = $values['data'];
			if ( ! $product->needs_shipping() ) {
				continue;
			}
			if ( ! $product->get_weight() || ! $product->get_length() || ! $product->get_height() || ! $product->get_width() ) {
				return array();
			}

			for ( $i = 0; $i < $values['quantity']; $i++ ) {
				$dims = array(
					wc_get_dimension( $product->get_width(), 'cm' ),
					wc_get_dimension( $product->get_height(), 'cm' ),
					wc_get_dimension( $product->get_length(), 'cm' ),
				);
				sort( $dims );

				$all_items[] = array(
					'width'  => $dims[0],
					'height' => $dims[1],
					'length' => $dims[2],
					'weight' => wc_get_weight( $product->get_weight(), 'kg' ),
					'volume' => $dims[0] * $dims[1] * $dims[2],
					'price'  => $product->get_price(),
				);
			}
		}

		if ( empty( $all_items ) ) {
			return array();
		}

		// Sort items by volume descending for better packing.
		usort( $all_items, function( $a, $b ) {
			return $b['volume'] <=> $a['volume'];
		} );

		// Filter and sort boxes by volume ascending.
		$enabled_boxes = array();
		foreach ( $all_boxes as $box ) {
			if ( isset( $box['enabled'] ) && ! $box['enabled'] ) {
				continue;
			}
			$inner_l = ! empty( $box['inner_length'] ) ? $box['inner_length'] : $box['outer_length'];
			$inner_w = ! empty( $box['inner_width'] ) ? $box['inner_width'] : $box['outer_width'];
			$inner_h = ! empty( $box['inner_height'] ) ? $box['inner_height'] : $box['outer_height'];
			$box['inner_volume'] = $inner_l * $inner_w * $inner_h;
			$box['max_weight']   = ! empty( $box['max_weight'] ) ? $box['max_weight'] : 22;
			$enabled_boxes[]     = $box;
		}
		usort( $enabled_boxes, function( $a, $b ) {
			return $a['inner_volume'] <=> $b['inner_volume'];
		} );

		// Simple first-fit-decreasing bin packing.
		$packed_boxes = array();
		$remaining    = $all_items;

		while ( ! empty( $remaining ) ) {
			$item  = array_shift( $remaining );
			$placed = false;

			// Try to fit in an existing packed box.
			foreach ( $packed_boxes as &$pbox ) {
				if ( $pbox['remaining_volume'] >= $item['volume'] && ( $pbox['current_weight'] + $item['weight'] ) <= $pbox['max_weight'] ) {
					$pbox['remaining_volume'] -= $item['volume'];
					$pbox['current_weight']   += $item['weight'];
					$pbox['total_value']      += $item['price'];
					$placed = true;
					break;
				}
			}
			unset( $pbox );

			if ( ! $placed ) {
				// Find smallest box that fits.
				$best_box = null;
				foreach ( $enabled_boxes as $box ) {
					if ( $box['inner_volume'] >= $item['volume'] && $box['max_weight'] >= $item['weight'] ) {
						$best_box = $box;
						break;
					}
				}

				if ( $best_box ) {
					$packed_boxes[] = array(
						'box'              => $best_box,
						'remaining_volume' => $best_box['inner_volume'] - $item['volume'],
						'current_weight'   => ( ! empty( $best_box['box_weight'] ) ? $best_box['box_weight'] : 0 ) + $item['weight'],
						'max_weight'       => $best_box['max_weight'],
						'total_value'      => $item['price'],
					);
				} else {
					// Item doesn't fit any box — pack individually.
					$dimensions = array( $item['width'], $item['height'], $item['length'] );
					sort( $dimensions );
					$requests[] = array(
						'height'      => $dimensions[0],
						'width'       => $dimensions[1],
						'length'      => $dimensions[2],
						'weight'      => $item['weight'],
						'extra_cover' => ceil( $item['price'] ),
					);
				}
			}
		}

		// Convert packed boxes to request format.
		foreach ( $packed_boxes as $pbox ) {
			$box = $pbox['box'];
			$dimensions = array( $box['outer_height'], $box['outer_width'], $box['outer_length'] );
			sort( $dimensions );

			$request = array(
				'height'      => $dimensions[0],
				'width'       => $dimensions[1],
				'length'      => $dimensions[2],
				'weight'      => round( $pbox['current_weight'], 2 ),
				'extra_cover' => ceil( $pbox['total_value'] ),
			);

			// Check for satchel dimension adjustment.
			if ( $this->matches_enabled_satchel_size( floatval( $request['length'] ), floatval( $request['width'] ), floatval( $request['height'] ), floatval( $request['weight'] ) ) ) {
				$request['exact_dimensions'] = array(
					'length' => $request['length'],
					'width'  => $request['width'],
					'height' => $request['height'],
				);
				$request['length'] -= 1;
				$request['width']  -= 1;
				$request['height']  = 0;
			}

			$requests[] = $request;
		}

		return $requests;
	}

	private function matches_enabled_satchel_size( $length, $width, $height, $weight ) {
		$boxes = $this->get_all_boxes();

		if ( empty( $boxes ) || ! is_array( $boxes ) ) {
			return false;
		}

		foreach ( $boxes as $box ) {
			if ( empty( $box['id'] ) || ! $this->is_satchel( $box['id'] ) ) {
				continue;
			}

			$sl = floatval( $box['outer_length'] );
			$sw = floatval( $box['outer_width'] );
			$sh = floatval( $box['outer_height'] );
			$sm = floatval( $box['max_weight'] );

			if ( $sl === $length && $sw === $width && $sh === $height && $weight <= $sm ) {
				return true;
			}
		}

		return false;
	}
}
