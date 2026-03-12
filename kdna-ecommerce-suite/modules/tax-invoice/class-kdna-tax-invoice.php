<?php
defined( 'ABSPATH' ) || exit;

use Dompdf\Dompdf;
use Dompdf\Options;

class KDNA_Tax_Invoice {

    public function __construct() {
        // Attach invoice to completed order email.
        add_filter( 'woocommerce_email_attachments', [ $this, 'attach_invoice_to_email' ], 10, 4 );

        // My Account – download button on order details page.
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'add_invoice_download_button' ] );

        // Handle PDF download requests.
        add_action( 'init', [ $this, 'handle_invoice_download' ] );

    }

    /**
     * Register the test PDF handler separately so it works even when the module is disabled.
     */
    public static function register_test_pdf_handler() {
        add_action( 'admin_post_kdna_test_invoice', function () {
            ( new self() )->handle_test_pdf_download();
        } );
    }

    /**
     * Default settings for the invoice module.
     */
    public static function get_default_settings() {
        return [
            'logo_id'      => '',
            'accent_color' => '#C8E600',
            'footer_text'  => '',
        ];
    }

    /**
     * Attach the invoice PDF to the WooCommerce completed order email.
     */
    public function attach_invoice_to_email( $attachments, $email_id, $order, $email = null ) {
        if ( 'customer_completed_order' !== $email_id || ! $order ) {
            return $attachments;
        }

        $pdf_path = $this->generate_pdf( $order );
        if ( $pdf_path && file_exists( $pdf_path ) ) {
            $attachments[] = $pdf_path;
        }

        return $attachments;
    }

    /**
     * Show a download button on the My Account > View Order page.
     */
    public function add_invoice_download_button( $order ) {
        if ( ! $order || ! $order->has_status( 'completed' ) ) {
            return;
        }

        $url = wp_nonce_url(
            add_query_arg( [
                'kdna_invoice'  => '1',
                'order_id'      => $order->get_id(),
            ], home_url( '/' ) ),
            'kdna_invoice_' . $order->get_id()
        );

        echo '<p class="kdna-invoice-download" style="margin:1.5em 0;">';
        echo '<a href="' . esc_url( $url ) . '" class="button" target="_blank">';
        echo esc_html__( 'Download Tax Invoice (PDF)', 'kdna-ecommerce' );
        echo '</a></p>';
    }

    /**
     * Handle the PDF download via query string.
     */
    public function handle_invoice_download() {
        if ( empty( $_GET['kdna_invoice'] ) || empty( $_GET['order_id'] ) ) {
            return;
        }

        $order_id = absint( $_GET['order_id'] );

        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'kdna_invoice_' . $order_id ) ) {
            wp_die( __( 'Invalid request.', 'kdna-ecommerce' ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( __( 'Order not found.', 'kdna-ecommerce' ) );
        }

        // Verify the current user owns this order or is an admin.
        $current_user_id = get_current_user_id();
        if ( $order->get_customer_id() !== $current_user_id && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have permission to view this invoice.', 'kdna-ecommerce' ) );
        }

        $this->stream_pdf( $order );
        exit;
    }

    /**
     * Generate a PDF file and return its path (used for email attachments).
     */
    public function generate_pdf( $order ) {
        $html    = $this->build_invoice_html( $order );
        $dompdf  = $this->create_dompdf();

        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        $upload_dir = wp_upload_dir();
        $dir        = $upload_dir['basedir'] . '/kdna-invoices';

        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            // Prevent directory listing.
            file_put_contents( $dir . '/.htaccess', 'deny from all' );
            file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' );
        }

        $file_path = $dir . '/invoice-' . $order->get_id() . '.pdf';
        file_put_contents( $file_path, $dompdf->output() );

        return $file_path;
    }

    /**
     * Stream a PDF directly to the browser (used for downloads).
     */
    private function stream_pdf( $order ) {
        $html   = $this->build_invoice_html( $order );
        $dompdf = $this->create_dompdf();

        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        $filename = 'tax-invoice-' . $order->get_order_number() . '.pdf';

        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="' . $filename . '"' );
        header( 'Cache-Control: private, max-age=0, must-revalidate' );

        echo $dompdf->output();
    }

    /**
     * Create a configured DOMPDF instance.
     */
    private function create_dompdf() {
        $options = new Options();
        $options->set( 'isRemoteEnabled', true );
        $options->set( 'isHtml5ParserEnabled', true );
        $options->set( 'defaultFont', 'Helvetica' );
        $options->set( 'isFontSubsettingEnabled', true );
        $options->set( 'tempDir', sys_get_temp_dir() );

        return new Dompdf( $options );
    }

    /**
     * Build the full invoice HTML from order data and settings.
     */
    private function build_invoice_html( $order ) {
        $settings = wp_parse_args(
            get_option( 'kdna_invoice_settings', [] ),
            self::get_default_settings()
        );

        $accent_color  = sanitize_hex_color( $settings['accent_color'] ) ?: '#C8E600';
        $footer_text   = $settings['footer_text'];
        $logo_id       = $settings['logo_id'];
        $logo_src      = '';

        if ( $logo_id ) {
            $logo_path = get_attached_file( $logo_id );
            if ( $logo_path && file_exists( $logo_path ) ) {
                $mime     = mime_content_type( $logo_path );
                $data     = base64_encode( file_get_contents( $logo_path ) );
                $logo_src = 'data:' . $mime . ';base64,' . $data;
            }
        }

        // Order data.
        $order_number   = $order->get_order_number();
        $order_date     = $order->get_date_created() ? $order->get_date_created()->date( 'd/m/Y' ) : '';
        $payment_method = $order->get_payment_method_title();

        // Shipping address fields.
        $name     = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
        $address1 = $order->get_shipping_address_1();
        $address2 = $order->get_shipping_address_2();
        $city     = $order->get_shipping_city();
        $state    = $order->get_shipping_state();
        $postcode = $order->get_shipping_postcode();
        $country  = $order->get_shipping_country();
        $phone    = $order->get_billing_phone();

        // If shipping name is empty fall back to billing.
        if ( empty( $name ) ) {
            $name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        }

        // Format the country name.
        if ( $country && class_exists( 'WC_Countries' ) ) {
            $countries_obj = new WC_Countries();
            $countries     = $countries_obj->get_countries();
            $country       = $countries[ $country ] ?? $country;
        }

        // Format state name.
        $raw_state = $order->get_shipping_state();
        $raw_country = $order->get_shipping_country();
        if ( $raw_state && $raw_country ) {
            $states = WC()->countries->get_states( $raw_country );
            if ( $states && isset( $states[ $raw_state ] ) ) {
                $state = $states[ $raw_state ];
            }
        }

        // Build address lines.
        $address_lines = array_filter( [
            $address1,
            $address2,
            $city,
            trim( $state . ' ' . $postcode . ' ' . $country ),
        ] );

        // Resolve Apotheca font from Elementor custom fonts or theme.
        $font_face_css = $this->get_apotheca_font_css();

        // Build items HTML.
        $items_html = '';
        foreach ( $order->get_items() as $item ) {
            $product  = $item->get_product();
            $qty      = $item->get_quantity();
            $subtotal = $item->get_subtotal();
            $total    = $item->get_total();
            $unit_cost = $qty > 0 ? $subtotal / $qty : 0;

            // Product image as base64 for reliable embedding.
            $img_html = '';
            if ( $product ) {
                $thumb_id = $product->get_image_id();
                if ( $thumb_id ) {
                    $img_path = get_attached_file( $thumb_id );
                    if ( $img_path && file_exists( $img_path ) ) {
                        $mime     = mime_content_type( $img_path );
                        $data     = base64_encode( file_get_contents( $img_path ) );
                        $img_html = '<img src="data:' . $mime . ';base64,' . $data . '" style="width:40px;height:50px;object-fit:cover;">';
                    }
                }
            }

            $items_html .= '<tr>';
            $items_html .= '<td style="width:55px;padding:12px 8px;border-bottom:1px solid #eee;vertical-align:middle;">' . $img_html . '</td>';
            $items_html .= '<td style="padding:12px 8px;border-bottom:1px solid #eee;vertical-align:middle;">' . esc_html( $item->get_name() ) . '</td>';
            $items_html .= '<td style="padding:12px 8px;border-bottom:1px solid #eee;vertical-align:middle;text-align:center;">' . esc_html( $qty ) . '</td>';
            $items_html .= '<td style="padding:12px 8px;border-bottom:1px solid #eee;vertical-align:middle;text-align:right;">' . wc_price( $unit_cost ) . '</td>';
            $items_html .= '<td style="padding:12px 8px;border-bottom:1px solid #eee;vertical-align:middle;text-align:right;">' . wc_price( $total ) . '</td>';
            $items_html .= '</tr>';
        }

        // Totals.
        $subtotal_amount = $order->get_subtotal();
        $tax_amount      = $order->get_total_tax();
        $total_amount    = $order->get_total();

        // Shipping row (if applicable).
        $shipping_total = (float) $order->get_shipping_total();
        $shipping_html  = '';
        if ( $shipping_total > 0 ) {
            $shipping_html = '
                <tr>
                    <td colspan="4" style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">Shipping</td>
                    <td style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">' . wc_price( $shipping_total ) . '</td>
                </tr>';
        }

        // Discount row (if applicable).
        $discount_total = (float) $order->get_discount_total();
        $discount_html  = '';
        if ( $discount_total > 0 ) {
            $discount_html = '
                <tr>
                    <td colspan="4" style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">Discount</td>
                    <td style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">-' . wc_price( $discount_total ) . '</td>
                </tr>';
        }

        // Fee rows.
        $fee_html = '';
        foreach ( $order->get_fees() as $fee ) {
            $fee_html .= '
                <tr>
                    <td colspan="4" style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">' . esc_html( $fee->get_name() ) . '</td>
                    <td style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">' . wc_price( $fee->get_total() ) . '</td>
                </tr>';
        }

        $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
' . $font_face_css . '
<style>
    @page {
        margin: 0;
        size: A4;
    }
    body {
        font-family: "Apotheca", Helvetica, Arial, sans-serif;
        font-weight: 400;
        color: #1a1a1a;
        font-size: 10pt;
        line-height: 1.5;
        margin: 0;
        padding: 0;
    }
    .page-wrap {
        padding: 0 50px 120px 50px;
        position: relative;
        min-height: 100%;
    }
    .accent-bar {
        background-color: ' . $accent_color . ';
        height: 8px;
        width: 100%;
    }
    .header-table {
        width: 100%;
        margin-top: 25px;
        margin-bottom: 35px;
    }
    .header-table td {
        vertical-align: bottom;
        padding: 0;
    }
    .tax-invoice-title {
        font-size: 26pt;
        font-weight: 500;
        text-align: right;
        letter-spacing: 0.5px;
    }
    .details-table {
        width: 100%;
        margin-bottom: 35px;
    }
    .details-table td {
        vertical-align: top;
        padding: 0;
    }
    .to-label {
        font-size: 9pt;
        color: #666;
        margin-bottom: 4px;
    }
    .customer-name {
        font-weight: 500;
        font-size: 11pt;
        margin-bottom: 2px;
    }
    .address-line {
        margin: 1px 0;
        font-size: 10pt;
    }
    .invoice-meta {
        text-align: right;
        font-size: 10pt;
        line-height: 1.8;
    }
    .invoice-meta strong {
        font-weight: 500;
    }
    .items-table {
        width: 100%;
        border-collapse: collapse;
    }
    .items-table th {
        background-color: ' . $accent_color . ';
        padding: 10px 12px;
        text-align: left;
        font-weight: 500;
        font-size: 7.5pt;
        letter-spacing: 1.2px;
        text-transform: uppercase;
    }
    .items-table th.qty { text-align: center; }
    .items-table th.uc  { text-align: right; }
    .items-table th.amt { text-align: right; }
    .footer-section {
        position: fixed;
        bottom: 40px;
        left: 50px;
        right: 50px;
        border-top: 1px solid #1a1a1a;
        padding-top: 15px;
        text-align: center;
        font-size: 9pt;
        line-height: 1.7;
    }
    .footer-section strong {
        font-weight: 500;
    }
</style>
</head>
<body>

<div class="accent-bar"></div>

<div class="page-wrap">

    <!-- Header: Logo + Title -->
    <table class="header-table">
        <tr>
            <td style="width:60%;">'
                . ( $logo_src ? '<img src="' . $logo_src . '" style="max-height:55px;max-width:250px;">' : '' ) .
            '</td>
            <td style="width:40%;">
                <div class="tax-invoice-title">TAX INVOICE</div>
            </td>
        </tr>
    </table>

    <!-- TO / Invoice Details -->
    <table class="details-table">
        <tr>
            <td style="width:55%;">
                <div class="to-label">TO:</div>
                <div class="customer-name">' . esc_html( $name ) . '</div>'
                . implode( '', array_map( function ( $line ) {
                    return '<div class="address-line">' . esc_html( $line ) . '</div>';
                }, $address_lines ) )
                . ( $phone ? '<div class="address-line" style="margin-top:4px;">' . esc_html( $phone ) . '</div>' : '' ) .
            '</td>
            <td style="width:45%;">
                <div class="invoice-meta">
                    <strong>Invoice no:</strong> ' . esc_html( $order_number ) . '<br>
                    <strong>Date:</strong> ' . esc_html( $order_date ) . '<br><br>
                    <strong>Payment method:</strong> ' . esc_html( $payment_method ) . '
                </div>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th colspan="2">DESCRIPTION</th>
                <th class="qty">QUANTITY</th>
                <th class="uc">UNIT COST</th>
                <th class="amt">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            ' . $items_html . '

            <!-- Subtotal -->
            <tr>
                <td colspan="4" style="padding:8px 12px;text-align:right;border:none;font-size:9.5pt;">Sub-total</td>
                <td style="padding:8px 12px;text-align:right;border:none;font-size:9.5pt;">' . wc_price( $subtotal_amount ) . '</td>
            </tr>
            ' . $shipping_html . '
            ' . $discount_html . '
            ' . $fee_html . '
            <!-- Tax -->
            <tr>
                <td colspan="4" style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">Tax</td>
                <td style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">' . wc_price( $tax_amount ) . '</td>
            </tr>
            <!-- Total Paid -->
            <tr>
                <td colspan="3" style="border:none;"></td>
                <td style="background-color:' . $accent_color . ';padding:10px 12px;font-weight:500;font-size:10pt;">TOTAL PAID</td>
                <td style="background-color:' . $accent_color . ';padding:10px 12px;text-align:right;font-weight:500;font-size:10pt;">' . wc_price( $total_amount ) . '</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Footer -->
<div class="footer-section">
    ' . wp_kses_post( $footer_text ) . '
</div>

</body>
</html>';

        return $html;
    }

    /**
     * Handle the test PDF download from the admin settings page.
     */
    public function handle_test_pdf_download() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have permission to do this.', 'kdna-ecommerce' ) );
        }

        check_admin_referer( 'kdna_test_invoice' );

        $html   = $this->build_test_invoice_html();
        $dompdf = $this->create_dompdf();

        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="test-tax-invoice.pdf"' );
        header( 'Cache-Control: private, max-age=0, must-revalidate' );

        echo $dompdf->output();
        exit;
    }

    /**
     * Build invoice HTML with dummy data for previewing the layout.
     */
    private function build_test_invoice_html() {
        $settings = wp_parse_args(
            get_option( 'kdna_invoice_settings', [] ),
            self::get_default_settings()
        );

        $accent_color = sanitize_hex_color( $settings['accent_color'] ) ?: '#C8E600';
        $footer_text  = $settings['footer_text'];
        $logo_id      = $settings['logo_id'];
        $logo_src     = '';

        if ( $logo_id ) {
            $logo_path = get_attached_file( $logo_id );
            if ( $logo_path && file_exists( $logo_path ) ) {
                $mime     = mime_content_type( $logo_path );
                $data     = base64_encode( file_get_contents( $logo_path ) );
                $logo_src = 'data:' . $mime . ';base64,' . $data;
            }
        }

        $font_face_css = $this->get_apotheca_font_css();

        // Dummy line items.
        $dummy_items = [
            [ 'name' => 'Organic Lavender Hand Cream 250ml',  'qty' => 2, 'unit_cost' => 24.95 ],
            [ 'name' => 'Rosehip Facial Serum 30ml',          'qty' => 1, 'unit_cost' => 49.50 ],
            [ 'name' => 'Tea Tree Shampoo Bar 120g',          'qty' => 3, 'unit_cost' => 14.00 ],
        ];

        $items_html     = '';
        $subtotal       = 0;

        foreach ( $dummy_items as $item ) {
            $line_total = $item['qty'] * $item['unit_cost'];
            $subtotal  += $line_total;
            $items_html .= '<tr>';
            $items_html .= '<td style="width:55px;padding:12px 8px;border-bottom:1px solid #eee;vertical-align:middle;"></td>';
            $items_html .= '<td style="padding:12px 8px;border-bottom:1px solid #eee;vertical-align:middle;">' . esc_html( $item['name'] ) . '</td>';
            $items_html .= '<td style="padding:12px 8px;border-bottom:1px solid #eee;vertical-align:middle;text-align:center;">' . esc_html( $item['qty'] ) . '</td>';
            $items_html .= '<td style="padding:12px 8px;border-bottom:1px solid #eee;vertical-align:middle;text-align:right;">$' . number_format( $item['unit_cost'], 2 ) . '</td>';
            $items_html .= '<td style="padding:12px 8px;border-bottom:1px solid #eee;vertical-align:middle;text-align:right;">$' . number_format( $line_total, 2 ) . '</td>';
            $items_html .= '</tr>';
        }

        $shipping    = 9.95;
        $tax         = round( $subtotal * 0.10, 2 );
        $total       = $subtotal + $shipping + $tax;

        $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
' . $font_face_css . '
<style>
    @page {
        margin: 0;
        size: A4;
    }
    body {
        font-family: "Apotheca", Helvetica, Arial, sans-serif;
        font-weight: 400;
        color: #1a1a1a;
        font-size: 10pt;
        line-height: 1.5;
        margin: 0;
        padding: 0;
    }
    .page-wrap {
        padding: 0 50px 120px 50px;
        position: relative;
        min-height: 100%;
    }
    .accent-bar {
        background-color: ' . $accent_color . ';
        height: 8px;
        width: 100%;
    }
    .header-table {
        width: 100%;
        margin-top: 25px;
        margin-bottom: 35px;
    }
    .header-table td {
        vertical-align: bottom;
        padding: 0;
    }
    .tax-invoice-title {
        font-size: 26pt;
        font-weight: 500;
        text-align: right;
        letter-spacing: 0.5px;
    }
    .details-table {
        width: 100%;
        margin-bottom: 35px;
    }
    .details-table td {
        vertical-align: top;
        padding: 0;
    }
    .to-label {
        font-size: 9pt;
        color: #666;
        margin-bottom: 4px;
    }
    .customer-name {
        font-weight: 500;
        font-size: 11pt;
        margin-bottom: 2px;
    }
    .address-line {
        margin: 1px 0;
        font-size: 10pt;
    }
    .invoice-meta {
        text-align: right;
        font-size: 10pt;
        line-height: 1.8;
    }
    .invoice-meta strong {
        font-weight: 500;
    }
    .items-table {
        width: 100%;
        border-collapse: collapse;
    }
    .items-table th {
        background-color: ' . $accent_color . ';
        padding: 10px 12px;
        text-align: left;
        font-weight: 500;
        font-size: 7.5pt;
        letter-spacing: 1.2px;
        text-transform: uppercase;
    }
    .items-table th.qty { text-align: center; }
    .items-table th.uc  { text-align: right; }
    .items-table th.amt { text-align: right; }
    .footer-section {
        position: fixed;
        bottom: 40px;
        left: 50px;
        right: 50px;
        border-top: 1px solid #1a1a1a;
        padding-top: 15px;
        text-align: center;
        font-size: 9pt;
        line-height: 1.7;
    }
    .footer-section strong {
        font-weight: 500;
    }
</style>
</head>
<body>

<div class="accent-bar"></div>

<div class="page-wrap">

    <!-- Header: Logo + Title -->
    <table class="header-table">
        <tr>
            <td style="width:60%;">'
                . ( $logo_src ? '<img src="' . $logo_src . '" style="max-height:55px;max-width:250px;">' : '' ) .
            '</td>
            <td style="width:40%;">
                <div class="tax-invoice-title">TAX INVOICE</div>
            </td>
        </tr>
    </table>

    <!-- TO / Invoice Details -->
    <table class="details-table">
        <tr>
            <td style="width:55%;">
                <div class="to-label">TO:</div>
                <div class="customer-name">Jane Smith</div>
                <div class="address-line">42 Wallaby Way</div>
                <div class="address-line">Sydney</div>
                <div class="address-line">New South Wales 2000 Australia</div>
                <div class="address-line" style="margin-top:4px;">0412 345 678</div>
            </td>
            <td style="width:45%;">
                <div class="invoice-meta">
                    <strong>Invoice no:</strong> 10042<br>
                    <strong>Date:</strong> ' . wp_date( 'd/m/Y' ) . '<br><br>
                    <strong>Payment method:</strong> Credit Card (Stripe)
                </div>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th colspan="2">DESCRIPTION</th>
                <th class="qty">QUANTITY</th>
                <th class="uc">UNIT COST</th>
                <th class="amt">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            ' . $items_html . '

            <!-- Subtotal -->
            <tr>
                <td colspan="4" style="padding:8px 12px;text-align:right;border:none;font-size:9.5pt;">Sub-total</td>
                <td style="padding:8px 12px;text-align:right;border:none;font-size:9.5pt;">$' . number_format( $subtotal, 2 ) . '</td>
            </tr>
            <!-- Shipping -->
            <tr>
                <td colspan="4" style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">Shipping</td>
                <td style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">$' . number_format( $shipping, 2 ) . '</td>
            </tr>
            <!-- Tax -->
            <tr>
                <td colspan="4" style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">Tax</td>
                <td style="padding:6px 12px;text-align:right;border:none;font-size:9.5pt;">$' . number_format( $tax, 2 ) . '</td>
            </tr>
            <!-- Total Paid -->
            <tr>
                <td colspan="3" style="border:none;"></td>
                <td style="background-color:' . $accent_color . ';padding:10px 12px;font-weight:500;font-size:10pt;">TOTAL PAID</td>
                <td style="background-color:' . $accent_color . ';padding:10px 12px;text-align:right;font-weight:500;font-size:10pt;">$' . number_format( $total, 2 ) . '</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Footer -->
<div class="footer-section">
    ' . wp_kses_post( $footer_text ) . '
</div>

</body>
</html>';

        return $html;
    }

    /**
     * Attempt to load the Apotheca font from Elementor custom fonts or theme.
     * Returns a <style> block with @font-face declarations, or empty string.
     */
    private function get_apotheca_font_css() {
        $font_urls = [];

        // Check Elementor custom font posts.
        $font_posts = get_posts( [
            'post_type'      => 'elementor_font',
            'posts_per_page' => 5,
            'post_status'    => 'publish',
            's'              => 'Apotheca',
        ] );

        if ( empty( $font_posts ) ) {
            // Broader search – match any font post title containing "apotheca".
            $font_posts = get_posts( [
                'post_type'      => 'elementor_font',
                'posts_per_page' => 20,
                'post_status'    => 'publish',
            ] );
            $font_posts = array_filter( $font_posts, function ( $p ) {
                return stripos( $p->post_title, 'apotheca' ) !== false;
            } );
        }

        foreach ( $font_posts as $fp ) {
            $meta = get_post_meta( $fp->ID );
            // Elementor stores font files in repeater meta fields.
            foreach ( $meta as $key => $values ) {
                foreach ( $values as $val ) {
                    if ( is_string( $val ) && preg_match( '/\.(woff2?|ttf|otf|eot)$/i', $val ) ) {
                        $font_urls[] = $val;
                    }
                    // Try unserialized data.
                    $unserialized = @unserialize( $val );
                    if ( is_array( $unserialized ) ) {
                        array_walk_recursive( $unserialized, function ( $v ) use ( &$font_urls ) {
                            if ( is_string( $v ) && preg_match( '/\.(woff2?|ttf|otf|eot)$/i', $v ) ) {
                                $font_urls[] = $v;
                            }
                            if ( is_string( $v ) && filter_var( $v, FILTER_VALIDATE_URL ) && preg_match( '/font/i', $v ) ) {
                                $font_urls[] = $v;
                            }
                        } );
                    }
                }
            }
        }

        // Also check common filesystem locations.
        $possible_dirs = [
            get_stylesheet_directory() . '/fonts',
            get_stylesheet_directory() . '/assets/fonts',
            wp_upload_dir()['basedir'] . '/elementor/custom-fonts',
        ];

        foreach ( $possible_dirs as $dir ) {
            if ( ! is_dir( $dir ) ) {
                continue;
            }
            $files = glob( $dir . '/*potheca*.*' );
            if ( $files ) {
                foreach ( $files as $file ) {
                    if ( preg_match( '/\.(woff2?|ttf|otf)$/i', $file ) ) {
                        $font_urls[] = $file;
                    }
                }
            }
        }

        if ( empty( $font_urls ) ) {
            return '';
        }

        // Group by weight (look for 400/regular and 500/medium hints in filename).
        $faces = [];
        foreach ( $font_urls as $url ) {
            // Determine format.
            $ext = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );
            $format_map = [
                'woff2' => 'woff2',
                'woff'  => 'woff',
                'ttf'   => 'truetype',
                'otf'   => 'opentype',
            ];
            $format = $format_map[ $ext ] ?? 'truetype';

            // Determine weight from filename hints.
            $basename = strtolower( basename( $url ) );
            if ( preg_match( '/(medium|500|bold)/i', $basename ) ) {
                $weight = '500';
            } else {
                $weight = '400';
            }

            // Convert local paths to data URIs for DOMPDF compatibility.
            $src = $url;
            if ( file_exists( $url ) ) {
                $data = base64_encode( file_get_contents( $url ) );
                $mime = 'application/octet-stream';
                if ( $ext === 'woff2' ) $mime = 'font/woff2';
                elseif ( $ext === 'woff' ) $mime = 'font/woff';
                elseif ( $ext === 'ttf' ) $mime = 'font/ttf';
                $src = 'data:' . $mime . ';base64,' . $data;
            }

            $faces[ $weight ][] = 'url("' . $src . '") format("' . $format . '")';
        }

        $css = '<style>' . "\n";
        foreach ( $faces as $weight => $sources ) {
            $css .= '@font-face {
    font-family: "Apotheca";
    src: ' . implode( ', ', $sources ) . ';
    font-weight: ' . $weight . ';
    font-style: normal;
}' . "\n";
        }
        $css .= '</style>';

        return $css;
    }
}
