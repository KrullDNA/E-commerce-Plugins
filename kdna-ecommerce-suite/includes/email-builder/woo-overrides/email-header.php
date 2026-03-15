<?php
/**
 * Minimal WooCommerce email header override.
 *
 * When a KDNA Email Builder template is active, this replaces WooCommerce's
 * default email-header.php to prevent WC's styled header/logo from rendering.
 * The KDNA template (applied via woocommerce_mail_content filter) provides
 * its own header, logo, and styling.
 *
 * @var string $email_heading The email heading text.
 */
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
</head>
<body>
<?php if ( $email_heading ) : ?>
	<h1 style="font-size:30px;font-weight:300;line-height:1.2;margin:0 0 16px;"><?php echo wp_kses_post( $email_heading ); ?></h1>
<?php endif; ?>
