<?php
/**
 * KDNA Smart Coupons - Coupon Email
 *
 * WooCommerce email class for sending coupon codes to customers.
 * Used for auto-generated coupons, gift certificates, store credits,
 * and manually triggered coupon emails.
 */
defined( 'ABSPATH' ) || exit;

class KDNA_SC_Email_Coupon extends WC_Email {

    /**
     * Coupon data to include in the email.
     *
     * @var array
     */
    public $coupon_data = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id             = 'kdna_sc_coupon';
        $this->customer_email = true;
        $this->title          = __( 'Smart Coupon', 'kdna-ecommerce' );
        $this->description    = __( 'Coupon emails are sent to customers when they receive a coupon code – for example, from a product purchase, gift certificate, store credit, or cashback reward.', 'kdna-ecommerce' );

        $this->template_html  = '';
        $this->template_plain = '';

        $this->subject = $this->get_option( 'subject', __( 'You have received a coupon!', 'kdna-ecommerce' ) );
        $this->heading = $this->get_option( 'heading', __( 'Your Coupon', 'kdna-ecommerce' ) );

        // Trigger on our custom action.
        add_action( 'kdna_sc_send_coupon_email', [ $this, 'trigger' ], 10, 2 );

        parent::__construct();
    }

    /**
     * Get email subject.
     */
    public function get_default_subject() {
        return __( 'You have received a coupon!', 'kdna-ecommerce' );
    }

    /**
     * Get email heading.
     */
    public function get_default_heading() {
        return __( 'Your Coupon', 'kdna-ecommerce' );
    }

    /**
     * Trigger the sending of this email.
     *
     * @param string $recipient Email address.
     * @param array  $coupon_data {
     *     @type string $code    Coupon code.
     *     @type string $amount  Discount amount.
     *     @type string $type    Discount type (fixed_cart, percent, store_credit, etc.).
     *     @type string $expiry  Expiry date string or empty.
     *     @type string $message Optional message from sender.
     * }
     */
    public function trigger( $recipient, $coupon_data ) {
        if ( ! $this->is_enabled() ) {
            return;
        }

        $this->recipient   = $recipient;
        $this->coupon_data = wp_parse_args( $coupon_data, [
            'code'    => '',
            'amount'  => '',
            'type'    => 'fixed_cart',
            'expiry'  => '',
            'message' => '',
        ] );

        if ( ! $this->get_recipient() ) {
            return;
        }

        $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );
    }

    /**
     * Get content HTML.
     */
    public function get_content_html() {
        return $this->build_email_html();
    }

    /**
     * Get content plain text.
     */
    public function get_content_plain() {
        $data = $this->coupon_data;

        $text  = $this->get_heading() . "\n\n";
        $text .= sprintf( __( 'Coupon Code: %s', 'kdna-ecommerce' ), strtoupper( $data['code'] ) ) . "\n";
        $text .= sprintf( __( 'Value: %s', 'kdna-ecommerce' ), $this->format_amount( $data['amount'], $data['type'] ) ) . "\n";

        if ( ! empty( $data['expiry'] ) ) {
            $text .= sprintf( __( 'Expires: %s', 'kdna-ecommerce' ), $data['expiry'] ) . "\n";
        }

        if ( ! empty( $data['message'] ) ) {
            $text .= "\n" . $data['message'] . "\n";
        }

        $text .= "\n" . __( 'Apply this coupon at checkout to redeem your discount.', 'kdna-ecommerce' ) . "\n";

        return $text;
    }

    /**
     * Build the email HTML content.
     */
    private function build_email_html() {
        $data = $this->coupon_data;
        $s    = [];

        if ( class_exists( 'KDNA_Smart_Coupons' ) ) {
            $s = KDNA_Smart_Coupons::get_settings();
        }

        $bg    = ! empty( $s['custom_bg_color'] ) ? $s['custom_bg_color'] : '#39cccc';
        $fg    = ! empty( $s['custom_fg_color'] ) ? $s['custom_fg_color'] : '#ffffff';
        $code  = strtoupper( $data['code'] );
        $value = $this->format_amount( $data['amount'], $data['type'] );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f7f7f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f7;padding:30px 0;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                            <!-- Header -->
                            <tr>
                                <td style="background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $fg ); ?>;padding:30px 40px;text-align:center;">
                                    <h1 style="margin:0;font-size:24px;font-weight:700;"><?php echo esc_html( $this->get_heading() ); ?></h1>
                                </td>
                            </tr>

                            <!-- Coupon Card -->
                            <tr>
                                <td style="padding:30px 40px;">
                                    <table width="100%" cellpadding="0" cellspacing="0" style="border:2px dashed <?php echo esc_attr( $bg ); ?>;border-radius:8px;overflow:hidden;">
                                        <tr>
                                            <td width="120" style="background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $fg ); ?>;text-align:center;padding:20px;vertical-align:middle;">
                                                <div style="font-size:28px;font-weight:700;line-height:1.2;"><?php echo $value; ?></div>
                                                <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;opacity:0.9;margin-top:4px;">
                                                    <?php echo $data['type'] === 'store_credit' ? esc_html__( 'Credit', 'kdna-ecommerce' ) : esc_html__( 'Off', 'kdna-ecommerce' ); ?>
                                                </div>
                                            </td>
                                            <td style="padding:20px 25px;vertical-align:middle;">
                                                <div style="font-family:monospace;font-size:18px;font-weight:700;letter-spacing:2px;color:#333;"><?php echo esc_html( $code ); ?></div>
                                                <?php if ( ! empty( $data['expiry'] ) ) : ?>
                                                    <div style="font-size:13px;color:#999;margin-top:6px;">
                                                        <?php printf( esc_html__( 'Valid until %s', 'kdna-ecommerce' ), esc_html( $data['expiry'] ) ); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <?php if ( ! empty( $data['message'] ) ) : ?>
                            <!-- Message -->
                            <tr>
                                <td style="padding:0 40px 20px;">
                                    <div style="background:#f9f9f9;border-left:4px solid <?php echo esc_attr( $bg ); ?>;padding:15px 20px;border-radius:0 4px 4px 0;color:#555;font-size:14px;line-height:1.5;">
                                        <?php echo wp_kses_post( nl2br( $data['message'] ) ); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- CTA -->
                            <tr>
                                <td style="padding:10px 40px 30px;text-align:center;">
                                    <?php
                                    $shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url();
                                    ?>
                                    <a href="<?php echo esc_url( $shop_url ); ?>" style="display:inline-block;background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $fg ); ?>;padding:14px 32px;border-radius:6px;text-decoration:none;font-weight:600;font-size:15px;">
                                        <?php esc_html_e( 'Shop Now', 'kdna-ecommerce' ); ?>
                                    </a>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="background:#f7f7f7;padding:20px 40px;text-align:center;font-size:12px;color:#999;border-top:1px solid #eee;">
                                    <?php esc_html_e( 'Apply this coupon at checkout to redeem your discount.', 'kdna-ecommerce' ); ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Format the coupon amount for display.
     */
    private function format_amount( $amount, $type ) {
        if ( $type === 'percent' ) {
            return round( (float) $amount ) . '%';
        }

        if ( function_exists( 'wc_price' ) ) {
            return wp_strip_all_tags( wc_price( $amount ) );
        }

        return '$' . number_format( (float) $amount, 2 );
    }

    /**
     * Initialise form fields for WooCommerce email settings.
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Enable/Disable', 'kdna-ecommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable this email notification', 'kdna-ecommerce' ),
                'default' => 'yes',
            ],
            'subject' => [
                'title'       => __( 'Subject', 'kdna-ecommerce' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __( 'Email subject line.', 'kdna-ecommerce' ),
                'placeholder' => $this->get_default_subject(),
                'default'     => $this->get_default_subject(),
            ],
            'heading' => [
                'title'       => __( 'Email heading', 'kdna-ecommerce' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __( 'Main heading in the email.', 'kdna-ecommerce' ),
                'placeholder' => $this->get_default_heading(),
                'default'     => $this->get_default_heading(),
            ],
            'email_type' => [
                'title'       => __( 'Email type', 'kdna-ecommerce' ),
                'type'        => 'select',
                'description' => __( 'Choose which format of email to send.', 'kdna-ecommerce' ),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ],
        ];
    }
}
