/* global jQuery, kdna_tracking */
jQuery( function( $ ) {
	'use strict';

	var $metabox     = $( '#kdna-shipment-tracking' );
	var $form        = $( '#kdna-tracking-form' );
	var $items       = $( '#kdna-tracking-items' );
	var $showBtn     = $metabox.find( '.button-show-form' );
	var $showWrap    = $metabox.find( '.kdna-tracking-show-form' );

	// Initialize date picker.
	$( '#kdna_date_shipped' ).datepicker( {
		dateFormat: 'yy-mm-dd',
		maxDate: 0,
	} );

	// Show/hide custom provider fields based on provider selection.
	$( '#kdna_tracking_provider' ).on( 'change', function() {
		if ( $( this ).val() === 'custom' ) {
			$( '.custom_tracking_provider_field, .custom_tracking_link_field' ).show();
		} else {
			$( '.custom_tracking_provider_field, .custom_tracking_link_field' ).hide();
		}
	} );

	// Show form.
	$showBtn.on( 'click', function( e ) {
		e.preventDefault();
		$form.slideDown( 200 );
		$showWrap.hide();
	} );

	// Cancel form.
	$form.on( 'click', '.button-cancel-form', function( e ) {
		e.preventDefault();
		$form.slideUp( 200 );
		$showWrap.show();
		resetForm();
	} );

	// Save tracking.
	$form.on( 'click', '.button-save-form', function( e ) {
		e.preventDefault();

		var trackingNumber = $( '#kdna_tracking_number' ).val();
		if ( ! trackingNumber ) {
			alert( 'Please enter a tracking number.' );
			return;
		}

		var $btn = $( this );
		$btn.prop( 'disabled', true );

		$.ajax( {
			url: kdna_tracking.ajax_url,
			type: 'POST',
			data: {
				action:                   'kdna_tracking_save',
				security:                 kdna_tracking.save_nonce,
				order_id:                 $( '#kdna_tracking_order_id' ).val(),
				tracking_provider:        $( '#kdna_tracking_provider' ).val(),
				custom_tracking_provider: $( '#kdna_custom_tracking_provider' ).val(),
				custom_tracking_link:     $( '#kdna_custom_tracking_link' ).val(),
				tracking_number:          trackingNumber,
				date_shipped:             $( '#kdna_date_shipped' ).val(),
			},
			success: function( response ) {
				if ( response.success ) {
					$items.html( response.data.html );
					$form.slideUp( 200 );
					$showWrap.show();
					resetForm();
				} else {
					alert( response.data || 'Error saving tracking.' );
				}
				$btn.prop( 'disabled', false );
			},
			error: function() {
				alert( 'Error saving tracking.' );
				$btn.prop( 'disabled', false );
			}
		} );
	} );

	// Delete tracking.
	$items.on( 'click', '.delete-tracking', function( e ) {
		e.preventDefault();

		if ( ! confirm( 'Are you sure you want to delete this tracking item?' ) ) {
			return;
		}

		var $link      = $( this );
		var trackingId = $link.data( 'tracking-id' );

		$.ajax( {
			url: kdna_tracking.ajax_url,
			type: 'POST',
			data: {
				action:      'kdna_tracking_delete',
				security:    kdna_tracking.delete_nonce,
				order_id:    $( '#kdna_tracking_order_id' ).val(),
				tracking_id: trackingId,
			},
			success: function( response ) {
				if ( response.success ) {
					$items.html( response.data.html );
				}
			}
		} );
	} );

	function resetForm() {
		$( '#kdna_tracking_provider' ).val( 'custom' ).trigger( 'change' );
		$( '#kdna_custom_tracking_provider' ).val( '' );
		$( '#kdna_custom_tracking_link' ).val( '' );
		$( '#kdna_tracking_number' ).val( '' );
		$( '#kdna_date_shipped' ).val( '' );
	}
} );
