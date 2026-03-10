<?php
/**
 * KDNA Shipment Tracking Module
 *
 * Adds shipment tracking to WooCommerce orders with admin metabox,
 * frontend display, and email integration.
 */
defined( 'ABSPATH' ) || exit;

class KDNA_Shipment_Tracking {

	const META_KEY = '_kdna_shipment_tracking_items';

	public function __construct() {
		// Admin.
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );

		// AJAX.
		add_action( 'wp_ajax_kdna_tracking_save', [ $this, 'ajax_save_tracking' ] );
		add_action( 'wp_ajax_kdna_tracking_delete', [ $this, 'ajax_delete_tracking' ] );
		add_action( 'wp_ajax_kdna_tracking_get_items', [ $this, 'ajax_get_items' ] );

		// Frontend.
		add_action( 'woocommerce_view_order', [ $this, 'display_tracking_info' ], 0 );

		// Emails.
		add_action( 'woocommerce_email_before_order_table', [ $this, 'email_display' ], 0, 4 );

		// Orders list column.
		add_filter( 'manage_shop_order_posts_columns', [ $this, 'add_tracking_column' ], 99 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_tracking_column' ], 10, 2 );

		// HPOS support.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_tracking_column' ], 99 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render_tracking_column_hpos' ], 10, 2 );
	}

	// --- Providers ---

	public function get_providers() {
		$providers = array(
			'Australia' => array(
				'Australia Post'   => 'https://auspost.com.au/mypost/track/#/details/%1$s',
				'Fastway Couriers' => 'https://www.aramex.com.au/tools/track?l=%1$s',
				'Aramex Australia' => 'https://www.aramex.com.au/tools/track?l=%1$s',
				'TNT Australia'    => 'https://www.tnt.com/express/en_au/site/tracking.html?searchType=con&cons=%1$s',
				'StarTrack'        => 'https://startrack.com.au/track/details/%1$s',
				'Sendle'           => 'https://track.sendle.com/tracking?ref=%1$s',
			),
			'Austria' => array(
				'post.at'   => 'https://www.post.at/sv/sendungssuche?snr=%1$s',
				'dhl.at'    => 'https://www.dhl.at/en/express/tracking.html?AWB=%1$s',
				'DPD.at'    => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=de_AT',
			),
			'Brazil' => array(
				'Correios' => 'https://www.correios.com.br/rastreamento',
			),
			'Belgium' => array(
				'bpost' => 'https://track.bpost.cloud/btr/web/#/search?itemCode=%1$s&lang=en',
			),
			'Canada' => array(
				'Canada Post' => 'https://www.canadapost-postescanada.ca/track-reperage/en#/resultList?searchFor=%1$s',
				'Purolator'   => 'https://www.purolator.com/en/shipping/tracker?pin=%1$s',
			),
			'Czech Republic' => array(
				'PPL.cz'       => 'https://www.ppl.cz/vyhledat-zasilku?shipmentId=%1$s',
				'Ceska posta'  => 'https://www.postaonline.cz/trackandtrace/-/zasilka/cislo?telegramNumber=%1$s',
				'DHL.cz'       => 'https://www.dhl.cz/en/express/tracking.html?AWB=%1$s',
				'DPD.cz'       => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=cs_CZ',
			),
			'Finland' => array(
				'Itella' => 'https://www.posti.fi/en/search?quickSearchQuery=%1$s',
			),
			'France' => array(
				'Colissimo' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=%1$s',
			),
			'Germany' => array(
				'DHL Intraship (DE)' => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?lang=de&idc=%1$s',
				'Hermes'             => 'https://www.myhermes.de/empfangen/sendungsverfolgung/sendungsinformation#checks=%1$s',
				'Deutsche Post DHL'  => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?lang=de&idc=%1$s',
				'UPS Germany'        => 'https://www.ups.com/track?loc=de_DE&tracknum=%1$s',
				'DPD.de'             => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=de_DE',
			),
			'Ireland' => array(
				'DPD.ie'  => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=en_IE',
				'An Post'  => 'https://track.anpost.ie/TrackingResults.aspx?ression=0&queryType=0&txtTrackNo=%1$s',
			),
			'Italy' => array(
				'BRT (Bartolini)' => 'https://as777.brt.it/vas/sped_det_show.hsm?referer=sped_numspe_par.htm&Ession=(BESSION)&ESSION=(BESSION)&ESSION=(BESSION)&ESSION=(BESSION)&ESSION=(BESSION)&ESSION=(BESSION)&ESSION=(BESSION)&ESSION=(BESSION)',
				'DHL Express'     => 'https://www.dhl.it/it/express/tracking.html?AWB=%1$s',
			),
			'India' => array(
				'DTDC' => 'https://www.dtdc.in/tracking.asp',
			),
			'Netherlands' => array(
				'PostNL'         => 'https://postnl.nl/tracktrace/?B=%1$s&P=%2$s&D=%3$s&T=C',
				'DPD.NL'         => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=nl_NL',
				'UPS Netherlands'=> 'https://www.ups.com/track?loc=nl_NL&tracknum=%1$s',
			),
			'New Zealand' => array(
				'Courier Post'        => 'https://www.courierpost.co.nz/tools/tracking/?tracking_code=%1$s',
				'NZ Post'             => 'https://www.nzpost.co.nz/tools/tracking?trackid=%1$s',
				'Aramex New Zealand'  => 'https://www.aramex.co.nz/tools/track?l=%1$s',
				'PBT Couriers'        => 'https://www.pbtcouriers.co.nz/',
			),
			'Poland' => array(
				'InPost'         => 'https://inpost.pl/sledzenie-przesylek?number=%1$s',
				'DPD.PL'         => 'https://tracktrace.dpd.com.pl/parcelDetails?typ=1&p1=%1$s',
				'Poczta Polska'  => 'https://emonitoring.poczta-polska.pl/?numer=%1$s',
			),
			'Romania' => array(
				'Fan Courier'    => 'https://www.fancourier.ro/en/awb-tracking/?tracking=%1$s',
				'DPD Romania'    => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=ro_RO',
				'Urgent Cargus'  => 'https://app.urgentcargus.ro/Private/Tracking.aspx?CodBara=%1$s',
			),
			'South Africa' => array(
				'SAPO'    => 'http://sms.postoffice.co.za/TrackandTrace/Default.aspx?id=%1$s',
				'Fastway' => 'https://www.fastway.co.za/our-services/track-your-parcel?l=%1$s',
			),
			'Sweden' => array(
				'PostNord Sverige AB' => 'https://www.postnord.se/vara-verktyg/spara-brev-paket-och-pall?shipmentId=%1$s',
				'DHL.se'              => 'https://www.dhl.se/en/express/tracking.html?AWB=%1$s',
				'Bring.se'            => 'https://tracking.bring.se/tracking/%1$s',
				'UPS.se'              => 'https://www.ups.com/track?loc=sv_SE&tracknum=%1$s',
				'DB Schenker'         => 'https://www.dbschenker.com/se-sv/tracking?trackingId=%1$s',
			),
			'United Kingdom' => array(
				'DHL'                           => 'https://www.dhl.co.uk/en/express/tracking.html?AWB=%1$s',
				'DPD.co.uk'                     => 'https://track.dpd.co.uk/parcels/%1$s',
				'EVRi'                          => 'https://www.evri.com/track/parcel/%1$s',
				'ParcelForce'                   => 'https://www.parcelforce.com/track-trace?trackNumber=%1$s',
				'Royal Mail'                    => 'https://www.royalmail.com/track-your-item#/tracking-results/%1$s',
				'TNT Express (consignment)'     => 'https://www.tnt.com/express/en_gb/site/tracking.html?searchType=con&cons=%1$s',
				'TNT Express (reference)'       => 'https://www.tnt.com/express/en_gb/site/tracking.html?searchType=ref&cons=%1$s',
				'DHL Parcel UK'                 => 'https://www.dhl.co.uk/en/express/tracking.html?AWB=%1$s',
			),
			'United States' => array(
				'DHL US'         => 'https://www.dhl.com/us-en/home/tracking.html?tracking-id=%1$s',
				'DHL eCommerce'  => 'https://www.dhl.com/us-en/home/tracking.html?tracking-id=%1$s',
				'FedEx'          => 'https://www.fedex.com/apps/fedextrack/?tracknumbers=%1$s',
				'FedEx Sameday'  => 'https://www.fedex.com/apps/fedextrack/?tracknumbers=%1$s',
				'OnTrac'         => 'https://www.ontrac.com/tracking/?number=%1$s',
				'UPS'            => 'https://www.ups.com/track?tracknum=%1$s',
				'USPS'           => 'https://tools.usps.com/go/TrackConfirmAction_input?qtc_tLabels1=%1$s',
			),
		);

		return apply_filters( 'kdna_shipment_tracking_get_providers', $providers );
	}

	// --- Data Operations ---

	public function get_tracking_items( $order_id, $formatted = false ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array();
		}

		$tracking_items = $order->get_meta( self::META_KEY, true );
		if ( ! is_array( $tracking_items ) ) {
			$tracking_items = array();
		}

		if ( $formatted ) {
			foreach ( $tracking_items as &$item ) {
				$item = $this->get_formatted_tracking_item( $order_id, $item );
			}
		}

		return $tracking_items;
	}

	public function add_tracking_item( $order_id, $args ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array();
		}

		$defaults = array(
			'tracking_provider'        => '',
			'custom_tracking_provider' => '',
			'custom_tracking_link'     => '',
			'tracking_number'          => '',
			'date_shipped'             => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$args['tracking_id'] = md5( $args['tracking_provider'] . '-' . $args['tracking_number'] . microtime() );

		if ( ! empty( $args['date_shipped'] ) ) {
			$args['date_shipped'] = strtotime( $args['date_shipped'] );
		} else {
			$args['date_shipped'] = time();
		}

		$tracking_items   = $this->get_tracking_items( $order_id );
		$tracking_items[] = $args;

		$this->save_tracking_items( $order_id, $tracking_items );

		return $args;
	}

	public function delete_tracking_item( $order_id, $tracking_id ) {
		$tracking_items = $this->get_tracking_items( $order_id );
		$updated        = array();

		foreach ( $tracking_items as $item ) {
			if ( $item['tracking_id'] !== $tracking_id ) {
				$updated[] = $item;
			}
		}

		$this->save_tracking_items( $order_id, $updated );
		return true;
	}

	private function save_tracking_items( $order_id, $tracking_items ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->update_meta_data( self::META_KEY, $tracking_items );
			$order->save();
		}
	}

	public function get_formatted_tracking_item( $order_id, $tracking_item ) {
		$formatted = $tracking_item;

		// Resolve provider name and URL.
		$formatted['formatted_tracking_provider'] = '';
		$formatted['formatted_tracking_link']     = '';

		if ( ! empty( $tracking_item['custom_tracking_provider'] ) ) {
			$formatted['formatted_tracking_provider'] = $tracking_item['custom_tracking_provider'];
			if ( ! empty( $tracking_item['custom_tracking_link'] ) ) {
				$formatted['formatted_tracking_link'] = sprintf( $tracking_item['custom_tracking_link'], $tracking_item['tracking_number'] );
			}
		} else {
			$providers = $this->get_providers();
			foreach ( $providers as $country => $country_providers ) {
				foreach ( $country_providers as $provider_name => $url_template ) {
					$provider_slug = sanitize_title( $provider_name );
					if ( $provider_slug === $tracking_item['tracking_provider'] ) {
						$formatted['formatted_tracking_provider'] = $provider_name;

						$order = wc_get_order( $order_id );
						$postcode     = '';
						$country_code = '';
						if ( $order ) {
							$postcode     = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
							$country_code = $order->get_shipping_country() ?: $order->get_billing_country();
						}

						$formatted['formatted_tracking_link'] = sprintf(
							$url_template,
							$tracking_item['tracking_number'],
							urlencode( $postcode ),
							urlencode( $country_code )
						);
						break 2;
					}
				}
			}
		}

		// Format date.
		if ( ! empty( $tracking_item['date_shipped'] ) ) {
			$formatted['formatted_date_shipped'] = date_i18n( get_option( 'date_format' ), $tracking_item['date_shipped'] );
		} else {
			$formatted['formatted_date_shipped'] = '';
		}

		return $formatted;
	}

	// --- Admin Metabox ---

	public function add_meta_box() {
		$screen = $this->get_order_screen();

		add_meta_box(
			'kdna-shipment-tracking',
			__( 'Shipment Tracking', 'kdna-ecommerce' ),
			[ $this, 'render_meta_box' ],
			$screen,
			'side',
			'high'
		);
	}

	private function get_order_screen() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return wc_get_page_screen_id( 'shop-order' );
		}
		return 'shop_order';
	}

	public function render_meta_box( $post_or_order ) {
		if ( $post_or_order instanceof WP_Post ) {
			$order_id = $post_or_order->ID;
		} else {
			$order_id = $post_or_order->get_id();
		}

		$tracking_items = $this->get_tracking_items( $order_id );

		wp_nonce_field( 'kdna-tracking-save', 'kdna_tracking_nonce' );
		wp_nonce_field( 'kdna-tracking-delete', 'kdna_tracking_delete_nonce' );
		?>
		<div id="kdna-tracking-items">
			<?php
			foreach ( $tracking_items as $item ) {
				$this->render_tracking_item_html( $order_id, $item );
			}
			?>
		</div>

		<p class="kdna-tracking-show-form">
			<a href="#" class="button button-show-form"><?php esc_html_e( 'Add Tracking Number', 'kdna-ecommerce' ); ?></a>
		</p>

		<div id="kdna-tracking-form" style="display:none;">
			<p class="form-field">
				<label for="kdna_tracking_provider"><?php esc_html_e( 'Provider:', 'kdna-ecommerce' ); ?></label>
				<select id="kdna_tracking_provider" name="tracking_provider" class="widefat">
					<optgroup label="<?php esc_attr_e( 'Custom Provider', 'kdna-ecommerce' ); ?>">
						<option value="custom"><?php esc_html_e( 'Custom Provider', 'kdna-ecommerce' ); ?></option>
					</optgroup>
					<?php
					$providers = $this->get_providers();
					foreach ( $providers as $country => $country_providers ) {
						echo '<optgroup label="' . esc_attr( $country ) . '">';
						foreach ( $country_providers as $provider_name => $url ) {
							echo '<option value="' . esc_attr( sanitize_title( $provider_name ) ) . '" data-url="' . esc_attr( $url ) . '">' . esc_html( $provider_name ) . '</option>';
						}
						echo '</optgroup>';
					}
					?>
				</select>
			</p>
			<p class="form-field custom_tracking_provider_field" style="display:none;">
				<label for="kdna_custom_tracking_provider"><?php esc_html_e( 'Provider Name:', 'kdna-ecommerce' ); ?></label>
				<input type="text" id="kdna_custom_tracking_provider" name="custom_tracking_provider" class="widefat" />
			</p>
			<p class="form-field custom_tracking_link_field" style="display:none;">
				<label for="kdna_custom_tracking_link"><?php esc_html_e( 'Tracking Link:', 'kdna-ecommerce' ); ?></label>
				<input type="text" id="kdna_custom_tracking_link" name="custom_tracking_link" class="widefat" placeholder="https://" />
				<span class="description"><?php esc_html_e( 'Use %1$s for the tracking number.', 'kdna-ecommerce' ); ?></span>
			</p>
			<p class="form-field">
				<label for="kdna_tracking_number"><?php esc_html_e( 'Tracking Number:', 'kdna-ecommerce' ); ?></label>
				<input type="text" id="kdna_tracking_number" name="tracking_number" class="widefat" required />
			</p>
			<p class="form-field">
				<label for="kdna_date_shipped"><?php esc_html_e( 'Date Shipped:', 'kdna-ecommerce' ); ?></label>
				<input type="text" id="kdna_date_shipped" name="date_shipped" class="date-picker widefat" placeholder="<?php echo esc_attr( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>" />
			</p>
			<p>
				<button type="button" class="button button-primary button-save-form"><?php esc_html_e( 'Save Tracking', 'kdna-ecommerce' ); ?></button>
				<button type="button" class="button button-cancel-form"><?php esc_html_e( 'Cancel', 'kdna-ecommerce' ); ?></button>
			</p>
		</div>

		<input type="hidden" id="kdna_tracking_order_id" value="<?php echo esc_attr( $order_id ); ?>" />
		<?php
	}

	private function render_tracking_item_html( $order_id, $item ) {
		$formatted = $this->get_formatted_tracking_item( $order_id, $item );
		?>
		<div class="kdna-tracking-item" data-tracking-id="<?php echo esc_attr( $item['tracking_id'] ); ?>">
			<p>
				<strong><?php echo esc_html( $formatted['formatted_tracking_provider'] ); ?></strong><br>
				<?php echo esc_html( $item['tracking_number'] ); ?>
				<?php if ( ! empty( $formatted['formatted_date_shipped'] ) ) : ?>
					<br><em><?php echo esc_html( $formatted['formatted_date_shipped'] ); ?></em>
				<?php endif; ?>
				<?php if ( ! empty( $formatted['formatted_tracking_link'] ) ) : ?>
					<br><a href="<?php echo esc_url( $formatted['formatted_tracking_link'] ); ?>" target="_blank"><?php esc_html_e( 'Track', 'kdna-ecommerce' ); ?></a>
				<?php endif; ?>
			</p>
			<p>
				<a href="#" class="delete-tracking" data-tracking-id="<?php echo esc_attr( $item['tracking_id'] ); ?>"><?php esc_html_e( 'Delete', 'kdna-ecommerce' ); ?></a>
			</p>
			<hr>
		</div>
		<?php
	}

	public function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_order_edit = ( 'shop_order' === $screen->id || 'woocommerce_page_wc-orders' === $screen->id );

		if ( ! $is_order_edit ) {
			return;
		}

		wp_enqueue_style( 'kdna-tracking-admin', KDNA_ECOMMERCE_URL . 'assets/css/tracking-admin.css', [], KDNA_ECOMMERCE_VERSION );
		wp_enqueue_script( 'kdna-tracking-admin', KDNA_ECOMMERCE_URL . 'assets/js/tracking-admin.js', [ 'jquery', 'jquery-ui-datepicker' ], KDNA_ECOMMERCE_VERSION, true );
		wp_localize_script( 'kdna-tracking-admin', 'kdna_tracking', [
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'save_nonce'   => wp_create_nonce( 'kdna-tracking-save' ),
			'delete_nonce' => wp_create_nonce( 'kdna-tracking-delete' ),
			'get_nonce'    => wp_create_nonce( 'kdna-tracking-get' ),
		] );
	}

	// --- AJAX Handlers ---

	public function ajax_save_tracking() {
		check_ajax_referer( 'kdna-tracking-save', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		$order_id = absint( $_POST['order_id'] );

		$args = array(
			'tracking_provider'        => sanitize_text_field( $_POST['tracking_provider'] ?? '' ),
			'custom_tracking_provider' => sanitize_text_field( $_POST['custom_tracking_provider'] ?? '' ),
			'custom_tracking_link'     => esc_url_raw( $_POST['custom_tracking_link'] ?? '' ),
			'tracking_number'          => sanitize_text_field( $_POST['tracking_number'] ?? '' ),
			'date_shipped'             => sanitize_text_field( $_POST['date_shipped'] ?? '' ),
		);

		if ( empty( $args['tracking_number'] ) ) {
			wp_send_json_error( 'Tracking number is required.' );
		}

		// If provider is "custom", clear the standard provider.
		if ( 'custom' === $args['tracking_provider'] ) {
			$args['tracking_provider'] = '';
		} else {
			$args['custom_tracking_provider'] = '';
			$args['custom_tracking_link']     = '';
		}

		$this->add_tracking_item( $order_id, $args );

		ob_start();
		$tracking_items = $this->get_tracking_items( $order_id );
		foreach ( $tracking_items as $item ) {
			$this->render_tracking_item_html( $order_id, $item );
		}
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}

	public function ajax_delete_tracking() {
		check_ajax_referer( 'kdna-tracking-delete', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		$order_id    = absint( $_POST['order_id'] );
		$tracking_id = sanitize_text_field( $_POST['tracking_id'] );

		$this->delete_tracking_item( $order_id, $tracking_id );

		ob_start();
		$tracking_items = $this->get_tracking_items( $order_id );
		foreach ( $tracking_items as $item ) {
			$this->render_tracking_item_html( $order_id, $item );
		}
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}

	public function ajax_get_items() {
		check_ajax_referer( 'kdna-tracking-get', 'security' );

		$order_id = absint( $_POST['order_id'] );

		ob_start();
		$tracking_items = $this->get_tracking_items( $order_id );
		foreach ( $tracking_items as $item ) {
			$this->render_tracking_item_html( $order_id, $item );
		}
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}

	// --- Frontend Display ---

	public function display_tracking_info( $order_id ) {
		$tracking_items = $this->get_tracking_items( $order_id, true );

		if ( empty( $tracking_items ) ) {
			return;
		}

		$title = apply_filters( 'kdna_shipment_tracking_my_orders_title', __( 'Tracking Information', 'kdna-ecommerce' ) );
		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<table class="woocommerce-table shop_table kdna-tracking-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provider', 'kdna-ecommerce' ); ?></th>
					<th><?php esc_html_e( 'Tracking Number', 'kdna-ecommerce' ); ?></th>
					<th><?php esc_html_e( 'Date', 'kdna-ecommerce' ); ?></th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tracking_items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item['formatted_tracking_provider'] ); ?></td>
						<td><?php echo esc_html( $item['tracking_number'] ); ?></td>
						<td><?php echo esc_html( $item['formatted_date_shipped'] ); ?></td>
						<td>
							<?php if ( ! empty( $item['formatted_tracking_link'] ) ) : ?>
								<a href="<?php echo esc_url( $item['formatted_tracking_link'] ); ?>" target="_blank" class="button"><?php esc_html_e( 'Track', 'kdna-ecommerce' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	// --- Email Display ---

	public function email_display( $order, $sent_to_admin, $plain_text, $email = null ) {
		$excluded = apply_filters( 'kdna_shipment_tracking_excluded_email_classes', array( 'WC_Email_Customer_Refunded_Order' ) );

		if ( $email && in_array( get_class( $email ), $excluded, true ) ) {
			return;
		}

		$order_id       = $order->get_id();
		$tracking_items = $this->get_tracking_items( $order_id, true );

		if ( empty( $tracking_items ) ) {
			return;
		}

		$title = apply_filters( 'kdna_shipment_tracking_my_orders_title', __( 'Tracking Information', 'kdna-ecommerce' ) );

		if ( $plain_text ) {
			echo "\n\n" . esc_html( strtoupper( $title ) ) . "\n\n";
			foreach ( $tracking_items as $item ) {
				echo esc_html( $item['formatted_tracking_provider'] ) . "\n";
				echo esc_html( $item['tracking_number'] ) . "\n";
				if ( ! empty( $item['formatted_tracking_link'] ) ) {
					echo esc_html( $item['formatted_tracking_link'] ) . "\n";
				}
				echo "\n";
			}
		} else {
			?>
			<h2><?php echo esc_html( $title ); ?></h2>
			<table cellspacing="0" cellpadding="6" style="width:100%; border:1px solid #eee; margin-bottom:20px;" border="1">
				<thead>
					<tr>
						<th style="text-align:left; border:1px solid #eee; padding:12px;"><?php esc_html_e( 'Provider', 'kdna-ecommerce' ); ?></th>
						<th style="text-align:left; border:1px solid #eee; padding:12px;"><?php esc_html_e( 'Tracking Number', 'kdna-ecommerce' ); ?></th>
						<th style="text-align:left; border:1px solid #eee; padding:12px;"><?php esc_html_e( 'Date', 'kdna-ecommerce' ); ?></th>
						<th style="text-align:left; border:1px solid #eee; padding:12px;">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tracking_items as $item ) : ?>
						<tr>
							<td style="text-align:left; border:1px solid #eee; padding:12px;"><?php echo esc_html( $item['formatted_tracking_provider'] ); ?></td>
							<td style="text-align:left; border:1px solid #eee; padding:12px;"><?php echo esc_html( $item['tracking_number'] ); ?></td>
							<td style="text-align:left; border:1px solid #eee; padding:12px;"><?php echo esc_html( $item['formatted_date_shipped'] ); ?></td>
							<td style="text-align:left; border:1px solid #eee; padding:12px;">
								<?php if ( ! empty( $item['formatted_tracking_link'] ) ) : ?>
									<a href="<?php echo esc_url( $item['formatted_tracking_link'] ); ?>"><?php esc_html_e( 'Track', 'kdna-ecommerce' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		}
	}

	// --- Orders List Column ---

	public function add_tracking_column( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'order_status' === $key ) {
				$new_columns['kdna_tracking'] = __( 'Tracking', 'kdna-ecommerce' );
			}
		}
		return $new_columns;
	}

	public function render_tracking_column( $column_name, $post_id ) {
		if ( 'kdna_tracking' === $column_name ) {
			echo $this->get_tracking_column_html( $post_id );
		}
	}

	public function render_tracking_column_hpos( $column_name, $order ) {
		if ( 'kdna_tracking' === $column_name ) {
			echo $this->get_tracking_column_html( $order->get_id() );
		}
	}

	private function get_tracking_column_html( $order_id ) {
		$tracking_items = $this->get_tracking_items( $order_id, true );

		if ( empty( $tracking_items ) ) {
			return '&ndash;';
		}

		$output = '';
		foreach ( $tracking_items as $item ) {
			if ( ! empty( $item['formatted_tracking_link'] ) ) {
				$output .= '<a href="' . esc_url( $item['formatted_tracking_link'] ) . '" target="_blank">' . esc_html( $item['tracking_number'] ) . '</a><br>';
			} else {
				$output .= esc_html( $item['tracking_number'] ) . '<br>';
			}
		}

		return $output;
	}
}
