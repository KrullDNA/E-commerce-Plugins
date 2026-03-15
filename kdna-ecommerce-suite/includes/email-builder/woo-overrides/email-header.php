<?php
/**
 * Minimal WooCommerce email header override.
 *
 * Used when a KDNA Email Builder template is active for WooCommerce emails.
 * Outputs only the email heading without WooCommerce's styled header/wrapper.
 *
 * @package KDNA_Ecommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta content="width=device-width, initial-scale=1.0" name="viewport">
</head>
<body>
<?php if ( ! empty( $email_heading ) ) : ?>
	<h1 style="margin:0 0 16px;"><?php echo wp_kses_post( $email_heading ); ?></h1>
<?php endif; ?>
