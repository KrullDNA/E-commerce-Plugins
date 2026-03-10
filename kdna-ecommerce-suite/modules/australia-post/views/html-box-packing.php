<?php
/**
 * Template to render box packing input fields.
 *
 * @var KDNA_Shipping_Australia_Post $shipping_method
 */
defined( 'ABSPATH' ) || exit;
?>
<tr valign="top" id="packing_options">
	<th scope="row" class="titledesc"><?php esc_html_e( 'Box Sizes', 'kdna-ecommerce' ); ?></th>
	<td class="forminp">
		<style type="text/css">
			.kdna_auspost_boxes td, .kdna_auspost_services td { vertical-align: middle; padding: 4px 7px; }
			.kdna_auspost_boxes th, .kdna_auspost_services th { vertical-align: middle; padding: 9px 7px; }
			.kdna_auspost_boxes td input { margin-right: 4px; }
			.kdna_auspost_boxes .check-column { vertical-align: middle; text-align: left; padding: 0 7px; }
			.kdna_auspost_services th.sort { width: 16px; }
			.kdna_auspost_services td.sort { cursor: move; width: 16px; padding: 0 16px; background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAHUlEQVQYV2O8f//+fwY8gJGgAny6QXKETRgEVgAAXxAVsa5Xr3QAAAAASUVORK5CYII=) no-repeat center; }
		</style>
		<table class="kdna_auspost_boxes widefat">
			<thead>
			<tr>
				<th class="check-column"><input type="checkbox"/></th>
				<th><?php esc_html_e( 'Name', 'kdna-ecommerce' ); ?></th>
				<th><?php esc_html_e( 'Outer Length', 'kdna-ecommerce' ); ?></th>
				<th><?php esc_html_e( 'Outer Width', 'kdna-ecommerce' ); ?></th>
				<th><?php esc_html_e( 'Outer Height', 'kdna-ecommerce' ); ?></th>
				<th><?php esc_html_e( 'Inner Length', 'kdna-ecommerce' ); ?></th>
				<th><?php esc_html_e( 'Inner Width', 'kdna-ecommerce' ); ?></th>
				<th><?php esc_html_e( 'Inner Height', 'kdna-ecommerce' ); ?></th>
				<th><?php esc_html_e( 'Weight of box', 'kdna-ecommerce' ); ?></th>
				<th><?php esc_html_e( 'Max Weight', 'kdna-ecommerce' ); ?></th>
				<th><?php esc_html_e( 'Type', 'kdna-ecommerce' ); ?></th>
				<th><?php esc_html_e( 'Enabled', 'kdna-ecommerce' ); ?></th>
			</tr>
			</thead>
			<tfoot>
			<tr>
				<th colspan="3">
					<a href="#" class="button plus insert"><?php esc_html_e( 'Add Box', 'kdna-ecommerce' ); ?></a>
					<a href="#" class="button minus remove"><?php esc_html_e( 'Remove selected box(es)', 'kdna-ecommerce' ); ?></a>
				</th>
				<th colspan="9">
					<small class="description"><?php esc_html_e( 'Items will be packed into these boxes based on dimensions and volume. Outer dimensions are sent to Australia Post; inner dimensions are used for packing.', 'kdna-ecommerce' ); ?></small>
				</th>
			</tr>
			</tfoot>
			<tbody id="rates">
			<?php
			$default_box_count = count( $shipping_method->default_boxes );
			$i = 0;

			foreach ( $shipping_method->get_all_boxes() as $key => $box ) {
				$default_box = $i < $default_box_count;
				++$i;
				$readonly = $default_box ? 'readonly' : '';
				?>
				<tr>
					<td class="check-column">
						<?php if ( ! $default_box ) : ?>
							<input title="select" type="checkbox"/>
						<?php endif; ?>
					</td>
					<td>
					<?php if ( $default_box ) : ?>
						<?php echo esc_html( $box['name'] ); ?>
					<?php else : ?>
						<input type="text" size="10" name="boxes_name[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $box['name'] ); ?>"/>
					<?php endif; ?>
					</td>
					<td><label class="dimension"><input <?php echo $readonly; ?> type="text" size="5" name="boxes_outer_length[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $box['outer_length'] ); ?>"/><span>cm</span></label></td>
					<td><label class="dimension"><input <?php echo $readonly; ?> type="text" size="5" name="boxes_outer_width[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $box['outer_width'] ); ?>"/><span>cm</span></label></td>
					<td><label class="dimension"><input <?php echo $readonly; ?> type="text" size="5" name="boxes_outer_height[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $box['outer_height'] ); ?>"/><span>cm</span></label></td>
					<td><label class="dimension"><input <?php echo $readonly; ?> type="text" size="5" name="boxes_inner_length[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $box['inner_length'] ); ?>"/><span>cm</span></label></td>
					<td><label class="dimension"><input <?php echo $readonly; ?> type="text" size="5" name="boxes_inner_width[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $box['inner_width'] ); ?>"/><span>cm</span></label></td>
					<td><label class="dimension"><input <?php echo $readonly; ?> type="text" size="5" name="boxes_inner_height[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $box['inner_height'] ); ?>"/><span>cm</span></label></td>
					<td><label class="weight"><input <?php echo $readonly; ?> type="text" size="5" name="boxes_box_weight[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $box['box_weight'] ); ?>"/><span>kg</span></label></td>
					<td><label class="weight"><input <?php echo $readonly; ?> type="text" size="5" name="boxes_max_weight[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $box['max_weight'] ); ?>" placeholder="22"/><span>kg</span></label></td>
					<td>
						<select <?php disabled( $default_box ); ?> name="boxes_type[<?php echo esc_attr( $key ); ?>]">
							<option value="box" <?php selected( $box['type'] ?? 'box', 'box' ); ?>>Box</option>
							<option value="envelope" <?php selected( $box['type'] ?? '', 'envelope' ); ?>>Envelope</option>
							<option value="packet" <?php selected( $box['type'] ?? '', 'packet' ); ?>>Packet</option>
							<option value="tube" <?php selected( $box['type'] ?? '', 'tube' ); ?>>Tube</option>
						</select>
					</td>
					<td><input type="checkbox" name="boxes_enabled[<?php echo esc_attr( $key ); ?>]" <?php checked( ! isset( $box['enabled'] ) || $box['enabled'], true ); ?> /></td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<script type="text/javascript">
			jQuery(function(){
				jQuery('#woocommerce_kdna_australia_post_packing_method').change(function(){
					if(jQuery(this).val()=='box_packing'){jQuery('#packing_options').show();}else{jQuery('#packing_options').hide();}
					if(jQuery(this).val()=='weight'){jQuery('#woocommerce_kdna_australia_post_max_weight').closest('tr').show();}else{jQuery('#woocommerce_kdna_australia_post_max_weight').closest('tr').hide();}
				}).change();

				jQuery('.kdna_auspost_boxes .insert').click(function(){
					var $tbody=jQuery('.kdna_auspost_boxes').find('tbody');
					var size=$tbody.find('tr').length;
					var code='<tr class="new"><td class="check-column"><input type="checkbox"/></td><td><input type="text" size="10" name="boxes_name['+size+']"/></td><td><label class="dimension"><input type="text" size="5" name="boxes_outer_length['+size+']"/><span>cm</span></label></td><td><label class="dimension"><input type="text" size="5" name="boxes_outer_width['+size+']"/><span>cm</span></label></td><td><label class="dimension"><input type="text" size="5" name="boxes_outer_height['+size+']"/><span>cm</span></label></td><td><label class="dimension"><input type="text" size="5" name="boxes_inner_length['+size+']"/><span>cm</span></label></td><td><label class="dimension"><input type="text" size="5" name="boxes_inner_width['+size+']"/><span>cm</span></label></td><td><label class="dimension"><input type="text" size="5" name="boxes_inner_height['+size+']"/><span>cm</span></label></td><td><label class="weight"><input type="text" size="5" name="boxes_box_weight['+size+']"/><span>kg</span></label></td><td><label class="weight"><input type="text" size="5" name="boxes_max_weight['+size+']"/><span>kg</span></label></td><td><select name="boxes_type['+size+']"><option value="box" selected>Box</option><option value="envelope">Envelope</option><option value="packet">Packet</option><option value="tube">Tube</option></select></td><td><input type="checkbox" name="boxes_enabled['+size+']" checked/></td></tr>';
					$tbody.append(code);
					return false;
				});

				jQuery('.kdna_auspost_boxes .remove').click(function(){
					jQuery('.kdna_auspost_boxes').find('tbody .check-column input:checked').each(function(){
						jQuery(this).closest('tr').hide().find('input').val('');
					});
					return false;
				});

				jQuery('.kdna_auspost_services tbody').sortable({
					items:'tr',cursor:'move',axis:'y',handle:'.sort',scrollSensitivity:40,forcePlaceholderSize:true,helper:'clone',opacity:0.65,
					stop:function(){
						jQuery('.kdna_auspost_services tbody tr').each(function(index,el){
							jQuery('input.order',el).val(parseInt(jQuery(el).index('.kdna_auspost_services tr')));
						});
					}
				});
			});
		</script>
	</td>
</tr>
