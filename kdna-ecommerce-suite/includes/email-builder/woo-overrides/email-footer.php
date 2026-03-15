<?php
/**
 * Minimal WooCommerce email footer override.
 *
 * When a KDNA Email Builder template is active, this replaces WooCommerce's
 * default email-footer.php to prevent WC's styled footer from rendering.
 * The KDNA template (applied via woocommerce_mail_content filter) provides
 * its own footer.
 */
defined( 'ABSPATH' ) || exit;
?>
</body>
</html>
