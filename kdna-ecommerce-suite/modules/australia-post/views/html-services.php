<?php
/**
 * Template to render services input fields.
 *
 * @var KDNA_Shipping_Australia_Post $shipping_method
 */
defined( 'ABSPATH' ) || exit;
?>
<tr valign="top" id="service_options">
	<th scope="row" class="titledesc"><?php esc_html_e( 'Services', 'kdna-ecommerce' ); ?></th>
	<td class="forminp">
		<table class="kdna_auspost_services widefat">
			<thead>
			<th class="sort">&nbsp;</th>
			<th style="text-align:center; padding: 10px;"><?php esc_html_e( 'Service', 'kdna-ecommerce' ); ?></th>
			<th style="text-align:center; padding: 10px;"><?php esc_html_e( 'Enable', 'kdna-ecommerce' ); ?></th>
			<th><?php esc_html_e( 'Name', 'kdna-ecommerce' ); ?></th>
			<th style="text-align:center; padding: 10px;"><?php esc_html_e( 'Extra Cover', 'kdna-ecommerce' ); ?></th>
			<th style="text-align:center; padding: 10px;"><?php esc_html_e( 'Signature / Registered', 'kdna-ecommerce' ); ?></th>
			<th><?php echo esc_html( sprintf( __( 'Adjustment (%s)', 'kdna-ecommerce' ), get_woocommerce_currency_symbol() ) ); ?></th>
			<th><?php esc_html_e( 'Adjustment (%)', 'kdna-ecommerce' ); ?></th>
			</thead>
			<tbody>
			<?php
			$sort             = 0;
			$ordered_services = array();

			foreach ( $shipping_method->services as $code => $values ) {
				$name = is_array( $values ) ? $values['name'] : $values;

				if ( isset( $shipping_method->custom_services[ $code ]['order'] ) ) {
					$sort = absint( $shipping_method->custom_services[ $code ]['order'] );
				}

				while ( isset( $ordered_services[ $sort ] ) ) {
					++$sort;
				}

				$ordered_services[ $sort ] = array( $code, $name );
				++$sort;
			}

			ksort( $ordered_services );

			foreach ( $ordered_services as $value ) {
				$code = $value[0];
				$name = $value[1];

				if ( ! isset( $shipping_method->custom_services[ $code ] ) ) {
					$shipping_method->custom_services[ $code ] = array();
				}
				?>
				<tr>
					<td class="sort"><input type="hidden" class="order" name="<?php echo esc_attr( "kdna_auspost_service[$code][order]" ); ?>" value="<?php echo esc_attr( $shipping_method->custom_services[ $code ]['order'] ?? '' ); ?>"/></td>
					<td style="text-align:center"><strong><?php echo esc_html( $name ); ?></strong></td>
					<td style="text-align:center"><input type="checkbox" name="kdna_auspost_service[<?php echo esc_attr( $code ); ?>][enabled]" <?php checked( ( ! isset( $shipping_method->custom_services[ $code ]['enabled'] ) || ! empty( $shipping_method->custom_services[ $code ]['enabled'] ) ), true ); ?> /></td>
					<td><input type="text" name="kdna_auspost_service[<?php echo esc_attr( $code ); ?>][name]" placeholder="<?php echo esc_attr( "$name ({$shipping_method->title})" ); ?>" value="<?php echo esc_attr( $shipping_method->custom_services[ $code ]['name'] ?? '' ); ?>" size="30"/></td>
					<td style="text-align:center">
						<?php if ( in_array( $code, array_keys( $shipping_method->extra_cover ), true ) ) : ?>
							<input type="checkbox" name="kdna_auspost_service[<?php echo esc_attr( $code ); ?>][extra_cover]" <?php checked( ( ! isset( $shipping_method->custom_services[ $code ]['extra_cover'] ) || ! empty( $shipping_method->custom_services[ $code ]['extra_cover'] ) ), true ); ?> />
						<?php endif; ?>
					</td>
					<td style="text-align:center">
						<?php if ( in_array( $code, $shipping_method->delivery_confirmation, true ) ) : ?>
							<input type="checkbox" name="kdna_auspost_service[<?php echo esc_attr( $code ); ?>][delivery_confirmation]" <?php checked( ( ! isset( $shipping_method->custom_services[ $code ]['delivery_confirmation'] ) || ! empty( $shipping_method->custom_services[ $code ]['delivery_confirmation'] ) ), true ); ?> />
						<?php endif; ?>
					</td>
					<td><input type="text" name="kdna_auspost_service[<?php echo esc_attr( $code ); ?>][adjustment]" placeholder="N/A" value="<?php echo esc_attr( $shipping_method->custom_services[ $code ]['adjustment'] ?? '' ); ?>" size="4"/></td>
					<td><input type="text" name="kdna_auspost_service[<?php echo esc_attr( $code ); ?>][adjustment_percent]" placeholder="N/A" value="<?php echo esc_attr( $shipping_method->custom_services[ $code ]['adjustment_percent'] ?? '' ); ?>" size="4"/></td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
	</td>
</tr>
